# AUFlow

A digital form submission and multi-step approval system for universities.

![PHP](https://img.shields.io/badge/PHP-8.4-777BB4)
![Laravel](https://img.shields.io/badge/Laravel-12-FF2D20)
![React](https://img.shields.io/badge/React-19-61DAFB)
![License](https://img.shields.io/badge/License-MIT-green)

## What is AUFlow
AUFlow is a university-focused platform that replaces paper-based request forms with a digital process. Students can submit requests such as enrollment concerns, clearance requirements, and facility-use requests through one web system. The platform keeps submissions organized, searchable, and traceable from start to finish.

Each request is routed through configurable multi-step workflows handled by assigned staff or administrators. Steps can be sequential or parallel, and every stage can trigger notifications to keep approvers and submitters informed. This helps departments process requests consistently even during high-volume periods.

When a request is completed, AUFlow can generate a QR-verifiable, tamper-evident snapshot for official record-keeping. Administrators can also manage forms, workflows, users, and roles, then monitor system activity through reports and analytics.

## Key Features
- Dynamic form builder with conditional fields and slot reservation
- Visual workflow builder with sequential and parallel step support
- Role-based access control via permission slugs
- In-app and email notifications (via Resend)
- Audit logging for all user and system actions
- QR-code verifiable immutable submission snapshots
- Reports with date range filtering, status aggregates, and CSV export
- Enrollment-scale concurrency handling via queued job processing

## Tech Stack
| Layer | Technology |
|---|---|
| Backend | PHP 8.4, Laravel 12 |
| Frontend | React 19, TypeScript, Tailwind CSS 4 |
| Bridge | Inertia.js 2.x |
| Database | MySQL |
| Mail | Resend |
| Queue | Laravel Queue (database or Redis driver) |
| Testing | PHPUnit 11 |

## Getting Started

```bash
git clone <repo-url> auflow
cd auflow
cp .env.example .env
# Edit .env: set DB_HOST, DB_DATABASE, DB_USERNAME, DB_PASSWORD, REDIS_HOST
composer install
npm install
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
npm run build
php artisan serve
```

For complete setup and deployment instructions, see [docs/setup.md](docs/setup.md).

For the quickstart guide, see [docs/getting-started.md](docs/getting-started.md).

## Documentation

Full technical documentation can be found in the `docs/` directory:

- [README Index](docs/README.md) - Main documentation entry point
- [Setup Guide](docs/setup.md) - End-to-end setup and deployment (local/production)
- [Architecture](docs/architecture.md) - Modular monolith structure and data flow
- [Database](docs/database.md) - Schema conventions and immutability triggers
- [Workflow Engine](docs/workflow-engine.md) - Graph traversal and versions
- [Security](docs/security.md) - Payload encryption and permissions
- [Deployment](docs/deployment.md) - Production deployment with Nginx + Supervisor
- [Testing](docs/testing.md) - Test suites for encryption and workflows
- [Troubleshooting](docs/troubleshooting.md) - Common issues and fixes

## License and Credits
- License: MIT
- Framework and starter foundations: Laravel, Inertia.js, React, and the open-source ecosystem used by this project
- Project: AUFlow capstone system for university digital request workflows
