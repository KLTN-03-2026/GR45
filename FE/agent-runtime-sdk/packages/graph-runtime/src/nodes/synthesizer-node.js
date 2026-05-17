import { unwrapSyntheticReplyEnvelope } from "@fe-agent/core/model-json";
import { journalEntry } from "../journal.js";
import {
  composeRestrictedPolicyRefusalWithLanguageModel,
  enforceRestrictedReplyComplianceWithLanguageModel,
} from "./compliance-language-model.js";
import { coerceReplyForVietnameseAppUser } from "../reply/reply-language-coerce.js";
import { REPLY_MATCH_CUSTOMER_LANGUAGE_INSTRUCTION } from "../reply/reply-language-policy.js";
import { isFunction, isString, textOrEmpty, valueOr } from "../value.js";

/** Một phần prompt (luôn gắn khi restricted + có (B)) — nghiệp vụ clarification do LLM đọc JSON, không gate bằng hàm JS. */
const CLARIFICATION_RULES_IN_PROMPT_VI = [
  "",
  "Khi trong (B) có bất kỳ object nào với `clarification_needed: true`:",
  "- Khách chưa đủ thông tin để kết luận kết quả thao tác → `reply` **chỉ hỏi tiếp** theo `missing_slots` / `suggested_questions_vi` (tiếng Việt, ngắn, thân thiện).",
  "- **CẤM** khẳng định có/không có dữ liệu, trạng thái, kết quả cụ thể, hoặc thao tác đã hoàn tất.",
  "- Có thể gợi ý giờ/khung giờ từ `suggested_reply_chips_vi` nếu thiếu `gio_khoi_hanh`.",
  "- Trong trạng thái đó, **không dùng (A)** để kết luận thay cho bước hỏi bổ sung.",
  "Trường `bot_next_action_vi` là chỉ dẫn pipeline — không sao chép nguyên làm `reply`.",
].join("\n");

/** Anti-hallucination rules cho số/ngày/giờ/mã thực thể — chỉ dùng nguyên văn từ (B). */
const NO_HALLUCINATION_RULES_IN_PROMPT_VI = [
  "",
  "CẤM BỊA DỮ LIỆU CỤ THỂ:",
  "- Giờ khởi hành, giờ đến, ngày khởi hành: chỉ dùng giá trị **chính xác** từ (B). Cấm làm tròn, suy ra, hay đề xuất giờ không có trong (B).",
  "- Mã chuyến, mã vé, mã tuyến, mã voucher, mã đặt vé, mã ghế: chỉ dùng nguyên văn từ (B). Cấm tự tạo mã.",
  "- Giá vé, số ghế còn, số điểm thưởng, số tiền hoàn: chỉ dùng số chính xác từ (B). Cấm ước lượng.",
  "- Họ tên khách, email, số điện thoại, địa chỉ: chỉ dùng từ (B); cấm bịa.",
  "- Nếu (B) không có dữ liệu đủ để trả lời chắc chắn: HỎI LẠI khách (clarification) hoặc gợi ý kiểm tra/đặt vé — TUYỆT ĐỐI không tự đề xuất số/giờ/mã.",
  "- CẤM câu hứa: \"tôi sẽ tra cứu\", \"em sẽ tìm\", \"đang xử lý\", \"để tôi kiểm tra giúp\". Nếu cần tra cứu thêm, hỏi khách bổ sung slot rồi để hệ thống tự gọi tool — KHÔNG tự hứa hành động trong câu trả lời.",
  "- Khách yêu cầu một ngày trong quá khứ: từ chối lịch sự, mời chọn ngày từ hôm nay trở đi — KHÔNG hứa tra cứu chuyến quá khứ.",
  "",
  "TUYẾN ĐƯỜNG (route) — MỘT CHIỀU, KHÔNG ĐƯỢC DIỄN GIẢI THÀNH HAI CHIỀU:",
  "- Một row tuyến đường trong (B) có `huong: \"one_way\"` LUÔN là MỘT chiều duy nhất: `diem_di → diem_den`.",
  "- `gio_khoi_hanh` (hoặc `gio`) là giờ BẮT ĐẦU chiều đó. `gio_den_noi` (hoặc `gio_ket_thuc`) là giờ ĐẾN cuối chiều đó.",
  "- CẤM nói \"chiều đi <giờ_khoi_hanh> và chiều về <gio_den_noi>\" — đây là BỊA. `gio_den_noi` KHÔNG phải chiều về.",
  "- Muốn nói về chiều ngược lại (vd Huế → Đà Nẵng) chỉ được nếu (B) có một row khác với `diem_di`/`diem_den` đảo chiều. Nếu không có row đó, KHẲNG ĐỊNH \"chỉ có chiều X → Y, chưa có chiều ngược lại\".",
  "- Khi (B) có nhiều route giữa cùng cặp điểm: liệt kê đầy đủ từng row (ID + giờ khởi hành + giờ đến) hoặc tóm tắt \"có N tuyến\". CẤM chỉ kể 1 row khi count ≥ 2.",
  "- Phân biệt rõ: route (lịch trình cố định) ≠ chuyến (trip, có ngày cụ thể). Nếu khách hỏi \"có nhiều chuyến không\" và (B) chỉ là kết quả `search_routes` (không phải `search_trips`): trả lời theo dữ liệu route, KHÔNG bịa ra chuyến cụ thể; gợi ý khách cho ngày để tra `search_trips`.",
].join("\n");

