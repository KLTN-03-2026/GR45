import mitt from "mitt";

export function createRuntimeBus() {
  const emitter = mitt();
  return {
    emitter,
    onToken: (fn) => {
      emitter.on("token", fn);
      return () => emitter.off("token", fn);
    },
    onToolStart: (fn) => {
      emitter.on("tool:start", fn);
      return () => emitter.off("tool:start", fn);
    },
    onToolEnd: (fn) => {
      emitter.on("tool:end", fn);
      return () => emitter.off("tool:end", fn);
    },
    onStageChange: (fn) => {
      emitter.on("stage", fn);
      return () => emitter.off("stage", fn);
    },
    emit: emitter.emit.bind(emitter),
  };
}
