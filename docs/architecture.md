# Architecture Overview

AUFlow is a modular monolith built on Laravel 12 and Inertia React.
Feature domains are grouped under `app/Modules`, while shared cross-cutting behavior remains in `app/Services`, `app/Actions`, `app/Jobs`, and framework boot files.

## Runtime Stack

- Backend: PHP 8.4, Laravel 12.
- Frontend: React 19 + TypeScript via Inertia 2.
- Database: MySQL 8.
- Cache and queue backend: Redis (production pattern), with database queue fallback available in config.
- Background processes: dedicated queue worker and scheduler, managed by Supervisor in production.

## Application Bootstrapping

Core application wiring lives in `bootstrap/app.php`:

- Routing entrypoints:
  - `routes/web.php`
  - `routes/console.php`
  - health endpoint `/up`
- Middleware aliases:
  - `permission` and `any-permission` both map to `EnsureHasAnyPermission`
- Web middleware stack appends:
  - `HandleAppearance`
  - `HandleInertiaRequests`
  - `AddLinkHeadersForPreloadedAssets`
  - `ForcePasswordChange`
- Scheduler registration:
  - `workflow:send-approval-reminders` (every minute)
  - `reports:send-scheduled-exports` (every minute)
  - `partitions:manage` (monthly)
- Exception handling includes Inertia-aware error rendering and safe 429/419 handling.

Module service providers are registered in `bootstrap/providers.php`.

## Route Composition

`routes/web.php` defines root and dashboard routes, then includes module route files:

- `app/Modules/UserManagement/routes.php`
- `app/Modules/FormBuilder/routes.php`
- `app/Modules/AuditTrail/routes.php`
- `app/Modules/WorkflowBuilder/routes.php`
- `app/Modules/StaffDashboard/routes.php`
- `app/Modules/StudentDashboard/routes.php`
- `app/Modules/VerificationSnapshot/routes.php`
- `app/Modules/AdminSubmissions/routes.php`
- `app/Modules/Reports/routes.php`
- `app/Modules/Notifications/routes.php`
- `app/Modules/Performance/routes.php`
- `app/Modules/ErrorReports/routes.php`

Auth and settings are added via `routes/auth.php` and `routes/settings.php`.

Current auth surface is login, password reset, password confirmation, and forced password-change flow.
Registration and email verification routes are not active.

## Code Organization

- `app/Modules/*`: domain-level controllers, models, requests, services, observers, routes.
- `app/Actions/*`: focused write operations (for example canonical submission persistence).
- `app/Jobs/*`: async orchestration (submission processing, export generation, notifications).
- `app/Services/*`: shared services (notifications, snapshot storage, audit logging, profile picture URL resolution).
- `resources/js/pages/*`: Inertia page components.
- `resources/js/components/*`: reusable frontend components.

## Submission Processing Flow

Current canonical submission path:

1. Client posts to submission endpoints (`user.forms.submit`, `student-dashboard.forms.submit`, or staff equivalent).
2. Request validation happens in FormRequest classes.
3. `StudentSubmissionService` or `StaffSubmissionService` normalizes payload, computes idempotency key, and assembles workflow-progress payloads.
4. `ProcessFormSubmissionJob` executes asynchronous persistence.
5. `WriteCanonicalSubmissionAction` runs transactional writes to:
   - `tbl_form_submission`
   - `tbl_submission_attachment`
   - `tbl_slots`
   - `tbl_workflow_step_progress`
6. Downstream notification jobs are dispatched.

## Workflow Runtime Design

- Runtime progression reads frozen workflow definitions from `tbl_workflow_version.steps_snapshot`.
- `WorkflowProgressService::buildInitialProgress()` builds initial progress rows from the published version snapshot.
- Staff and admin approval/rejection services use `DB::transaction()` + `lockForUpdate()` on progress and canonical submission rows.
- Legacy fallback to live workflow step data still exists for pre-version records.

## Storage and Files

- General private files: `private` disk.
- Profile pictures: dedicated private `profile-pictures` disk served through authenticated route `/profile-pictures/{path}`.
- Snapshot rendered HTML: object storage path in `tbl_snapshot.rendered_html_path`, written by `SnapshotStorageService`.

## Observability and Audit

- `tbl_audit_log` is append-only via DB triggers (`prevent_audit_update`, `prevent_audit_delete`).
- Snapshot immutability is enforced by DB trigger plus model guard.
- Error reports are captured via public endpoint `POST /api/error-reports` and reviewed in admin UI.
