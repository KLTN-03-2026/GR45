import { unwrapSyntheticReplyEnvelope } from "@fe-agent/core/model-json";
import { REPLY_MATCH_CUSTOMER_LANGUAGE_INSTRUCTION } from "../reply/reply-language-policy.js";
import { textOrEmpty, valueOr } from "../value.js";

const MAX_CORPUS = 6000;
const MAX_DRAFT = 2500;
const MAX_QUESTION = 800;

const FORBIDDEN_META_RE =
  /\b(gpt|llm|openai|ai model|language model|chatbot|prompt|embedding|api)\b/i;

const CONTACT_RE =
  /(?:https?:\/\/\S+)|(?:www\.\S+)|(?:\+?\d[\d\s.-]{7,})|(?:[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-z]{2,})/gi;

function compact(value, max) {
  return textOrEmpty(value)
    .replace(/\s+/g, " ")
    .trim()
    .slice(0, max);
}

function extractAllowedContacts(corpus) {
  const found = textOrEmpty(corpus).match(CONTACT_RE);
  return [...new Set(valueOr(found, []))];
}

function stripUnknownContacts(reply, allowedContacts) {
  return textOrEmpty(reply).replace(CONTACT_RE, (match) => {
    return allowedContacts.includes(match) ? match : "";
  });
}

function hardSanitizeReply(reply, allowedCorpus) {
  const allowedContacts = extractAllowedContacts(allowedCorpus);

  let out = textOrEmpty(reply);

  out = stripUnknownContacts(out, allowedContacts);

  out = out.replace(FORBIDDEN_META_RE, "");

  out = out.replace(/\s+/g, " ").trim();

  return out;
}

export async function composeRestrictedPolicyRefusalWithLanguageModel({
  languageModel,
  refusalReasonExplanation,
  latestUserQuestionText,
  allowedCorpusExcerptPreview,
}) {
  const prompt = [
    REPLY_MATCH_CUSTOMER_LANGUAGE_INSTRUCTION,
    "",
    'Return JSON only: {"reply": string}',
    "",
    "Write a short polite refusal.",
    "- Do not invent contacts.",
    "- Do not mention AI/system/policy/internal rules.",
    "- Keep under 2 sentences.",
    "",
    `Reason: ${compact(refusalReasonExplanation, 500)}`,
    "",
    `User question: ${compact(latestUserQuestionText, MAX_QUESTION)}`,
    "",
    `Allowed context: ${compact(allowedCorpusExcerptPreview, 1200)}`,
  ].join("\n");

  try {
    const raw = await languageModel.completeJson(prompt);

    const reply = unwrapSyntheticReplyEnvelope(raw);

    const sanitized = hardSanitizeReply(
      reply,
      allowedCorpusExcerptPreview,
    );

    if (sanitized.length >= 5) {
      return sanitized;
    }
  } catch {}

  return "Hiện tại mình chưa có đủ thông tin phù hợp để trả lời chính xác câu hỏi này.";
}

export async function enforceRestrictedReplyComplianceWithLanguageModel(
  languageModel,
  draftReplyText,
  allowedSourceCorpusText,
  latestUserQuestionText,
) {
  const draft = compact(draftReplyText, MAX_DRAFT);

  const corpus = compact(allowedSourceCorpusText, MAX_CORPUS);

  const question = compact(latestUserQuestionText, MAX_QUESTION);

  if (!draft) {
    return composeRestrictedPolicyRefusalWithLanguageModel({
      languageModel,
      refusalReasonExplanation: "Empty draft reply.",
      latestUserQuestionText: question,
      allowedCorpusExcerptPreview: corpus,
    });
  }

  const locallySanitized = hardSanitizeReply(draft, corpus);

  const clarificationNeeded =
    corpus.includes('"clarification_needed": true');

  const prompt = [
    REPLY_MATCH_CUSTOMER_LANGUAGE_INSTRUCTION,
    "",
    'Return JSON only: {"reply": string}',
    "",
    "Review and minimally rewrite the draft reply.",
    "",
    "Rules:",
    "- Keep meaning unchanged.",
    "- Remove hallucinated contacts.",
    "- Remove AI/system/meta wording.",
    "- Only use facts from corpus.",
    clarificationNeeded
      ? "- If clarification_needed=true: only ask user for missing info."
      : "",
    "",
    `User question:\n${question}`,
    "",
    `Corpus:\n${corpus}`,
    "",
    `Draft:\n${locallySanitized}`,
  ].join("\n");

  try {
    const raw = await languageModel.completeJson(prompt);

    const reviewed = unwrapSyntheticReplyEnvelope(raw);

    const finalReply = hardSanitizeReply(reviewed, corpus);

    if (finalReply) {
      return finalReply;
    }
  } catch {}

  return locallySanitized;
}
