import { ToolRegistry } from "@fe-agent/tools";

import { registerGr45Tools } from "../../catalog/tools/index.js";

function useProvidedValue(value, defaultValue) {
  return value == null ? defaultValue : value;
}

function createToolRegistryOptions(opts) {
  return {
    defaultToolTimeoutMs: useProvidedValue(opts.defaultToolTimeoutMs, 60_000),
    requireSensitiveToolConfirmation: useProvidedValue(
      opts.requireSensitiveToolConfirmation,
      false,
    ),
  };
}

export function createDefaultToolRegistry(opts = {}) {
  const tools = new ToolRegistry(createToolRegistryOptions(opts));
  registerGr45Tools(tools);
  if (opts.registerTools != null) {
    opts.registerTools(tools);
  }
  return tools;
}
