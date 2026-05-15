import { buildSuggestionContext } from "./suggestions/build-suggestion-context.js";
import { suggestionStrategyRegistry } from "./suggestions/suggestion-strategy.registry.js";
import { createSuggestionResult } from "./suggestions/create-suggestion-result.js";

export function createSuggestionGeneratorNode(graphDependencies) {
  return async function suggestionGeneratorGraphNode(graphState) {
    graphDependencies.bus?.emit("stage", {
      stage: "suggestion_generator",
      status: "enter",
      correlationId: graphState.correlationId,
    });

    try {
      const context = buildSuggestionContext({
        deps: graphDependencies,
        state: graphState,
      });

      const strategy = suggestionStrategyRegistry.resolve(
        context.suggestionSource,
      );

      return await strategy.execute(context);
    } catch (error) {
      return createSuggestionResult({
        source: "suggestion_generator_error",
        suggestions: [],
        extra: {
          error_message: String(error?.message ?? error).slice(0, 300),
        },
      });
    } finally {
      graphDependencies.bus?.emit("stage", {
        stage: "suggestion_generator",
        status: "exit",
        correlationId: graphState.correlationId,
      });
    }
  };
}