/** Empty-result / error-result rules — BẮT BUỘC khẳng định, không được đi clarification. */
const EMPTY_RESULT_RULES_IN_PROMPT_VI = [
  "",
  "KẾT QUẢ THAO TÁC RỖNG / KHÔNG TỒN TẠI — BẮT BUỘC KHẲNG ĐỊNH:",
  "- Khi (B) có object với `success: true` và (`count: 0` hoặc `data: []` hoặc `rows: []` hoặc `items: []`): bot PHẢI nói rõ KHÔNG CÓ / KHÔNG TÌM THẤY / CHƯA CÓ kết quả phù hợp. CẤM đi hỏi thêm slot, CẤM hứa tra cứu sau.",
  "- Khi (B) có `success: false` kèm `error` / `message` / `reason`: bot PHẢI nêu đúng lý do từ trường đó (vd 'mã vé không tồn tại', 'mật khẩu sai', 'mã voucher đã hết hạn'). CẤM lờ đi hoặc đi hỏi thêm.",
  "- Khi (B) có `past_date` hoặc `invalid_seat` hoặc `invalid_trip_id` hoặc `invalid_email_format`: bot PHẢI nêu rõ lý do không hợp lệ trước khi mời khách thử lại. CẤM giả vờ chấp nhận và tra cứu giúp.",
  "- Khi (B) có `success: false` mà không có `clarification_needed: true`: KHÔNG được hỏi thêm slot — chỉ thông báo lý do + gợi ý hành động thay thế (đổi ngày, đổi tuyến, kiểm tra lại email...).",
  "- Câu khẳng định cho B-empty nên ngắn (1-2 câu): \"Hiện không có chuyến X → Y vào ngày Z\" hoặc \"Mã voucher V không hợp lệ/đã hết hạn\". KHÔNG dài dòng, KHÔNG hỏi tiếp.",
].join("\n");

