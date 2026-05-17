import { z } from "zod";

import {
  apiFetch,
  clearKhachToken,
  getKhachBearerHeaders,
  persistKhachToken,
  withQuery,
} from "../../tools/api/api-client.js";
import {
  buildCompactToolDescription,
  buildToolJsonSchemaFromSlots,
} from "../../tools/slots/common-slots.js";

const passthroughArgs = z.object({}).passthrough();

export function createCatalogToolRegistrar(registry) {
  let activeToolContext = null;
  const plannerPatterns = [];
  /** @type {Map<string, (result: any) => Array<{text:string,action?:string,params?:object}>>} */
  const toolSuggestions = new Map();

  function getFastPlannerPatterns() {
    return plannerPatterns.slice();
  }

  function getToolSuggestionsMap() {
    return new Map(toolSuggestions);
  }

  function register(toolName, slotSpec, tier, labels, run, plannerPattern) {
    if (Array.isArray(plannerPattern)) {
      plannerPatterns.push(...plannerPattern);
      for (const p of plannerPattern) {
        if (p && typeof p.suggestions === "function") {
          toolSuggestions.set(toolName, p.suggestions);
        }
      }
    } else if (plannerPattern) {
      plannerPatterns.push(plannerPattern);
      if (typeof plannerPattern.suggestions === "function") {
        toolSuggestions.set(toolName, plannerPattern.suggestions);
      }
    }

    registry.register({
      definition: {
        name: toolName,
        description: buildCompactToolDescription(toolName, slotSpec),
        jsonSchema: buildToolJsonSchemaFromSlots(slotSpec),
        tier,
        labels,
      },
      argsSchema: passthroughArgs,
      execute: async (args, ctx) => {
        const previous = activeToolContext;
        activeToolContext = ctx ? ctx : null;
        try {
          return await run(args == null ? {} : args, ctx);
        } finally {
          activeToolContext = previous;
        }
      },
    });
  }

  async function jsonResult(path, opts) {
    const headers = { ...(opts.headers ? opts.headers : {}) };
    const method = opts.method ? String(opts.method).toUpperCase() : "GET";
    if (
      method !== "GET" &&
      opts.body?.constructor === String &&
      opts.body !== "" &&
      !headers["Content-Type"] &&
      !headers["content-type"]
    ) {
      headers["Content-Type"] = "application/json";
    }
    if (opts.auth === "optional") {
      Object.assign(headers, getKhachBearerHeaders());
    } else if (opts.auth === "bearer") {
      const bearer = getKhachBearerHeaders();
      if (!bearer.Authorization) {
        return {
          ok: false,
          data: {
            success: false,
            auth_required: true,
            error: "Bạn cần đăng nhập trước khi xem/quản lý tài khoản hoặc vé.",
          },
          error: "auth_required: Bearer token missing — khách chưa đăng nhập.",
        };
      }
      Object.assign(headers, bearer);
    }

    const res = await apiFetch(path, {
      ...opts,
      headers,
      signal: opts.signal ? opts.signal : activeToolContext?.signal,
    });
    if (opts.persistToken && res.res.ok && res.body?.success === true) {
      persistKhachToken(res.body);
    }
    if (res.res.ok && res.body?.success === true) {
      return { ok: true, data: res.body };
    }
    return {
      ok: false,
      error:
        res.body?.message?.constructor === String
          ? res.body.message
          : JSON.stringify(
              res.body?.errors == null
                ? res.body == null
                  ? res.res.status
                  : res.body
                : res.body.errors,
            ),
    };
  }

  async function stub(name, args) {
    return {
      ok: true,
      data: {
        tool: name,
        not_implemented: true,
        args_echo: args,
        hint: "Chưa có route phù hợp hoặc cần AgentToolController / UI.",
      },
    };
  }

  function bridgeProxyRequired() {
    return {
      ok: false,
      error:
        "Live support bridge tools are disabled in the browser runtime. Expose a backend proxy authenticated by the current user instead of shipping a bridge secret to Vite.",
    };
  }

  function positiveId(value, label) {
    const id = Number(value);
    if ([!Number.isInteger(id), id <= 0].some(Boolean)) {
      return { ok: false, error: `Thiếu ${label} hợp lệ.` };
    }
    return { ok: true, id };
  }

  async function exeTramDung(args, kind) {
    const parsed = positiveId(args.trip_id, "trip_id");
    if (!parsed.ok) return parsed;
    const id = parsed.id;
    const result = await jsonResult(`chuyen-xe/${id}/tram-dung`, {
      method: "GET",
      auth: "none",
    });
    if (!result.ok) return result;

    const payload = result.data?.data;
    const rows = kind === "pickup" && Array.isArray(payload?.tram_don)
      ? payload.tram_don
      : kind === "dropoff" && Array.isArray(payload?.tram_tra)
        ? payload.tram_tra
        : Array.isArray(payload)
      ? payload
      : Array.isArray(payload?.data)
        ? payload.data
        : [];
    return {
      ok: true,
      data: {
        success: true,
        note: kind === "pickup" ? "Danh sách điểm đón." : "Danh sách điểm trả.",
        count: rows.length,
        data: rows,
      },
    };
  }

  return {
    bridgeProxyRequired,
    clearKhachToken,
    exeTramDung,
    getFastPlannerPatterns,
    getKhachBearerHeaders,
    getToolSuggestionsMap,
    jsonResult,
    positiveId,
    register,
    stub,
    withQuery,
  };
}
