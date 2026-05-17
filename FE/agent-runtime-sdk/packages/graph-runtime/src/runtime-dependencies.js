import {
  createLangChainLlmDeps,
  createLangChainRagEmbedderDeps,
} from "@fe-agent/core/langchain-providers";

import { valueOr } from "./value.js";

function createDefaultLlm(graphDependencies) {
  return createLangChainLlmDeps({
    ollamaModel: graphDependencies.ollamaModel,
    ollamaChatModel: graphDependencies.ollamaChatModel,
    ollamaBaseUrl: graphDependencies.ollamaBaseUrl,
    ollamaKeepAlive: graphDependencies.ollamaKeepAlive,
    ollamaNumCtx: graphDependencies.ollamaNumCtx,
    groqKey: graphDependencies.groqKey,
    groqApiKey: graphDependencies.groqApiKey,
    groqModel: graphDependencies.groqModel,
    groqBaseUrl: graphDependencies.groqBaseUrl,
    fetchImpl: graphDependencies.fetchImpl,
    signal: graphDependencies.signal,
  });
}

function createDefaultRagEmbedder(graphDependencies) {
  return createLangChainRagEmbedderDeps({
    ollamaEmbedModel: graphDependencies.ollamaEmbedModel,
    ollamaBaseUrl: graphDependencies.ollamaBaseUrl,
    huggingFaceKey: graphDependencies.huggingFaceKey,
    huggingFaceApiKey: graphDependencies.huggingFaceApiKey,
    huggingFaceModel: graphDependencies.huggingFaceModel,
    huggingFaceEmbedModel: graphDependencies.huggingFaceEmbedModel,
    huggingFaceEndpointUrl: graphDependencies.huggingFaceEndpointUrl,
    huggingFaceProvider: graphDependencies.huggingFaceProvider,
    fetchImpl: graphDependencies.fetchImpl,
    signal: graphDependencies.signal,
  });
}

function createRagWithDefaultEmbedder(graphDependencies) {
  if (graphDependencies.rag == null) {
    return graphDependencies.rag;
  }
  if (graphDependencies.rag.embedder) {
    return graphDependencies.rag;
  }

  return {
    ...graphDependencies.rag,
    embedder: createDefaultRagEmbedder(graphDependencies),
  };
}

export function resolveGraphRuntimeDependencies(graphDependencies) {
  return {
    ...graphDependencies,
    llm: valueOr(graphDependencies.llm, createDefaultLlm(graphDependencies)),
    rag: createRagWithDefaultEmbedder(graphDependencies),
  };
}
