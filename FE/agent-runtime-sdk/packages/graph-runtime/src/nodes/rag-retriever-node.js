import { journalEntry } from "../journal.js";
import {
  materializeChunksFromHits,
  retrieveContextForQuery,
} from "@fe-agent/rag/pipeline";
import { textOrEmpty, valueOr } from "../value.js";

export function createRagRetrieverNode(graphDependencies) {
  return async function ragRetrieverGraphNode(graphState) {
    graphDependencies.bus?.emit("stage", {
      stage: "rag_retriever",
      status: "enter",
      correlationId: graphState.correlationId,
    });

    const lastUserMessageText =
      textOrEmpty([...graphState.messages]
        .reverse()
        .find((chatMessage) => chatMessage.role === "user")?.content);

    const queryText = lastUserMessageText
      ? lastUserMessageText
      : valueOr(graphState.plan?.goal, "");
    let retrievalHits = await retrieveContextForQuery({
      embedder: graphDependencies.rag.embedder,
      vector: graphDependencies.rag.vector,
      query: queryText,
      collection: graphDependencies.rag.collection,
      signal: valueOr(graphState._signal, graphDependencies.signal),
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
