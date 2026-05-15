import { unwrapSyntheticReplyEnvelope } from "@fe-agent/core";
import { journalEntry } from "@fe-agent/observability";
import {
  composeRestrictedPolicyRefusalWithLanguageModel,
  enforceRestrictedReplyComplianceWithLanguageModel,
} from "./compliance-language-model.js";
import { coerceReplyForVietnameseAppUser } from "./reply-language-coerce.js";
import { REPLY_MATCH_CUSTOMER_LANGUAGE_INSTRUCTION } from "./reply-language-policy.js";

/** Một phần prompt (luôn gắn khi restricted + có (B)) — nghiệp vụ clarification do LLM đọc JSON, không gate bằng hàm JS. */
const CLARIFICATION_RULES_IN_PROMPT_VI = [
  "",
  "Khi trong (B) có bất kỳ object nào với `clarification_needed: true`:",
  "- Khách chưa đủ thông tin để kết luận lịch chuyến → `reply` **chỉ hỏi tiếp** theo `missing_slots` / `suggested_questions_vi` (tiếng Việt, ngắn, thân thiện).",
  "- **CẤM** khẳng định có/không chuyến, vé, tuyến, chỗ trống hay giờ chạy cụ thể; **CẤM** các cụm kiểu \"không có chuyến\", \"hết vé\", \"không còn xe\".",
  "- Có thể gợi ý giờ/khung giờ từ `suggested_reply_chips_vi` nếu thiếu `gio_khoi_hanh`.",
  "- Trong trạng thái đó, **không dùng (A)** để kết luận lịch chuyến (tránh mâu thuẫn với bước hỏi bổ sung).",
  "Trường `bot_next_action_vi` là chỉ dẫn pipeline — không sao chép nguyên làm `reply`.",
].join("\n");

const CONFIRMATION_RULES_IN_PROMPT_VI = [
  "",
  "Khi trong kết quả thao tác có lỗi dạng `confirmation_required:<tool>`:",
  "- Chưa thực hiện thao tác nhạy cảm. Không nói thao tác đã thành công.",
  "- Hỏi khách xác nhận rõ ràng trước khi đặt vé, hủy vé, đổi thông tin, thanh toán hoặc thao tác nhạy cảm tương tự.",
  "- Không tự gọi lại thao tác trong câu trả lời; chỉ viết lời xác nhận cần thiết cho khách.",
].join("\n");

/**
 * @param graphDependencies
 * @param {boolean} isQuestionAnswerPdfOnly
 * @param {boolean} useRestrictedAnswerSourcesOnly
 */
