# AUFlow Documentation

This folder documents the current implemented state of AUFlow.
It is maintained against the live Laravel 12 + React 19 + Inertia 2 codebase.

## Documentation Index

- [setup.md](setup.md) - Canonical setup and deployment guide (local dev and production, manual installation).
- [getting-started.md](getting-started.md) - Local setup and first-run workflow.
- [architecture.md](architecture.md) - Runtime architecture, bootstrapping, request flow, queue/scheduler behavior.
- [modules.md](modules.md) - Module-by-module responsibilities, routes, and service boundaries.
- [conventions.md](conventions.md) - Engineering conventions enforced in this codebase.
- [data-model.md](data-model.md) - Domain entities, relationships, and canonical submission model.
- [database.md](database.md) - Table conventions, migrations, indexes, triggers, and partition strategy.
- [workflow-engine.md](workflow-engine.md) - Workflow version snapshots, progression, approval/rejection behavior.
- [security.md](security.md) - AuthN/AuthZ model, encryption, immutable records, route hardening.
- [testing.md](testing.md) - Current test suite structure and execution commands.
- [environment.md](environment.md) - Environment variables and config-backed behavior.
- [deployment.md](deployment.md) - Deployment architecture and production safety practices.
- [deployment-runbook.md](deployment-runbook.md) - Operational runbook for first deploy and updates.
- [troubleshooting.md](troubleshooting.md) - Common failure modes and proven fixes.

## Current System Snapshot

- Backend: Laravel 12.51, PHP 8.4.
- Frontend: React 19, TypeScript, Inertia 2, Tailwind 4.
- Data and async: MySQL 8 + Redis, queue workers and scheduler.
- Core architecture: modular monolith under `app/Modules`, with shared actions/jobs/services.
- Canonical submission model: `tbl_form_submission` with plain-JSON `payload_json` (no application-level encryption).
- Workflow runtime source: `tbl_workflow_version.steps_snapshot` (frozen publish snapshot).
- Immutable records:
	- `tbl_audit_log` append-only via DB triggers.
	- `tbl_snapshot` immutable when `locked=1` via DB trigger and model guard.

## Recent Implemented Changes Reflected In This Docs Set

- Forced password-change flow (`must_change_password`) is active.
- Registration and email verification routes are removed from active routing.
- Profile pictures are stored on a private disk and served through authenticated routes.
- Error reporting module is active:
	- Public intake endpoint: `POST /api/error-reports` (throttled).
	- Admin review endpoints under `/admin/error-reports`.
- Snapshot HTML is stored out-of-database via `rendered_html_path` and `SnapshotStorageService`.
- Reports include async export orchestration plus saved views and scheduled exports.

## Notes

- This docs set intentionally avoids planned/future features.
- Any item described here should be traceable to current code in this repository.
