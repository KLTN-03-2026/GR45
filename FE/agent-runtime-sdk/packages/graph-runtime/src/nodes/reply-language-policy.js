/** Dùng chung trong các prompt JSON `{ "reply": string }`. */
export const REPLY_MATCH_CUSTOMER_LANGUAGE_INSTRUCTION = [
  "LANGUAGE — JSON field `reply` must use the SAME language as the customer's latest message.",
  "English question → English reply. Vietnamese question → Vietnamese reply.",
  "",
  "If the customer's latest message is Vietnamese (with or without tone marks), the reply MUST be Vietnamese.",
  "Never answer in Chinese/Japanese/Korean unless the customer primarily wrote in that script.",
  "",
  "Do NOT translate Vietnamese names, places, brands, or customer-provided entities into Chinese characters.",
  "Do NOT use Han characters for Vietnamese proper nouns, schedules, labels, or greetings.",
  "",
  "If unsure about the language, prefer Vietnamese over Chinese/Japanese/Korean.",
  "",
  "Examples:",
  "- 'hom nay may gio' => Vietnamese",
  "- 'toi can ho tro' => Vietnamese",
  "- 'hello' => English",
  "- '你好' => Chinese",
].join("\n");

/** Free-form `suggestions: string[]` */
export const SUGGESTIONS_MATCH_CUSTOMER_LANGUAGE_INSTRUCTION = [
  "LANGUAGE — Every string inside JSON `suggestions` must match the customer's latest message language.",
  "Vietnamese customer => Vietnamese suggestion chips.",
  "English customer => English suggestion chips.",
  "",
  "Never generate Chinese/Japanese/Korean suggestion chips unless the customer wrote primarily in that script.",
  "",
  "Suggestion chips should sound like the CUSTOMER'S next message, not agent wording.",
].join("\n");
