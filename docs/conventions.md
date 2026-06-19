# AUFlow Engineering Conventions

This document reflects conventions currently implemented in the codebase.

## Authorization and Access Control

- Route protection uses permission slugs, not role-name strings.
- Middleware aliases in `bootstrap/app.php`:
	- `permission`
	- `any-permission`
	Both map to `EnsureHasAnyPermission`.
- Controller policy checks are used on sensitive mutations (for example role/user management).

## Controller Boundaries

- Controllers are orchestration-focused:
	- receive request
	- validate via FormRequest (or explicit validator where still legacy)
	- authorize
	- delegate to services/actions
	- return Inertia/JSON/redirect response
- Heavy business logic should remain in services/actions, not controller methods.

## Service and Action Boundaries

- Services coordinate domain workflows, caching, notifications, and composition across models.
- Actions encapsulate focused write paths, typically with one public method and transactional guarantees.
- Multi-table write paths use `DB::transaction()`.
- Concurrency-sensitive approval flows use `lockForUpdate()` on critical rows.

## Caching Conventions

- Keep key families semantically namespaced.
- Existing runtime keys include AUFlow-prefixed style (for example `auflow:workflow:*`).
- Canonical key-family intent still applies:
	- form definitions
	- workflow definitions
	- user permissions
- Cache invalidation should happen in the same mutation path as the write.

## Error-Handling Conventions

- Do not return raw exception internals to end users in production paths.
- Log detailed diagnostics with context.
- Return safe user messages for UI and API consumers.
- Inertia requests use centralized error-page behavior from exception handling in `bootstrap/app.php`.

## Workflow and Snapshot Rules

- Runtime progression should use `tbl_workflow_version.steps_snapshot`.
- Legacy fallback logic exists for pre-version records and should be treated as compatibility behavior.
- Snapshot verification reads status/history from frozen `payload_json` first.
- Snapshot rows are immutable when `locked=1`.

## Frontend Conventions

- Internal navigation uses Inertia `Link`.
- Forms use `useForm` and should prevent duplicate submit while processing.
- Inertia props are strict contracts; keep TypeScript interfaces aligned with backend payloads.
- Design system stack is Tailwind + Radix in existing frontend code.

## Queue and Scheduled Work

- Queue jobs should delegate business logic to services/actions.
- Job classes declare retry/backoff/timeouts explicitly.
- Scheduled commands are declared in `bootstrap/app.php`, not in the legacy console kernel.

## Documentation Rule

- Document only implemented behavior.
- Remove or label stale assumptions quickly when code changes.
