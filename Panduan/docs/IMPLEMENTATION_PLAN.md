# TeraPublic — Incremental Implementation Plan

Work one phase at a time. Do not scaffold every feature in one task. The existing Next.js project already uses App Router, TypeScript, Tailwind CSS, Turbopack, and `src/`; preserve its working setup.

## Phase 0 — Documentation and repository baseline
- Keep `AGENTS.md`, product spec, backend guide, frontend guide, ERD, JSON schema, and implementation plan in sync.
- Inspect actual repository structure and package versions before changes.
- Decide whether `backend-laravel/` and `ai-service/` are subfolders in a monorepo or separate repositories; do not move the existing frontend without explicit need.

## Phase 1 — Finalize conceptual data model
- Review `docs/ERD.mmd` against `spec/terapublic.schema.json`.
- Resolve versioning/publication invariants, import provenance, session storage, APBD source columns, and feedback target constraints.
- Audit actual candidate DKI Jakarta datasets before freezing dataset-specific columns and deciding JSONB boundaries.
- Only after review, finalize PostgreSQL types, constraints, indexes, and migration order.

## Phase 2 — Laravel API foundation
- Create Laravel project in `backend-laravel/` or its own repository.
- Configure `.env`, PostgreSQL, API routing, health endpoint, error handling, logging, and tests.
- Install/configure Sanctum according to the official docs matching the Laravel version.
- Use the agreed unversioned `/api/...` route convention and a consistent API response/error contract.

## Phase 3 — Identity and access
- Implement citizen registration/login/logout/current-user flow and server-side sessions.
- Configure Sanctum SPA cookie/CSRF/CORS/stateful-domain settings for Next.js.
- Support short-lived Sanctum bearer tokens for active Admins using direct API clients; allow the current token to be revoked.
- Provision the single Admin role securely; do not enable public Admin registration.
- Test public, citizen-owned, and Admin-protected endpoints.

## Phase 4 — Reference and audit data
- Implement regions and categories with validation and duplicate prevention.
- Implement data sources and audit logging.
- Add the minimum necessary versioning mechanics and test them before domain imports.

## Phase 5 — Facilities, APBD, and datasets
- Implement the APBD eight-column data model, provenance, correction, and publication workflow.
- Implement source registry, import jobs, private file storage, and queued APBD imports using normalized headers, batch progress, validation, row errors, and duplicate-candidate reporting; this API has no preview or manual mapping step. Keep the HTTP upload limit at 20 MB.
- Implement draft, publication, correction, withdrawal, and history semantics.
- Use queues for imports that are too large/slow for a normal request.

## Phase 6 — Public data and monitoring
- Implement endpoints that return published versions only.
- Build public facility/APBD reads and monitoring for actual published source data; do not infer APBD totals or project details.
- Show source/update periods when known; do not fabricate public values.

## Phase 7 — Feedback
- Implement authenticated submission and ownership-protected user history.
- Implement Admin review/status/response operations and append-only feedback events.
- Test required rejection reason and valid target constraints.

## Phase 8 — Frontend API integration
- Build typed API client(s) in `src/lib/api/` for Laravel endpoints.
- Use the chosen cookie/CSRF flow; do not place session credentials in localStorage.
- Keep frontend modules focused on UI and request/response mapping, not duplicate business logic.
- Use Next.js Route Handlers only for a documented BFF requirement.

## Phase 9 — RAG
- Confirm approved knowledge sources and citation policy.
- Decide table/data ownership between Laravel and Python AI service before writing integration code.
- Build document processing, chunks/embeddings, retrieval, citations, chat endpoints, timeouts, and insufficient-evidence behavior.
- Keep the Python service private and enforce user ownership through Laravel.

## Phase 10 — ML (after dataset audit)
- Identify a measurable task that has adequate data.
- Specify target, feature set, baseline, evaluation method, and leakage-safe data split.
- Only then implement training, evaluation, model artifacts/versions, and inference.

## Completion criteria for each task
- Relevant tests pass or failures are reported accurately.
- Validation and authorization are server-side.
- Docs are updated when schema or behavior changes.
- Only the requested phase is implemented.
- Report files changed, dependencies/commands used, tests run, and known limitations.
