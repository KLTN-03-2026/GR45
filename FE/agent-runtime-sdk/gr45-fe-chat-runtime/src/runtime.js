import {
  createBrowserAgentRuntime,
} from "@fe-agent/browser-runtime/runtime";
import {
  createInitialAgentState,
} from "@fe-agent/graph-runtime";
import { createRuntimeBus } from "@fe-agent/streaming";

import {
  collectLiveSupportDeferredOpeningFromToolResults,
  collectLiveSupportPublicIdsFromToolResults,
} from "./domain/planner/planner-policy.js";
import { GR45_DOMAIN_POLICY } from "./domain/gr45-domain-policy.js";
import { deriveRuntimeOutcome } from "./domain/outcome/runtime-outcome.js";
import { summarizeToolResults } from "./domain/outcome/tool-summary.js";
import { createDefaultToolRegistry } from "./runtime/providers/tool-registry.js";
import { deriveGr45ToolSuggestions } from "./catalog/tools/index.js";
import { enhanceGr45TripSearchArgumentsRuleOnly } from "./catalog/slots/trip-search-slot-extractor.js";

let defaultRuntime = null;

const VALID_ROLES = new Set(["user", "assistant", "tool", "system"]);

function normalizeRole(raw) {
  const r = String(raw == null ? "" : raw).trim();
  return VALID_ROLES.has(r) ? r : "user";
}

function useProvidedValue(value, defaultValue) {
  return value == null ? defaultValue : value;
}

function textFromToolData(data) {
  const direct = [
    data?.message,
    data?.error,
    data?.data?.message,
    data?.data?.error,
    data?.data?.suggested_questions_vi?.[0],
    data?.suggested_questions_vi?.[0],
  ]
    .map((value) => String(value == null ? "" : value).trim())
    .find((value) => value.length > 0);

  if (direct) return direct;
  return "";
}

function createDeterministicToolReply({ toolResults }) {
  const rows = Array.isArray(toolResults) ? toolResults : [];
  if (rows.length !== 1) return "";

  const row = rows[0];
  const text = textFromToolData(row?.data);
  if (text) return text;
  const err = String(row?.error ?? "").trim();
  if (err && !err.startsWith("confirmation_required:")) return err;

  const simpleToolNames = new Set([
    "auth_login",
    "auth_logout",
    "auth_forgot_password",
    "auth_reset_password",
    "auth_activate_account",
    "profile_update",
    "password_change",
    "booking_cancel_booking",
    "ticket_cancel_ticket",
    "support_send_message",
  ]);
  if (row?.ok === true && simpleToolNames.has(row?.toolName)) {
    return "Thao tác đã hoàn tất.";
  }

  return "";
}

export function createFeChatAgentRuntime(opts = {}) {
  const collectLiveSupportIds = useProvidedValue(
    opts.collectLiveSupportPublicIds,
    collectLiveSupportPublicIdsFromToolResults,
  );
  const tools = useProvidedValue(opts.tools, createDefaultToolRegistry(opts));
  const bus = useProvidedValue(opts.bus, createRuntimeBus());
  const { compiledGraph, sessions } = createBrowserAgentRuntime({
    options: {
      ...opts,
      bus,
      synthesizerReplyOverride: useProvidedValue(
        opts.synthesizerReplyOverride,
        createDeterministicToolReply,
      ),
    },
    env: import.meta.env,
    locationOrigin: globalThis.window?.location?.origin ? globalThis.window.location.origin : "",
    tools,
    domainPolicy: GR45_DOMAIN_POLICY,
    prompt: opts.synthesizerDomainInstructions,
    enhanceToolCallArguments: useProvidedValue(
      opts.enhanceToolCallArguments,
      enhanceGr45TripSearchArgumentsRuleOnly,
    ),
  });

  async function invoke(message, invokeOpts = null, signal = undefined) {
    const text = String(message == null ? "" : message).trim();
    if (!text) {
      throw new Error("Thiếu nội dung tin nhắn.");
    }

    const requestOptions = invokeOpts == null ? {} : invokeOpts;
    const stopTokenStream =
      requestOptions.onToken == null
        ? null
        : bus.onToken(requestOptions.onToken);
    const history =
      Array.isArray(requestOptions.history)
        ? requestOptions.history
        : [];
    const requestSessionId = String(
      requestOptions.session_id == null ? "" : requestOptions.session_id,
    ).trim();
    const sessionId =
      requestSessionId
        ? requestSessionId.slice(0, 64)
        : `fe-${crypto.randomUUID()}`;

    const prior = history.slice(-20).map((row) => ({
      id: crypto.randomUUID(),
      role: normalizeRole(row?.role),
      content: String(row?.content == null ? "" : row.content),
    }));

    const correlationId = crypto.randomUUID();

    let out;
    try {
      out = await compiledGraph.invoke(
        createInitialAgentState({
          sessionId,
          correlationId,
          messages: [
            ...prior,
            {
              id: crypto.randomUUID(),
              role: "user",
              content: text,
            },
          ],
          _signal: signal,
        }),
        // Use correlationId as thread_id so each invocation starts from a clean checkpoint.
        { configurable: { thread_id: correlationId } },
      );
    } finally {
      if (stopTokenStream) stopTokenStream();
    }

    const answer = String(
      out?.finalAnswer == null
        ? ""
        : out.finalAnswer,
    ).trim();
    const toolResults = Array.isArray(out?.toolResults) ? out.toolResults : [];
    const liveSupportPublicIds = collectLiveSupportIds(toolResults);
    const liveSupportDeferredOpening =
      collectLiveSupportDeferredOpeningFromToolResults(toolResults);
    const ragContext = Array.isArray(out?.ragContext) ? out.ragContext : [];
    const outcome = deriveRuntimeOutcome({
      toolResults,
      ragContext,
      answerText: answer,
    });
    return {
      success: true,
      session_id: sessionId,
      assistant: JSON.stringify({
        answer: answer
          ? answer
          : "(Trống) — kiểm tra provider LLM, embedding/RAG, và kết nối API.",
        suggestions: deriveGr45ToolSuggestions(toolResults),
      }),
      metadata: {
        ai: {
          runtime: "fe_agent",
          outcome,
          planner_loops_signal: out?.signals?.planner_loop,
          rag_hits: ragContext.length,
          tool_summary: summarizeToolResults(toolResults),
          ...(liveSupportPublicIds.length
            ? { live_support_public_ids: liveSupportPublicIds }
            : {}),
          ...(liveSupportDeferredOpening
            ? { live_support_deferred_opening: true }
            : {}),
        },
      },
    };
  }

  return { invoke, tools, sessions, compiledGraph, bus };
}

export function createDefaultFeChatAgentRuntime(opts = {}) {
  return createFeChatAgentRuntime(opts);
}

export function ensureDefaultFeChatAgentRuntime() {
  if (!defaultRuntime) {
    defaultRuntime = createDefaultFeChatAgentRuntime();
  }
  return defaultRuntime;
}

export function resetDefaultFeChatAgentRuntime() {
  defaultRuntime = null;
}

export async function invokeFeChatAgent(
  message,
  opts = null,
  signal = undefined,
) {
  return ensureDefaultFeChatAgentRuntime().invoke(message, opts, signal);
}
