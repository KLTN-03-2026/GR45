import { chunkText, extractPdfTextWithPdfJs } from "./chunk.js";
import { extractPdfTextFromBuffer } from "./pdf-node.js";

async function embedPiecesAndUpsert(input) {
  const docId = input.docId;
  const pieces = input.pieces;
  const batchSize = input.embedBatchSize ?? 12;
  const previewChars = input.previewChars ?? 512;
  const embeddingModelLabel =
    typeof input.embeddingModel === "string" && input.embeddingModel.trim() !== ""
      ? input.embeddingModel.trim().slice(0, 512)
      : "";

  for (let i = 0; i < pieces.length; i += batchSize) {
    const batch = pieces.slice(i, i + batchSize);
    const embeddings = await input.embedder.embedTexts({
      texts: batch.map((b) => b.text),
    });
    await input.vector.upsert({
      collection: input.collection,
      items: batch.map((b, idx) => ({
        id: b.id,
        vector: embeddings[idx],
        metadata: {
          docId,
          sessionId: input.sessionId,
          chunkIndex: b.index,
          correlationId: input.correlationId,
          preview: b.text.slice(0, Math.min(b.text.length, previewChars)),
          /** Lưu DB đầy đủ (`ai_chunks.content`) — client upsert không nhận thêm POST riêng. */
          chunk_content: b.text,
          original_filename: input.documentFilename
            ? String(input.documentFilename)
            : "",
          ...(embeddingModelLabel !== ""
            ? { embedding_model: embeddingModelLabel }
            : {}),
        },
      })),
    });
  }

  if (input.sessions) {
    const prev = await input.sessions.load(input.sessionId);
    const wf = prev.workflow ?? {};
    const prevDocs = Array.isArray(wf.ragDocs) ? wf.ragDocs : [];
    await input.sessions.save({
      ...prev,
      sessionId: input.sessionId,
      updatedAt: new Date().toISOString(),
      workflow: { ...wf, ragDocs: [...prevDocs, docId] },
    });
  }

  return { docId, chunks: pieces.length };
}

export async function ingestPdfToVectorCollection(input) {
  const docId = input.docId ?? crypto.randomUUID();

  const text = await extractPdfTextWithPdfJs(input.file, input.pdfjsWorkerSrc);
  const pieces = chunkText(
    { text, docId },
    { chunkSize: input.chunkSize, overlap: input.overlap }
  );

  return embedPiecesAndUpsert({
    ...input,
    docId,
    pieces,
    embedBatchSize: input.embedBatchSize,
    previewChars: 512,
  });
}

// Nạp PDF từ buffer (Node) qua pdf-parse, chunk, embed rồi upsert.
export async function ingestPdfBufferToVectorCollection(input) {
  const docId =
    typeof input.docId === "string" && input.docId.trim()
      ? input.docId.trim()
      : crypto.randomUUID();

  const text = await extractPdfTextFromBuffer(input.pdfBuffer);
  input.onParsed?.({ charCount: text.length });
  const pieces = chunkText(
    { text, docId },
    {
      chunkSize: input.chunkSize ?? 960,
      overlap: input.overlap ?? 180,
    }
  );

  const totalPieces = pieces.length;
  let toEmbed = pieces;
  const maxChunks = input.maxChunks;
  if (typeof maxChunks === "number" && maxChunks > 0 && pieces.length > maxChunks) {
    toEmbed = pieces.slice(0, maxChunks);
  }

  await embedPiecesAndUpsert({
    ...input,
    docId,
    pieces: toEmbed,
    embedBatchSize: input.embedBatchSize,
    previewChars: input.previewChars ?? 900,
  });

  return {
    docId,
    chunksEmbedded: toEmbed.length,
    chunksTotal: totalPieces,
  };
}

export async function retrieveContextForQuery(input) {
  let vector = await input.embedder.embedQuery?.({
    text: input.query,
  });

  if (!vector?.length) {
    const batched = await input.embedder.embedTexts({
      texts: [input.query],
    });
    vector = batched[0] ?? [];
  }

  const hits = await input.vector.query({
    collection: input.collection,
    vector,
    topK: input.topK ?? 8,
    signal: input.signal,
  });

  return hits.map((hit) => ({
    id: hit.id,
    text: "",
    score: hit.score,
    source: typeof hit.metadata?.source === "string" ? hit.metadata.source : undefined,
    metadata: hit.metadata,
  }));
}

export function materializeChunksFromHits(hits, metaTextKey = "preview") {
  return hits.map((hit) => {
    const txt =
      typeof hit.metadata?.[metaTextKey] === "string"
        ? String(hit.metadata[metaTextKey])
        : hit.text ?? "";
    return { ...hit, text: txt };
  });
}
