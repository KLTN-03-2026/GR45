const DEFAULT_CHUNK_SIZE = 800;
const DEFAULT_OVERLAP = 120;
const DEFAULT_MAX_PDF_PAGES = 300;

function normalizeText(text) {
  return String(text ?? "")
    .replace(/\r\n/g, "\n")
    .replace(/[ \t]+/g, " ")
    .replace(/\n{3,}/g, "\n\n")
    .trim();
}

function chooseSoftEnd(text, start, hardEnd, minEnd) {
  const window = text.slice(start, hardEnd);
  const breakpoints = ["\n\n", "\n", ". ", "? ", "! ", "; ", ", "];

  for (const bp of breakpoints) {
    const idx = window.lastIndexOf(bp);
    if (idx >= 0 && start + idx + bp.length >= minEnd) {
      return start + idx + bp.length;
    }
  }

  return hardEnd;
}

export function chunkText(source, options = {}) {
  const chunkSize = options.chunkSize ?? DEFAULT_CHUNK_SIZE;
  const overlap = options.overlap ?? DEFAULT_OVERLAP;

  if (!source || typeof source !== "object") {
    throw new Error("source must be an object");
  }

  if (!source.docId) {
    throw new Error("source.docId is required");
  }

  if (!Number.isInteger(chunkSize) || chunkSize <= 0) {
    throw new Error("chunkSize must be a positive integer");
  }

  if (!Number.isInteger(overlap) || overlap < 0 || overlap >= chunkSize) {
    throw new Error("overlap must be a non-negative integer smaller than chunkSize");
  }

  const text = normalizeText(source.text);

  if (!text) return [];

  if (text.length <= chunkSize) {
    return [
      {
        id: `${source.docId}#0`,
        docId: source.docId,
        index: 0,
        text,
        start: 0,
        end: text.length,
      },
    ];
  }

  const chunks = [];
  let cursor = 0;
  let index = 0;

  while (cursor < text.length) {
    const hardEnd = Math.min(cursor + chunkSize, text.length);
    const minEnd = Math.min(cursor + Math.floor(chunkSize * 0.55), hardEnd);
    const end =
      hardEnd >= text.length
        ? hardEnd
        : chooseSoftEnd(text, cursor, hardEnd, minEnd);

    const slice = text.slice(cursor, end).trim();

    if (slice) {
      chunks.push({
        id: `${source.docId}#${index}`,
        docId: source.docId,
        index,
        text: slice,
        start: cursor,
        end,
      });
      index += 1;
    }

    if (end >= text.length) break;

    cursor = Math.max(end - overlap, cursor + 1);
  }

  return chunks;
}

export async function extractPdfTextWithPdfJs(source, pdfjsWorkerSrc, options = {}) {
  let pdfjsMod;

  try {
    pdfjsMod = await import("pdfjs-dist");
  } catch {
    throw new Error(
      "[@fe-agent/rag] Install optional dependency pdfjs-dist and set workerSrc where needed",
    );
  }

  const { getDocument, GlobalWorkerOptions } = pdfjsMod;

  if (pdfjsWorkerSrc && GlobalWorkerOptions) {
    GlobalWorkerOptions.workerSrc = pdfjsWorkerSrc;
  }

  const data = source instanceof Blob ? await source.arrayBuffer() : source;
  const doc = await getDocument({ data }).promise;
  const maxPages = options.maxPages ?? DEFAULT_MAX_PDF_PAGES;

  try {
    const pages = Math.min(doc.numPages, maxPages);
    const parts = [];

    for (let pageNumber = 1; pageNumber <= pages; pageNumber += 1) {
      const page = await doc.getPage(pageNumber);
      const content = await page.getTextContent();

      const pageText = content.items
        .map((item) =>
          item && typeof item === "object" && "str" in item
            ? String(item.str)
            : "",
        )
        .join(" ")
        .replace(/\s+/g, " ")
        .trim();

      if (pageText) {
        parts.push(`--- page ${pageNumber} ---\n${pageText}`);
      }
    }

    return parts.join("\n\n").trim();
  } finally {
    await doc.destroy();
  }
}