/** CẤM bot tự confirm hành động nhạy cảm khi tool chưa thực thi xong. */
const ANTI_PSEUDO_CONFIRM_RULES_IN_PROMPT_VI = [
  "",
  "CẤM TỰ CONFIRM HÀNH ĐỘNG (booking / cancel / payment / login):",
  "- CẤM nói \"Đã xác nhận đặt vé\", \"Đặt vé thành công\", \"Mã vé của bạn là...\", \"Mã đặt vé...\", \"Đã hủy vé\", \"Hủy thành công\", \"Đã thanh toán\", \"Thanh toán thành công\", \"Đã đăng nhập\", \"Đăng nhập thành công\" NẾU (B) KHÔNG có object với một trong các trường: `booking_id`, `ma_dat_ve`, `booking_confirmed: true`, `cancellation_confirmed: true`, `payment_confirmed: true`, `login_success: true`, `khach_hang` (object có id thật).",
  "- Mã vé / mã đặt vé / mã giao dịch: CHỈ trích nguyên văn từ (B). Khách nhắc tên mã trong câu hỏi KHÔNG có nghĩa là tool đã xác nhận — phải có result từ tool.",
  "- Trước khi confirm đặt vé / hủy / thanh toán: nếu (B) chưa có result hành động đó, BẮT BUỘC trả lời theo trình tự: (1) yêu cầu thông tin còn thiếu (login, mã vé, thông tin liên hệ), (2) xác nhận lại ý định của khách, (3) chỉ confirm sau khi tool thực thi xong.",
  "- Hành động cần đăng nhập (xem vé, hủy vé, đổi mật khẩu, áp voucher, đặt vé): nếu (B) cho thấy khách CHƯA đăng nhập (không có session token / user_id), reply phải MỜI đăng nhập trước. CẤM giả vờ làm thay.",
  "- Nếu khách hỏi 'xem hồ sơ / lịch sử đặt vé / loyalty' và (B) có `auth_required: true` hoặc thiếu session: nêu rõ cần đăng nhập, KHÔNG nói 'tôi chưa có khả năng' (vì hệ thống CÓ tool đó — chỉ thiếu auth).",
  "- Nếu (B) có `login_success: true` và `khach_hang.ho_va_ten`: chào tên đầy đủ + xác nhận đăng nhập thành công. CÓ THỂ tiếp tục thực hiện hành động trước đó khách yêu cầu.",
  "- Nếu (B) có `auth_failed: true`: nói rõ \"Đăng nhập thất bại — sai email hoặc mật khẩu\", mời thử lại; KHÔNG đi tra chuyến.",
  "- Nếu (B) có `invalid_email_format: true`: nói rõ email không đúng định dạng + ví dụ name@example.com.",
  "- Nếu (B) có `auth_required: true`: bot PHẢI mời đăng nhập (yêu cầu email + mật khẩu), KHÔNG nói 'tôi không có quyền', 'tôi chưa có khả năng', 'không hỗ trợ'.",
  "- Nếu (B) có `invalid_seat`: nói rõ mã ghế sai định dạng + giải thích A01–H32 chuẩn.",
  "- Nếu (B) có `invalid_trip_id`: nói rõ mã chuyến phải là số.",
  "- Nếu (B) có `past_date`: nói rõ ngày đã qua + mời chọn ngày từ hôm nay.",
].join("\n");

const CONFIRMATION_RULES_IN_PROMPT_VI = [
  "",
  "Khi trong kết quả thao tác có lỗi dạng `confirmation_required:<tool>`:",
  "- Chưa thực hiện thao tác nhạy cảm. Không nói thao tác đã thành công.",
  "- Hỏi khách xác nhận rõ ràng trước khi đặt vé, hủy vé, đổi thông tin, thanh toán hoặc thao tác nhạy cảm tương tự.",
  "- Không tự gọi lại thao tác trong câu trả lời; chỉ viết lời xác nhận cần thiết cho khách.",
].join("\n");

/**
 * Khi tool `support_create_support_session` đã mở phiên thành công, widget
 * sẽ tự subscribe websocket. Bot KHÔNG được hỏi thêm tên/SĐT vì admin trực
 * tiếp trả lời trong cùng cửa sổ chat — đó là handoff sang live chat.
 */
const SUPPORT_HANDOFF_RULES_IN_PROMPT_VI = [
  "",
  "LIVE CHAT / HANDOFF SANG HỖ TRỢ VIÊN:",
  "- Khi (B) có row `toolName: \"support_create_support_session\"` với `ok: true` và data có `public_id` (hoặc `data.public_id`): phiên live chat ĐÃ MỞ. Bot PHẢI báo: \"Đã mở phiên hỗ trợ. Đội hỗ trợ sẽ trả lời ngay trong khung chat này.\" (1–2 câu, thân thiện).",
  "- CẤM hỏi thêm tên / số điện thoại / email khi phiên đã mở — widget tự kết nối websocket với admin/nhà xe; tin tiếp theo của khách gửi thẳng cho hỗ trợ viên, chatbot không trả lời xen vào.",
  "- CẤM nói \"Tôi không thể chuyển tiếp\" / \"Vui lòng liên hệ tổng đài\" / \"Gọi hotline\" khi tool đã trả `public_id` thành công.",
  "- Nếu khách chỉ nói \"liên hệ hỗ trợ\" / \"gặp admin\" / \"chat với nhân viên\" mà tool CHƯA chạy (chưa có row `support_create_support_session` trong (B)): trấn an khách + báo đang kết nối, KHÔNG đi hỏi mục đích / SĐT.",
  "- Khi (B) có `support_create_support_session` `ok: false` (lỗi BE): xin lỗi ngắn + mời thử lại, KHÔNG gợi ý gọi điện ngoài kênh.",
].join("\n");

/**
 * Câu hỏi knowledge-base (PDF). Khi RAG retrieve trả về snippet rỗng hoặc
 * không khớp, bot phải nói rõ \"chưa có trong tài liệu\" thay vì \"không hiểu\".
 */
