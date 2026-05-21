# FlowForge Frontend, Realtime, and AI Notes

## Frontend

- Vue 3 Composition API is mounted from `resources/js/app.ts`.
- Pinia stores live in `resources/js/stores`.
- API access is centralized in `resources/js/lib/axios.ts`.
- Cursor pagination, workflow helpers, Reverb subscription, and toast state live in `resources/js/composables`.

## Auth

- Protected backend routes use `auth:api`.
- Access tokens are stored under `flowforge.access_token`.
- Refresh tokens are stored under `flowforge.refresh_token`.
- Token refresh uses `POST /api/v1/auth/refresh`.
- Axios retries a failed request once after a successful refresh.

## Realtime

- Reverb private channel auth is registered at `/broadcasting/auth`.
- The broadcasting auth route uses `api` and `auth:api` middleware.
- Workflow run events are broadcast to `private-tenant.{tenantId}`.
- The frontend Echo authorizer uses the same Axios instance, so 401 channel auth responses can refresh and retry.

## AI Workflow Generation

- Endpoint: `POST /api/v1/ai/generate-workflow`.
- Request body: `{ "prompt": "Describe workflow" }`, max 400 characters.
- Response: `{ "data": { "definition": { "steps": [] }, "confidence": "high|medium|low" } }`.
- The service logs metadata only: tenant id, prompt length, response time, and validation status.
