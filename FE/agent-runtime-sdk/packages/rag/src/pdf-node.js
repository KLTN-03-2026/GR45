const DEFAULT_MAX_PDF_BYTES = 80 * 1024 * 1024;

function toBuffer(pdfBuffer) {
  if (!pdfBuffer) {
    throw new Error("pdfBuffer is required.");
  }

  if (Buffer.isBuffer(pdfBuffer)) return pdfBuffer;

  if (pdfBuffer instanceof Uint8Array) {
    return Buffer.from(
      pdfBuffer.buffer,
      pdfBuffer.byteOffset,
      pdfBuffer.byteLength,
    );
  }

  throw new Error("pdfBuffer must be Buffer or Uint8Array.");
}

function normalizePdfText(value) {
  let text = String(value ?? "")
    .replace(/\u0000/g, " ")
    .replace(/[\t\f\v]+/g, " ")
    .replace(/[ ]{2,}/g, " ")
    .replace(/\n{3,}/g, "\n\n")
    .trim();

  try {
    text = text.normalize("NFKC");
  } catch {}

  return text;
}

export async function extractPdfTextFromBuffer(pdfBuffer, options = {}) {
  const buf = toBuffer(pdfBuffer);
  const maxBytes = options.maxBytes ?? DEFAULT_MAX_PDF_BYTES;

  if (buf.byteLength > maxBytes) {
    throw new Error(`PDF too large: ${buf.byteLength} bytes.`);
  }

  const pdfParseModule = await import("pdf-parse");

  let parsed;

  if (typeof pdfParseModule.default === "function") {
    parsed = await pdfParseModule.default(buf);
  } else if (typeof pdfParseModule.PDFParse === "function") {
    const parser = new pdfParseModule.PDFParse({ data: buf });

    try {
      parsed = await parser.getText();
    } finally {
      await parser.destroy?.();
    }
  } else {
    throw new Error("Unsupported pdf-parse module shape.");
  }

  return normalizePdfText(parsed?.text ?? parsed);
}