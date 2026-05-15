import { journalEntry } from "@fe-agent/observability";
import {
  materializeChunksFromHits,
  retrieveContextForQuery,
} from "@fe-agent/rag";

/** @param graphDependencies */
export function createRagRetrieverNode(graphDependencies) {
  return async function ragRetrieverGraphNode(graphState) {
    graphDependencies.bus?.emit("stage", {
      stage: "rag_retriever",
      status: "enter",
      correlationId: graphState.correlationId,
    });

    const lastUserMessageText =
      [...graphState.messages]
        .reverse()
        .find((chatMessage) => chatMessage.role === "user")?.content ?? "";

    let retrievalHits = await retrieveContextForQuery({
      embedder: graphDependencies.rag.embedder,
      vector: graphDependencies.rag.vector,
      query: lastUserMessageText || graphState.plan?.goal || "",
      collection: graphDependencies.rag.collection,
      signal: graphState._signal ?? graphDependencies.signal,
    });

    retrievalHits = materializeChunksFromHits(retrievalHits);

    return {
      ragContext: retrievalHits,
      signals: {
        ...graphState.signals,
        rag_fallback: false,
      },
      journal: [journalEntry("rag_retriever", { hits: retrievalHits.length })],
    };
  };
}
