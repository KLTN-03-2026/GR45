import mitt from "mitt";

export function createRuntimeBus() {
  const emitter = mitt();
  return {
    emitter,
    onToken: (fn) => emitter.on("token", fn),
    onToolStart: (fn) => emitter.on("tool:start", fn),
    onToolEnd: (fn) => emitter.on("tool:end", fn),
    onStageChange: (fn) => emitter.on("stage", fn),
    emit: emitter.emit.bind(emitter),
  };
}