export function createSynthesizerNode(
  graphDependencies,
  isQuestionAnswerPdfOnly,
  useRestrictedAnswerSourcesOnly
) {
  const synthDomainLines =
    typeof graphDependencies.synthesizerDomainInstructions === "string" &&
    graphDependencies.synthesizerDomainInstructions.trim()
      ? ["", graphDependencies.synthesizerDomainInstructions.trim(), ""]
      : [];

  return async function synthesizerGraphNode(graphState) {
    graphDependencies.bus?.emit("stage", {
      stage: "synthesizer",
      status: "enter",
      correlationId: graphState.correlationId,
    });

    const MAX_RAG_CHARS = 6000;
    const MAX_TOOLS_CHARS = 10000;
    const MAX_MSG_CHARS = 600;

    const retrievedSnippetText = graphState.ragContext
      .map((retrievalHit) =>
        retrievalHit.text || (retrievalHit.metadata?.preview ?? "")
      )
      .filter(Boolean)
      .join("\n---\n")
      .slice(0, MAX_RAG_CHARS);

    const recentTranscriptText = graphState.messages
      .slice(-6)
      .map(
        (chatMessage) =>
          `${chatMessage.role}: ${String(chatMessage.content ?? "").slice(0, MAX_MSG_CHARS)}`
      )
      .join("\n");

    let synthesisPrompt;
    if (isQuestionAnswerPdfOnly) {
      synthesisPrompt = [
        REPLY_MATCH_CUSTOMER_LANGUAGE_INSTRUCTION,
        ...synthDomainLines,
        "",
        'Nhiệm vụ / Task: Trả lời câu hỏi chỉ DỰA VÀ nội dung PDF extracted below.',
        "Giọng / tone: ngắn gọn, như nhân viên hỗ trợ. Short support tone.",
        "CẤM: nhắc tới LLM/model/API/tool/JSON/embeddings/mã/system/architecture/developer/debug.",
        "CẤM: mô tả bản thân là bot/chương trình máy/trí tuệ nhân tạo/mô hình ngôn ngữ/GPT. Luôn trả lời như người hỗ trợ chỉ dựa trên tài liệu khách có.",
        "CẤM: “tôi là trợ lý được tạo/tự động/ảo hoặc không phải người…” — chỉ báo không có trong tài liệu.",
        "CẤM: trích markdown code block hay bảng kỹ thuật.",
        "Không được bịa: họ tên, email, điện thoại, địa chỉ, hotline, website hay bất kỳ cách liên hệ nào chỉ được nêu khi NGUYÊN VĂN xuất hiện trong phần trích bên trên.",
        "Nếu câu hỏi không được nội dung trích trả lời (ví dụ hỏi tên của bạn, thông tin cá nhân): chỉ báo không có trong tài liệu — KHÔNG khuyến khách gọi số/email nào không thuộc tài liệu.",
        retrievedSnippetText
          ? `Nội dung trong tài liệu (trích):\n${retrievedSnippetText}\n`
          : "Không có đoạn nào được trích từ tài liệu.",
        "",
        "Hội thoại:",
        recentTranscriptText,
        "",
        "Định dạng: JSON một dòng, object có **đúng một** key `reply` (string).",
        "Cấm thêm key `question`, `answer`, `choices` hoặc key khác — chỉ `reply`.",
        "Giá trị `reply` là câu trả lời hoàn chỉnh cho khách, không placeholder.",
      ].join("\n");
    } else if (useRestrictedAnswerSourcesOnly) {
      const recentToolResultsDigest = JSON.stringify(
        graphState.toolResults.slice(-8),
        null,
        2
      ).slice(0, MAX_TOOLS_CHARS);
      synthesisPrompt = [
        "Bạn chỉ được trả lời khách DỰA VÀ đúng hai nguồn sau:",
        "(A) các đoạn TRÍCH từ TÀI LIỆU (PDF) được nạp;",
        "(B) KẾT QUẢ CÁC THAO TÁC (JSON) đã thực thi — chỉ được diễn giải bằng lời, không được bịa.",
        "TUYỆT ĐỐI không bổ sung kiến thức, số liệu hay ví dụ ngoài A và B.",
        ...synthDomainLines,
        "Không nhắc: LLM, model, embedding, vector, pipeline, REST, SDK, nhà phát triển.",
        "CẤM: mô tả bản thân là AI/bot/trí tuệ nhân tạo/mô hình ngôn ngữ/GPT/OpenAI hoặc tên công cụ kỹ thuật. Chỉ nói theo vai trò hỗ trợ chỉ có thông tin từ tài liệu và thao tác.",
        "CẤM: câu kiểu “tôi là trợ lý được tạo ra / trợ lý ảo / không phải người hay tổ chức” — đó vẫn là tự mô tả kỹ thuật; với câu hỏi về danh tính chỉ nói là không có trong (A) và (B).",
        "Không được bịa email, điện thoại, hotline, website hay hướng dẫn liên hệ trừ khi CHÍNH XÁC các chữ đó nằm trong (A) hoặc (B).",
        "Nếu câu hỏi không được (A)+(B) trả lời: chỉ nói không có trong tài liệu và kết quả thao tác — không tự khuyến nghị số/email giả định.",
        "Nếu ý của khách không thuộc chủ đề trong (A) (ví dụ hỏi tên ai đang trả lời): chỉ nói thông tin đó không có trong tài liệu và thao tác — không được ghép các đoạn dài không liên quan trong (A) để lấp câu trả lời.",
        REPLY_MATCH_CUSTOMER_LANGUAGE_INSTRUCTION,
        "",
        "Rõ ràng, không thuật ngữ kỹ thuật hệ thống hay AI.",
        retrievedSnippetText
          ? `--- (A) Trích tài liệu ---\n${retrievedSnippetText}`
          : "(A) Hiện chưa có đoạn trích tài liệu cho bối cảnh này.",
        "",
        recentToolResultsDigest !== "[]"
          ? `--- (B) Kết quả thao tác ---\n${recentToolResultsDigest}`
          : "(B) Hiện chưa có thao tác nào trả kết quả.",
        CLARIFICATION_RULES_IN_PROMPT_VI,
        CONFIRMATION_RULES_IN_PROMPT_VI,
        "",
        "Nếu trong (B) có `clarification_needed: true`: **bạn** soạn toàn bộ `reply` như nhân viên — dựa vào `missing_slots`, `suggested_questions_vi`, và (nếu có) `suggested_reply_chips_vi` / `inferred_from_message_so_far`; không dán JSON, không đọc tên field cho khách.",
        "",
        "Hội thoại:",
        recentTranscriptText,
        "",
        "Định dạng: JSON một dòng, object có **đúng một** key `reply` (string).",
        "Cấm key `question` / `answer` — một số model hay lỗi schema đó; chỉ dùng `reply`.",
        "`reply` là câu trả lời hoàn chỉnh, không placeholder.",
      ].join("\n");
    } else {
      const recentToolResultsDigest = JSON.stringify(
        graphState.toolResults.slice(-5),
        null,
        2
      ).slice(0, MAX_TOOLS_CHARS);
      synthesisPrompt = [
        REPLY_MATCH_CUSTOMER_LANGUAGE_INSTRUCTION,
        ...synthDomainLines,
        "",
        "You are synthesizing the final assistant reply.",
        "Use tool JSON and optional RAG snippets.",
        "Return JSON with **only** the key `reply` (string). Do **not** use `question` and `answer` keys.",
        retrievedSnippetText ? `Snippets:\n${retrievedSnippetText}\n` : "",
        recentToolResultsDigest !== "[]"
          ? `Tools:\n${recentToolResultsDigest}`
          : "No verified tool payloads.",
        CLARIFICATION_RULES_IN_PROMPT_VI,
        CONFIRMATION_RULES_IN_PROMPT_VI,
        "",
        recentTranscriptText,
        "",
        'Return JSON ONLY: { "reply": string }',
      ].join("\n");
    }

    let finalReplyText;

    const latestUserQuestionText =
      [...graphState.messages]
        .reverse()
        .find((chatMessage) => chatMessage.role === "user")?.content ?? "";

    const hasNoDocumentOrToolSources =
      useRestrictedAnswerSourcesOnly &&
      !isQuestionAnswerPdfOnly &&
      !retrievedSnippetText.trim() &&
      JSON.stringify(graphState.toolResults?.slice?.(-8) ?? []) === "[]";

    if (isQuestionAnswerPdfOnly && !retrievedSnippetText.trim()) {
      finalReplyText =
        await composeRestrictedPolicyRefusalWithLanguageModel({
          languageModel: graphDependencies.llm,
          refusalReasonExplanation:
            "Không có đoạn trích PDF nào khớp bối cảnh hiện tại; không được suy đoán hay bịa nội dung.",
          latestUserQuestionText,
          allowedCorpusExcerptPreview: "",
        });
    } else if (hasNoDocumentOrToolSources) {
      finalReplyText =
        await composeRestrictedPolicyRefusalWithLanguageModel({
          languageModel: graphDependencies.llm,
          refusalReasonExplanation:
            "Chưa có đoạn trích tài liệu và không có kết quả thao tác để làm căn cứ trả lời; chỉ báo và mời hỏi lại theo tài liệu hoặc dùng gợi ý, không bịa chi tiết.",
          latestUserQuestionText,
          allowedCorpusExcerptPreview: "",
        });
    } else {
      const rawSynthesisOutput =
        await graphDependencies.llm.completeJson(synthesisPrompt);
      finalReplyText = unwrapSyntheticReplyEnvelope(rawSynthesisOutput);
      if (isQuestionAnswerPdfOnly || useRestrictedAnswerSourcesOnly) {
        const allowedSourceCorpusText = `${retrievedSnippetText}\n${JSON.stringify(
          graphState.toolResults?.slice(-8) ?? []
        )}`;
        finalReplyText = await enforceRestrictedReplyComplianceWithLanguageModel(
          graphDependencies.llm,
          finalReplyText,
          allowedSourceCorpusText,
          latestUserQuestionText
        );
      }
    }

    finalReplyText = coerceReplyForVietnameseAppUser(
      latestUserQuestionText,
      finalReplyText,
    );

    graphDependencies.bus?.emit("token", finalReplyText);

    return {
      finalAnswer: finalReplyText,
      journal: [
        journalEntry("synthesizer", {
          length: String(finalReplyText).length,
        }),
      ],
    };
  };
}
