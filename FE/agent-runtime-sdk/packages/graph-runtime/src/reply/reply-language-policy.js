/** Dùng chung trong các prompt JSON `{ "reply": string }`. */
export const REPLY_MATCH_CUSTOMER_LANGUAGE_INSTRUCTION = [
  "Reply in the customer's latest message language.",
  "Vietnamese, including no-tone Vietnamese, must be answered in Vietnamese.",
  "Do not use Chinese/Japanese/Korean unless the customer mainly used that script.",
  "Keep names, places, brands, and user-provided entities unchanged.",
].join("\n");
