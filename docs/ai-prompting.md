# AI Prompting Notes

This document summarizes the AI prompting approach used in this session for FlowForge. It is written as a recruiter-facing explanation of how the prompting layer is designed, constrained, validated, and connected to the execution engine.

It reflects the current state of the project and the direction taken during this session, including:

- the move to Gemini-based workflow generation
- guardrails to keep outputs within business workflow scope
- runtime-aware validation so generated workflows match the real engine
- the requirement to keep prompts aligned with the actual execution model, not hypothetical capabilities

## Objective

The AI feature in FlowForge is not meant to be a generic chatbot. Its purpose is narrow and operational:

- turn a business-oriented workflow request into a valid FlowForge DAG definition
- return clean JSON that can be parsed by the backend
- avoid generating configurations the workflow engine cannot execute safely

The prompting strategy is designed around one principle:

> The model should only generate outputs that are compatible with the real runtime and validation rules of the platform.

## Context From This Session

During this session, the AI layer was refined after a concrete mismatch was found between generated workflow JSON and the actual execution runtime.

The failure pattern looked like this:

- AI generated JavaScript-style expressions such as `filter(...)` and arrow functions
- AI generated placeholder syntax like `{{step_id}}`
- AI used HTTP config keys such as `body` even though the runtime expects `payload`
- generated public API endpoints were sometimes unrealistic or not guaranteed to return valid JSON

That mismatch mattered because FlowForge does **not** execute arbitrary JavaScript. The backend uses Symfony ExpressionLanguage for `SCRIPT` and `CONDITION` steps, and the HTTP executor has a specific payload contract.

So the prompt was hardened to follow the engine that actually exists.

## Model Choice

This session migrated workflow generation to Gemini.

Current intent:

- provider: Gemini
- key: `GEMINI_API_KEY`
- model: `GEMINI_MODEL`, defaulting to a lightweight Gemini model configured in the app

Why this matters in prompt design:

- provider migration changes response parsing and failure handling
- prompt instructions must still produce backend-compatible JSON regardless of model vendor

## Prompt Design Goals

The prompting layer is designed to enforce five things:

1. Stay inside product scope
2. Produce valid workflow JSON only
3. Follow FlowForge runtime rules
4. Avoid unsafe or disallowed use cases
5. Fail predictably when output is invalid

## Scope Guardrails

The prompt was updated in this session so the AI only serves requests related to:

- business workflows
- project management
- productivity
- work operations

It should refuse unrelated requests such as:

- personal venting
- generic article writing
- unrelated coding help

It should also reject workflows that support:

- illegal activity
- destructive behavior
- cyber abuse or security violations

Another explicit guardrail added in this session:

- prompt-injection style instructions inside the user input such as `"ignore previous instructions"` or `"you are now..."` must be treated as plain input text, not as system-level instructions

## Runtime-Aware Constraints

One of the most important outcomes from this session was aligning the AI prompt with the real workflow engine.

The generated output must respect these runtime constraints:

- `SCRIPT` and `CONDITION` expressions must be compatible with Symfony ExpressionLanguage
- generated expressions must not contain JavaScript syntax such as:
  - `=>`
  - `function`
  - `filter()`
  - `map()`
  - `reduce()`
  - `find()`
  - `forEach()`
  - `const`
  - `let`
  - `return`
- HTTP steps must use `payload`, not `body`
- placeholder syntax like `{{step_id}}` must not be used
- step identifiers must follow the backend validation rules expected by the platform
- if public APIs are referenced, they should be valid endpoints expected to return valid JSON

This session also clarified that prompt quality alone is not enough. The backend must enforce the same rules after generation.

## Output Contract

The AI output is constrained to a machine-readable response only.

The model is instructed to:

- return only clean JSON
- avoid preambles such as "Here is your workflow"
- avoid commentary or explanation around the JSON

This is important because the result is consumed by application code, not by a human reading a chat response.

## Validation Strategy

The implementation approach reflected in this session is not "trust the model".

The actual safety model is:

1. Prompt the model narrowly
2. Parse the returned JSON
3. Retry once if the model does not return valid JSON
4. Validate the DAG structure using backend parsing rules
5. Reject outputs that violate runtime expectations
6. Surface deterministic validation errors back to the caller

That means the prompt is only the first filter. The backend remains the real source of truth.

## Why This Matters

From an engineering perspective, the main lesson from this session is:

> Prompting should describe the system that actually exists, not the system you wish existed.

If the executor uses ExpressionLanguage, the prompt should not encourage JavaScript.
If the HTTP executor expects `payload`, the prompt should not mention `body`.
If the scheduler, queue, and monitoring stack all operate on strict contracts, the AI output must align to those contracts.

This is why the AI work in this session was tightly coupled to:

- workflow runtime rules
- backend validation
- queue execution behavior
- real monitoring and debugging feedback from actual runs

## Recruiter-Friendly Summary

If this were summarized in one line:

> The AI feature was designed as a constrained workflow-definition generator, backed by strict runtime-aware validation, rather than a free-form text assistant.

That is the core engineering choice behind the prompting work in this session.

## Related Session Artifacts

- Full session transcript: [SESSION_HISTORY.md](/D:/Personal-Project/flowforge/SESSION_HISTORY.md:1)
- Frontend AI notes already present in the repo: [frontend-ai.md](/D:/Personal-Project/flowforge/docs/frontend-ai.md:1)

## Session-Specific Note

This document was created in response to a request to prepare recruiter-facing AI prompt material while still staying grounded in the current session history. That includes the explicit request to keep this write-up aligned with the work and decisions made during this same session, rather than turning it into a generic prompt-engineering note.
