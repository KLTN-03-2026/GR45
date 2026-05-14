import { z } from "zod";

import {
  apiFetch,
  clearKhachToken,
  getKhachBearerHeaders,
  persistKhachToken,
  withQuery,
} from "../api-helpers.js";
import { COMMON_SLOTS, EXTENDED_SLOTS } from "../slots.js";

const passthroughArgs = z.object({}).passthrough();

function specText(spec) {
  try {
    return JSON.stringify(spec, null, 2);
  } catch {
    return String(spec);
  }
}

function standardParametersSchema() {
  const properties = {};
  for (const [key, description] of Object.entries({ ...COMMON_SLOTS, ...EXTENDED_SLOTS })) {
    properties[key] = { type: "string", description };
  }
  return {
    type: "object",
    properties,
    additionalProperties: true,
  };
}

const STANDARD_PARAMETERS = standardParametersSchema();

export function createCatalogToolRegistrar(registry) {
  let activeToolContext = null;

  function register(toolName, slotSpec, tier, suggestionLabels, run) {
    registry.register({
      definition: {
        name: toolName,
        description: [
          `Tool \`${toolName}\` — GR45 khách (REST Laravel).`,
          "Spec:",
          specText(slotSpec),
        ].join("\n"),
        jsonSchema: STANDARD_PARAMETERS,
        suggestionLabels,
        tier,
      },
      argsSchema: passthroughArgs,
      execute: async (args, ctx) => {
        const previous = activeToolContext;
        activeToolContext = ctx || null;
        try {
          return await run(args ?? {}, ctx);
        } finally {
          activeToolContext = previous;
        }
      },
    });
  }

  async function jsonResult(path, opts) {
    const headers = { ...(opts.headers || {}) };
    const method = opts.method ? String(opts.method).toUpperCase() : "GET";
    if (
      method !== "GET" &&
      typeof opts.body === "string" &&
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
        return { ok: false, error: "Cần đăng nhập (Bearer token)." };
      }
      Object.assign(headers, bearer);
    }

    const res = await apiFetch(path, {
      ...opts,
      headers,
      signal: opts.signal || activeToolContext?.signal,
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
        typeof res.body?.message === "string"
          ? res.body.message
          : JSON.stringify(res.body?.errors ?? res.body ?? res.res.status),
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
    if (!Number.isInteger(id) || id <= 0) {
      return { ok: false, error: `Thiếu ${label} hợp lệ.` };
    }
    return { ok: true, id };
  }

  async function exeTramDung(args, _kind) {
    const parsed = positiveId(args.trip_id, "trip_id");
    if (!parsed.ok) return parsed;
    const id = parsed.id;
    const result = await jsonResult(`chuyen-xe/${id}/tram-dung`, {
      method: "GET",
      auth: "none",
    });
    if (!result.ok) return result;

    const payload = result.data?.data;
    const rows = Array.isArray(payload)
      ? payload
      : Array.isArray(payload?.data)
        ? payload.data
        : [];
    return {
      ok: true,
      data: {
        success: true,
        note: "Trả toàn bộ trạm — lọc đón/trả theo `loai_tram` ở FE/AgentToolController nếu cần.",
        count: rows.length,
        data: rows,
      },
    };
  }

  return {
    bridgeProxyRequired,
    clearKhachToken,
    exeTramDung,
    getKhachBearerHeaders,
    jsonResult,
    positiveId,
    register,
    stub,
    withQuery,
  };
}
