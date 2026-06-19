# Database Schema and Conventions

This document describes the current AUFlow database implementation as defined by migrations and runtime usage.

## Conventions

- Core domain tables are prefixed with `tbl_`.
- Laravel infrastructure tables (`jobs`, `failed_jobs`, `sessions`, cache tables) remain unprefixed.
- Production-oriented migrations use defensive checks for MySQL/SQLite differences where needed.

## Canonical Data Rules

- `tbl_form_submission` is the canonical source-of-truth for runtime submissions.
- `payload_json` is stored as plain JSON (no application-level encryption). Security is enforced at the database access-control layer.
- Runtime workflow state is version-bound through `workflow_version_id` and `tbl_workflow_version.steps_snapshot`.

## Current Schema Highlights

### Workflow Versioning

- `tbl_workflow_version` stores immutable workflow snapshots:
  - `workflow_id`
  - `version_number`
  - `steps_snapshot`
  - `published_at`
  - `is_current`
- `tbl_form_submission.workflow_version_id` links each submission to the exact published workflow snapshot.
- `tbl_workflow_step_progress.workflow_version_id` replaced legacy `workflow_version` naming.

### Submission Hardening

- `tbl_form_submission.idempotency_key` unique index prevents duplicate canonical writes.
- Virtual generated columns on `tbl_form_submission`:
  - `v_student_id`
  - `v_department_code`
- Both generated columns are indexed for reporting/filter performance.

### Snapshot Storage Change

- `tbl_snapshot.rendered_html` was removed.
- `tbl_snapshot.rendered_html_path` now stores object-storage location for rendered HTML.

### Role/Table Cleanup

- `tbl_role.role_type` removed.
- Legacy organization-table cleanup migration is present as a no-op marker.
- Legacy compatibility columns were dropped from submission and dependent tables in cleanup migrations.

## Immutability and Integrity Guards

### Audit Log Append-Only

`tbl_audit_log` has MySQL triggers that block modifications:

- `prevent_audit_update`
- `prevent_audit_delete`

Effect: audit rows are append-only in production MySQL environments.

### Snapshot Immutability

`tbl_snapshot` has MySQL trigger `trg_tbl_snapshot_no_update` to block updates when `locked=1`.

Model-level guard (`Snapshot` model `saving` hook) also throws when modifying an existing locked snapshot.

## Foreign Keys and Constraints

Major FK hardening migrations ensure links across:

- workflows and users (`created_by`, `assigned_account_id`, rejection links)
- workflow progress and form/workflow/step/actor
- step-progress attachments and uploaders
- slots to forms/users/facilities
- submissions to forms with `restrictOnDelete`
- report-view/scheduled-export ownership and form links

## Indexing Strategy

Implemented indexes include:

- dashboard-focused index on submissions (`current_actor_id`, `current_workflow_status`, `submitted_at`)
- workflow progress status/time indexes for approvals and analytics
- staff performance index on workflow step progress
- reporting/supporting composite indexes from performance migration set
- generated-column indexes (`idx_virtual_student_id`, `idx_virtual_department_code`)

## Partitioning Strategy

Migration `2026_04_26_220000_partition_audit_log_and_snapshot_tables.php` attempts MySQL partitioning for:

- `tbl_audit_log` (monthly)
- `tbl_snapshot` (quarterly)

Notes:

- Partitioning is skipped on SQLite.
- Migration wraps partition DDL in `try/catch` for compatibility.
- Ongoing partition maintenance is automated via `partitions:manage` command (scheduled monthly).

## Commands Relevant to Database Operations

- `php artisan migrate --force`
- `php artisan partitions:manage`
- `php artisan seed:demo`
- `php artisan snapshot:backfill-hashes` (legacy hash backfill utility)

## Operational Safety

- Production deploy scripts intentionally avoid destructive migration commands.
- Destructive migration commands (`migrate:fresh`, `migrate:refresh`) are explicitly forbidden in production.
