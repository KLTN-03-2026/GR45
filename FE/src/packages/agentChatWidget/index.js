export {
  ChatWidgetAgent,
  ensureChatWidgetAgentInitialized,
  resetChatWidgetAgent,
} from '@fe-agent/gr45-fe-chat-runtime/chat-widget-agent';

export {
  createDefaultFeChatAgentRuntime,
  createFeChatAgentRuntime,
  ensureDefaultFeChatAgentRuntime,
  invokeFeChatAgent,
  resetDefaultFeChatAgentRuntime,
} from '@fe-agent/gr45-fe-chat-runtime';

export {
  LoginTool,
  RegisterTool,
  buildContextSuggestions,
  clearLogin,
  persistLogin,
} from './legacy-compat.js';