const KB_FALLBACK_RULES_IN_PROMPT_VI = [
  "",
  "CÂU HỎI KIẾN THỨC / TÀI LIỆU (PDF KB) — ƯU TIÊN TÓM TẮT TỪ (A):",
  "- (A) là các đoạn TRÍCH NGUYÊN VĂN từ tài liệu đã được retrieval đánh giá là phù hợp với câu hỏi. KHÔNG được coi (A) là “không liên quan” chỉ vì câu chữ không khớp 100% — retrieval đã lọc bằng vector similarity.",
  "- Khi (A) KHÔNG RỖNG: BẮT BUỘC đọc kỹ snippet trước khi trả lời. Nếu bất kỳ đoạn nào trong (A) có nhắc tới chủ đề/khái niệm/đối tượng/số liệu mà khách hỏi (kể cả nhắc một phần), PHẢI tóm tắt câu trả lời từ đoạn đó (2–4 câu, văn nói tự nhiên). Có thể trích dẫn ngắn theo cú pháp “theo tài liệu, …”.",
  "- CHỈ được dùng câu \"Hiện tài liệu chưa có thông tin về <chủ đề>. Bạn có thể cung cấp thêm ngữ cảnh hoặc hỏi vấn đề khác giúp em.\" khi (A) thực sự RỖNG **hoặc** mọi snippet trong (A) không nhắc tới chủ đề câu hỏi (không có từ khoá liên quan nào). CẤM dùng câu fallback này khi (A) có snippet chứa chính cụm từ trong câu hỏi.",
  "- CẤM bịa số liệu / tên / năm. CẤM nói \"tôi không hiểu câu hỏi\" — câu hỏi rõ ràng.",
  "- CẤM viết sai tên riêng do khách cung cấp (vd \"Trần Thị Phương Anh\" KHÔNG được viết thành \"Phương Anwers\" / \"Phương An\" / bất kỳ biến thể nào). Lấy nguyên văn từ câu hỏi khách.",
  "- KHÔNG gợi ý khách gọi hotline / email ngoài tài liệu khi chỉ là thiếu dữ liệu KB.",
].join("\n");

