import { GR45_INTENT_CLASSIFIER_OPTIONS } from "./intent/intent-config.js";
import { gr45FastPlanner } from "./planner/fast-planner.js";
import {
  GR45_REPLY_SURFACE_TRANSPORT_VI,
  postProcessGr45Plan,
} from "./planner/planner-policy.js";

export const GR45_DOMAIN_POLICY = {
  synthesizerDomainInstructions: GR45_REPLY_SURFACE_TRANSPORT_VI,
  intentClassifierOptions: GR45_INTENT_CLASSIFIER_OPTIONS,
  planPostProcessor: postProcessGr45Plan,
  prePlannerHook: gr45FastPlanner,
};
