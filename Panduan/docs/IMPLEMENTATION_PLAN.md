# TeraPublic — Incremental Implementation Plan

Do one phase at a time. Do not scaffold every feature in one task.

## Phase 0 — Confirm baseline
- Confirm current Next.js project runs.
- Keep existing Next.js App Router, TypeScript, Tailwind, and Turbopack configuration.
- Create/use this documentation pack at project root.
- Do not modify generated project configuration unnecessarily.

## Phase 1 — Finalize conceptual schema
- Review the tables and relationships in `ERD.mmd` and `../spec/terapublic.schema.json`.
- Identify design questions that need user confirmation.
- Confirm actual MVP datasets before locking dataset-specific attributes.
- Finalize PostgreSQL types, constraints, indexes, and versioning rules.

## Phase 2 — FastAPI foundation
- Create isolated Python environment.
- Set up FastAPI app, configuration, environment variables, logging and health endpoint.
- Configure SQLAlchemy (or another explicitly approved ORM) and Alembic migrations.
- Add automated tests for the health endpoint and configuration.

## Phase 3 — Identity and access control
- Implement citizen registration/login/session or token design.
- Implement one Admin role.
- Test public versus authenticated versus Admin endpoints.
- Store only password hashes, never plaintext passwords.

## Phase 4 — Reference data
- Implement regions and categories.
- Add validation and duplicate-prevention rules.
- Add audit history.

## Phase 5 — Domain data and data governance
- Implement facility/project identities and versions.
- Implement source registry, import jobs, file handling, previews, row validation, draft, publication, correction, withdrawal and rollback/history semantics.
- Write tests for publication invariants.

## Phase 6 — Public monitoring and feedback
- Implement only published-data endpoints.
- Implement feedback submission and ownership-protected history.
- Implement Admin response/status updates and feedback event history.

## Phase 7 — RAG
- Agree approved knowledge sources and citation policy.
- Build document processing, chunks/embeddings, retrieval, citations, chat session/message APIs.
- Add ownership tests and insufficient-evidence behavior.

## Phase 8 — ML (after data audit)
- Choose a measurable use case based on real available datasets.
- Specify target, features, train/validation/test split and baseline metrics.
- Only then implement training, model versioning and inference.

## Done criteria per task
- Relevant tests pass.
- Input validation and authorization are implemented server-side.
- Documentation is updated when behavior or schema changes.
- The agent reports modified files, test results, and known limitations.