export function createSynthesizerNode(
  graphDependencies,
  isQuestionAnswerPdfOnly,
  useRestrictedAnswerSourcesOnly
) {
  const synthDomainLines =
    isString(graphDependencies.synthesizerDomainInstructions) &&
    graphDependencies.synthesizerDomainInstructions.trim()
      ? ["", graphDependencies.synthesizerDomainInstructions.trim(), ""]
      : [];

  /**
   * Override domain restriction "chỉ bán vé xe khách" khi đang trả lời từ PDF KB.
   * Domain instruction (vd GR45_REPLY_SURFACE_TRANSPORT_VI) khiến model nhỏ
   * (qwen2.5:3b) từ chối câu hỏi nghiên cứu / khảo sát dù snippet đã được retrieval.
   */
  const KB_DOMAIN_RESTRICTION_OVERRIDE_VI = [
    "",
    "[QUAN TRỌNG] Khi (A) — đoạn trích PDF KB — có nội dung liên quan câu hỏi của khách:",
    "- Quy tắc \"ứng dụng chỉ bán vé xe khách / xe bus\" KHÔNG áp dụng cho câu hỏi tra cứu KB này. Khách đang hỏi nội dung TÀI LIỆU đã được admin nạp, không phải hỏi mua vé.",
    "- CẤM từ chối kiểu \"tôi chỉ hỗ trợ tra cứu thông tin xe bus\" / \"không có thông tin về …\" / \"tìm thông tin tại trang web khác\" khi (A) đã chứa câu trả lời.",
    "- Trả lời TRỰC TIẾP từ (A) bằng tiếng Việt tự nhiên, 2–4 câu, có thể mở đầu bằng \"Theo tài liệu, …\".",
    "",
  ].join("\n");

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
        retrievalHit.text ? retrievalHit.text : valueOr(retrievalHit.metadata?.preview, "")
      )
      .filter(Boolean)
      .join("\n---\n")
      .slice(0, MAX_RAG_CHARS);

    const recentTranscriptText = graphState.messages
      .slice(-6)
      .map(
        (chatMessage) =>
          `${chatMessage.role}: ${textOrEmpty(chatMessage.content).slice(0, MAX_MSG_CHARS)}`
      )
      .join("\n");

    /** Banner thúc model nhỏ (qwen2.5:3b) đọc snippet thay vì copy template KB_FALLBACK / từ chối domain. */
    const kbSnippetForceUseBannerVi = retrievedSnippetText
      ? [
          "",
          "[BẮT BUỘC] Phía dưới có (A) đoạn trích từ TÀI LIỆU PDF do hệ thống retrieval đã lọc theo câu hỏi của khách.",
          "Bạn PHẢI đọc (A), tìm thông tin liên quan và TÓM TẮT lại 2–4 câu cho khách bằng tiếng Việt tự nhiên.",
          "CẤM dùng câu mẫu \"Hiện tài liệu chưa có thông tin về …\" trừ khi (A) thực sự không nhắc gì tới chủ đề câu hỏi.",
          "CẤM từ chối kiểu \"tôi chỉ hỗ trợ tra cứu thông tin xe bus\" hay \"bạn tìm thông tin tại trang web khác\" — câu này là tra cứu TÀI LIỆU KB đã nạp, không phải mua vé.",
          "",
        ].join("\n")
      : "";

    const kbDomainOverrideForUse = retrievedSnippetText
      ? KB_DOMAIN_RESTRICTION_OVERRIDE_VI
      : "";

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
        kbSnippetForceUseBannerVi,
        kbDomainOverrideForUse,
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
        NO_HALLUCINATION_RULES_IN_PROMPT_VI,
        EMPTY_RESULT_RULES_IN_PROMPT_VI,
        ANTI_PSEUDO_CONFIRM_RULES_IN_PROMPT_VI,
        CONFIRMATION_RULES_IN_PROMPT_VI,
        SUPPORT_HANDOFF_RULES_IN_PROMPT_VI,
        KB_FALLBACK_RULES_IN_PROMPT_VI,
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
        kbSnippetForceUseBannerVi,
        REPLY_MATCH_CUSTOMER_LANGUAGE_INSTRUCTION,
        ...synthDomainLines,
        kbDomainOverrideForUse,
        "",
        "You are synthesizing the final assistant reply.",
        "Use tool JSON and optional RAG snippets.",
        "Return JSON with **only** the key `reply` (string). Do **not** use `question` and `answer` keys.",
        retrievedSnippetText
          ? `(A) ĐOẠN TRÍCH TÀI LIỆU PDF (đã được retrieval lọc cho câu hỏi này — hãy đọc và tóm tắt):\n${retrievedSnippetText}\n`
          : "",
        recentToolResultsDigest !== "[]"
          ? `Tools:\n${recentToolResultsDigest}`
          : "No verified tool payloads.",
        CLARIFICATION_RULES_IN_PROMPT_VI,
        NO_HALLUCINATION_RULES_IN_PROMPT_VI,
        EMPTY_RESULT_RULES_IN_PROMPT_VI,
        ANTI_PSEUDO_CONFIRM_RULES_IN_PROMPT_VI,
        CONFIRMATION_RULES_IN_PROMPT_VI,
        SUPPORT_HANDOFF_RULES_IN_PROMPT_VI,
        KB_FALLBACK_RULES_IN_PROMPT_VI,
        "",
        recentTranscriptText,
        "",
        'Return JSON ONLY: { "reply": string }',
      ].join("\n");
    }

    let finalReplyText;

    const latestUserQuestionValue =
      [...graphState.messages]
        .reverse()
        .find((chatMessage) => chatMessage.role === "user")?.content;
    const latestUserQuestionText = textOrEmpty(latestUserQuestionValue);

    const hasNoDocumentOrToolSources =
      useRestrictedAnswerSourcesOnly &&
      !isQuestionAnswerPdfOnly &&
      !retrievedSnippetText.trim() &&
      JSON.stringify(valueOr(graphState.toolResults?.slice?.(-8), [])) === "[]";

    const deterministicToolReply =
      !isQuestionAnswerPdfOnly &&
      isFunction(graphDependencies.synthesizerReplyOverride)
        ? textOrEmpty(
            await graphDependencies.synthesizerReplyOverride({
              graphState,
              latestUserQuestionText,
              toolResults: graphState.toolResults,
              isQuestionAnswerPdfOnly,
              useRestrictedAnswerSourcesOnly,
            }),
          ).trim()
        : "";

    if (deterministicToolReply) {
      finalReplyText = deterministicToolReply;
    } else if (isQuestionAnswerPdfOnly && !retrievedSnippetText.trim()) {
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
      if ([isQuestionAnswerPdfOnly, useRestrictedAnswerSourcesOnly].some(Boolean)) {
        const allowedSourceCorpusText = `${retrievedSnippetText}\n${JSON.stringify(
          valueOr(graphState.toolResults?.slice(-8), [])
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
