import {
  createDefaultFeChatAgentRuntime,
  ensureDefaultFeChatAgentRuntime,
  resetDefaultFeChatAgentRuntime,
} from './runtime.js';

let defaultChatWidgetAgent = null;

export class ChatWidgetAgent {
  constructor(runtime = null) {
    this.runtime = runtime == null ? ensureDefaultFeChatAgentRuntime() : runtime;
  }

  async handleMessage(message, options = {}, signal = undefined) {
    const requestOptions = options == null ? {} : options;
    return this.runtime.invoke(message, {
      ...requestOptions,
      history: Array.isArray(requestOptions.history) ? requestOptions.history : [],
    }, signal);
  }
}

export function ensureChatWidgetAgentInitialized(options = {}) {
  if (!defaultChatWidgetAgent) {
    defaultChatWidgetAgent = new ChatWidgetAgent(
      createDefaultFeChatAgentRuntime(options),
    );
  }
  return defaultChatWidgetAgent;
}

export function resetChatWidgetAgent() {
  defaultChatWidgetAgent = null;
  resetDefaultFeChatAgentRuntime();
}
