# AI Feature

This document describes the current AI workflow generation feature in FlowForge, including its purpose, prompt strategy, guardrails, validation flow, and known trade-offs.

## Purpose

The AI feature exists to generate **FlowForge-compatible workflow DAG JSON** from short business-oriented descriptions.

It is not intended to be a general chatbot. The product goal is narrow:

- accept a workflow request in natural language
- generate a valid DAG definition
- validate that DAG against the real FlowForge runtime
- return machine-readable JSON that can be saved and executed

## Current Provider

FlowForge currently uses **Gemini** for workflow generation.

Relevant environment variables:

- `GEMINI_API_KEY`
- `GEMINI_MODEL`
- `AI_API_KEY` as a compatibility fallback when configured

The backend service responsible for inference is:

- [WorkflowGeneratorService.php](/D:/Personal-Project/flowforge/app/Services/Ai/WorkflowGeneratorService.php:1)

The API entry point is:

- [AiController.php](/D:/Personal-Project/flowforge/app/Http/Controllers/Api/V1/AiController.php:1)

## Prompting Strategy

The prompting layer is intentionally strict. The main principle is:

> The model must generate output that matches the execution engine that actually exists.

That means the prompt is written around runtime truth, not around hypothetical capabilities.

### Core Prompt Expectations

The model is instructed to:

- return JSON only
- avoid conversational preambles and closing text
- generate at most the supported step types
- produce a DAG with valid dependencies
- keep step definitions compatible with FlowForge executors

### Runtime-Aware Constraints

The prompt and backend validation both enforce these constraints:

- `SCRIPT` and `CONDITION` expressions must be compatible with Symfony ExpressionLanguage
- JavaScript syntax must not be generated
- HTTP outbound config must use `payload`, not `body`
- placeholder syntax like `{{step_id}}` must not be generated
- step IDs must conform to the platform validation rules
- public API endpoints should be valid and expected to return valid JSON

This was added because an earlier version of the prompt produced JavaScript-like expressions that the FlowForge runtime could not execute.

## Guardrails

The feature has explicit scope guardrails.

It should only support requests related to:

- business workflows
- project management
- productivity
- operational work

It should reject or refuse workflows for:

- illegal activity
- destructive or abusive system behavior
- cyber misuse or security violations
- unrelated requests such as article writing, personal venting, or generic coding assistance

The prompt also treats prompt-injection phrases such as:

- "Ignore previous instructions"
- "You are now ..."

as plain user input text, not as control instructions.

## Validation Flow

The backend does not trust model output directly.

Current flow:

1. receive prompt from API
2. trim and length-limit prompt
3. send request to Gemini
4. parse raw model output as JSON
5. retry once if the model does not return valid JSON
6. validate the DAG through the FlowForge parser and request rules
7. reject invalid output with structured application errors
8. return validated DAG plus confidence level

## Failure Handling

The service currently handles these error classes:

- missing or unavailable AI provider configuration
- invalid JSON from the model
- invalid DAG structure after parsing
- unsupported expression patterns such as JavaScript syntax
- repeated downstream failures via circuit-breaker style tracking

Expected API behavior:

- `422` for generation outputs that are invalid but well-formed enough to classify as generation errors
- `503` for provider unavailability or temporary AI subsystem failure

## Confidence Levels

The service returns a coarse confidence indicator:

- `high`
- `medium`
- `low`

The confidence is based on the shape of the generated workflow, not on a probabilistic score returned by the model.

## Frontend Integration

The main frontend surface for this feature is:

- [AiWorkflowBuilder.vue](/D:/Personal-Project/flowforge/resources/js/components/ai/AiWorkflowBuilder.vue:1)

Current frontend behavior:

- prompt input with character limit
- generate action with loading state
- DAG preview
- confidence badge
- reuse generated workflow in the editor

## Security Posture

The AI layer is designed with a defense-in-depth approach:

- narrow prompt scope
- guardrails against abusive use cases
- JSON-only output expectation
- backend structural validation
- runtime compatibility validation
- API rate limiting

The critical point is that **prompting is not the only safety mechanism**. Backend validation remains the real enforcement layer.

## Known Trade-Offs

- The AI feature is intentionally constrained and less flexible than a free-form assistant.
- Expression support is limited by Symfony ExpressionLanguage, which is safer but narrower than arbitrary code execution.
- Public endpoint validity is guided by prompt rules and validation heuristics, but cannot guarantee long-term external API stability.
- The model is optimized for valid workflow generation, not for explanatory conversational responses.

## Related Files

- [WorkflowGeneratorService.php](/D:/Personal-Project/flowforge/app/Services/Ai/WorkflowGeneratorService.php:1)
- [AiController.php](/D:/Personal-Project/flowforge/app/Http/Controllers/Api/V1/AiController.php:1)
- [ai-prompting.md](/D:/Personal-Project/flowforge/docs/ai-prompting.md:1)
- [frontend-ai.md](/D:/Personal-Project/flowforge/docs/frontend-ai.md:1)
