import { compileAgentGraph } from "@fe-agent/graph-runtime";
import { createRuntimeBus } from "@fe-agent/streaming";

import { resolveBrowserAgentRuntimeConfig } from "../config/browser-runtime-config.js";
import { createBrowserAgentProviders } from "../providers/browser-agent-providers.js";

function hasRuntimeValue(value) {
  return value !== undefined && value !== null && value !== "";
}

function firstRuntimeValue(...values) {
  for (const value of values) {
    if (hasRuntimeValue(value)) return value;
  }
  return undefined;
}

function useProvidedValue(value, defaultValue) {
  return value == null ? defaultValue : value;
}

function createResolvedDomainRuntimeOptions({
  options,
  prompt,
  domainPolicy,
  enhanceToolCallArguments,
}) {
  return {
    domainInstructions: options.domainInstructions,
    synthesizerDomainInstructions: firstRuntimeValue(
      options.synthesizerDomainInstructions,
      prompt,
      domainPolicy.synthesizerDomainInstructions,
    ),
    synthesizerReplyOverride: options.synthesizerReplyOverride,
    intentClassifierOptions: useProvidedValue(
      options.intentClassifierOptions,
      domainPolicy.intentClassifierOptions,
    ),
    planPostProcessor: useProvidedValue(
      options.planPostProcessor,
      domainPolicy.planPostProcessor,
    ),
    prePlannerHook: useProvidedValue(
      options.prePlannerHook,
      domainPolicy.prePlannerHook,
    ),
    enhanceToolCallArguments: useProvidedValue(
      options.enhanceToolCallArguments,
      enhanceToolCallArguments,
    ),
  };
}

function createLlmGraphDependencies(options, config, fetchImpl) {
  return {
    llm: options.llm,
    ollamaModel: config.ollamaModel,
    ollamaEmbedModel: config.ollamaEmbedModel,
    ollamaBaseUrl: config.ollamaBaseUrl,
    ollamaKeepAlive: config.ollamaKeepAlive,
    ollamaNumCtx: config.ollamaNumCtx,
    groqKey: config.groqKey,
    groqModel: config.groqModel,
    groqBaseUrl: config.groqBaseUrl,
    huggingFaceKey: config.huggingFaceKey,
    huggingFaceModel: config.huggingFaceModel,
    huggingFaceEndpointUrl: config.huggingFaceEndpointUrl,
    huggingFaceProvider: config.huggingFaceProvider,
    fetchImpl,
  };
}

function createDomainGraphDependencies({
  domainOptions,
}) {
  return {
    domainInstructions: domainOptions.domainInstructions,
    synthesizerDomainInstructions: domainOptions.synthesizerDomainInstructions,
    synthesizerReplyOverride: domainOptions.synthesizerReplyOverride,
    intentClassifierOptions: domainOptions.intentClassifierOptions,
    planPostProcessor: domainOptions.planPostProcessor,
    prePlannerHook: domainOptions.prePlannerHook,
    enhanceToolCallArguments: domainOptions.enhanceToolCallArguments,
  };
}

function createRagGraphDependencies(options, config, vector) {
  return {
    rag: {
      collection: useProvidedValue(options.collection, config.collection),
      embedder: options.embedder,
      vector,
    },
    qaPdfOnly: options.qaPdfOnly,
    restrictedAnswerSources: options.restrictedAnswerSources,
  };
}

function createGraphDependencies({
  options,
  config,
  fetchImpl,
  sessions,
  tools,
  vector,
  prompt,
  domainPolicy,
  enhanceToolCallArguments,
}) {
  return {
    ...createLlmGraphDependencies(options, config, fetchImpl),
    ...createDomainGraphDependencies({
      domainOptions: createResolvedDomainRuntimeOptions({
        options,
        prompt,
        domainPolicy,
        enhanceToolCallArguments,
      }),
    }),
    ...createRagGraphDependencies(options, config, vector),
    tools,
    bus: useProvidedValue(options.bus, createRuntimeBus()),
    sessions,
    checkpointer: false,
    confirmToolCall: options.confirmToolCall,
    config: useProvidedValue(options.config, {}),
  };
}

export function createBrowserAgentRuntime({
  options = {},
  env = {},
  locationOrigin = "",
  tools,
  prompt,
  domainPolicy = {},
  enhanceToolCallArguments,
  tokenStorageKey = "auth.client.token",
} = {}) {
  const config = resolveBrowserAgentRuntimeConfig({
    env,
    locationOrigin,
    defaults: options,
  });
  const providers = createBrowserAgentProviders({
    options,
    config,
    tokenStorageKey,
  });
  const compiledGraph = compileAgentGraph(
    createGraphDependencies({
      options,
      config,
      tools,
      prompt,
      domainPolicy,
      enhanceToolCallArguments,
      ...providers,
    }),
  );

  return {
    config,
    compiledGraph,
    ...providers,
  };
}
