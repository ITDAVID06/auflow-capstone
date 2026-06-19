# AUFlow Module Guide

This document summarizes active modules in `app/Modules` and how they participate in the submission lifecycle.

## Dashboard

- Route surface: root admin dashboard (`/dashboard`) and widget endpoints under `/dashboard/report-widgets/*`.
- Key responsibility: admin summary metrics and dashboard widgets for trend/status data.

## UserManagement

- Route prefix: `/user-management`.
- Middleware: `auth` + permission checks (`users.manage`, `roles.manage`, or `any-permission` combinations).
- Core responsibilities:
	- user CRUD and archival
	- role CRUD
	- role-permission sync
	- permission resolution cache (`PermissionService`)
- Hardening highlights:
	- role assignment restrictions enforced in policy (`RolePolicy`)
	- created users are flagged `must_change_password=true`

## FormBuilder

- Route areas:
	- admin authoring (`/admin/forms`, `/forms`)
	- facilities (`/admin/facilities*`)
	- requester catalog/submission (`/user/forms*`)
- Middleware:
	- `forms.manage` for authoring/mutations
	- `facilities.manage` for facility admin
	- `throttle:submissions` on user submit endpoint
- Core responsibilities:
	- form and field authoring
	- form revision lifecycle
	- form visibility and permission mapping
	- request-form rendering and submission entrypoint

## WorkflowBuilder

- Route prefix: `/workflows`, plus admin views under `/admin/workflows` and support endpoints under `/workflow-config/*`.
- Middleware: `auth` + `permission:workflows.manage`.
- Core responsibilities:
	- workflow draft/update/publish/archive/enable transitions
	- workflow canvas persistence
	- workflow version snapshot creation (`tbl_workflow_version`)
	- readiness helpers

## StudentDashboard

- Route prefix: `/student-dashboard`.
- Middleware: `auth` + `permission:dashboard.student`.
- Core responsibilities:
	- list active forms and submit requests
	- submission status/metrics/history
	- edit eligible submissions
	- download progress attachments for owned submissions

## StaffDashboard

- Route prefix: `/staff-dashboard`.
- Base middleware: `auth` + `permission:dashboard.staff`.
- Nested reviewer middleware: `any-permission:requests.approve,submissions.view,submissions.override`.
- Core responsibilities:
	- pending approvals and all-requests views
	- approve/reject step actions (with locking)
	- staff-side submissions and metrics
	- progress/comment attachment handling

## AdminSubmissions

- Route prefix: `/admin/submissions`.
- Middleware:
	- read/list: `any-permission:submissions.view,submissions.override`
	- override actions: `any-permission:submissions.override`
- Core responsibilities:
	- cross-form submission review
	- override approve/reject
	- snapshot lookup integration

## VerificationSnapshot

- Route prefix: `/snapshots`.
- Public endpoints:
	- `GET /snapshots/{public_id}`
	- `GET /snapshots/{public_id}/verify`
- Auth-protected endpoints include progress snapshot lookups and verify-all for a submission.
- Core responsibilities:
	- create immutable per-step snapshots
	- verify HMAC integrity
	- expose public verification payloads

## Reports

- Route prefix: `/reports`.
- Middleware: `auth` + `any-permission:submissions.view,submissions.override`.
- Core responsibilities:
	- filtered report datasets
	- chart/aggregate/compare APIs
	- CSV/PDF exports
	- async export orchestration (cache-tracked export status)
	- saved report views (`tbl_report_view`)
	- scheduled exports (`tbl_scheduled_export`)

## Notifications

- API prefix: `/api/notifications`.
- Page route: `/notifications`.
- Core responsibilities:
	- in-app notification listing
	- mark read / mark all read / delete

## Performance

- Route prefix: `/performance`.
- Middleware: `auth` + `any-permission:performance.view`.
- Core responsibilities:
	- staff performance page and backing data endpoints

## AuditTrail

- Route prefix: `/admin/audit-trail`.
- Middleware: `auth` + `permission:users.manage`.
- Core responsibilities:
	- audit listing, filtered data API, and export

## ErrorReports

- Public intake endpoint:
	- `POST /api/error-reports` (throttle `10,1`)
- Admin routes under `/admin/error-reports` with `permission:error-reports.manage`.
- Core responsibilities:
	- collect frontend error reports
	- admin review and status updates (`new`, `reviewed`, `resolved`)

## Cross-Module Runtime Notes

- Canonical submission data is centralized in `tbl_form_submission`.
- Workflow runtime references `tbl_workflow_version.steps_snapshot` for current logic, with explicit legacy fallbacks where needed.
- Approval/rejection/override paths use transactional locking.
- Snapshot generation is dispatched after response from approval actions.
