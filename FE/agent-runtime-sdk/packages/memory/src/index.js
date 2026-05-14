import { ChatMessageSchema, SessionSnapshotSchema } from "@fe-agent/shared-zod-schemas";

export function mergeIntoSnapshot(base, outgoing) {
  return [...base, ...outgoing].map((m) => ChatMessageSchema.parse(m));
}

export function ensureSessionSnapshot(opts) {
  return SessionSnapshotSchema.parse({
    sessionId: opts.sessionId,
    updatedAt: new Date().toISOString(),
    messages: opts.messages,
    workflow: opts.workflow,
  });
}

export class SessionFacades {
  constructor(backing) {
    this.backing = backing;
  }

  get provider() {
    return this.backing;
  }

  async loadSession(sessionId) {
    return this.backing.load(sessionId);
  }

  async saveFull(snapshot) {
    SessionSnapshotSchema.parse(snapshot);
    await this.backing.save(snapshot);
  }
}
