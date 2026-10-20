# TeraPublic — Instructions for AI Coding Agents

Before changing code, read these files in order:
1. `docs/PROJECT_SPEC.md` — project scope and agreed product rules.
2. `docs/USE_CASES.md` — actors and use cases.
3. `docs/ERD.mmd` — conceptual entity relationship diagram.
4. `spec/terapublic.schema.json` — machine-readable entities, fields, and relationships.
5. `docs/IMPLEMENTATION_PLAN.md` — phased implementation order.

## Working rules
- Do not implement every module at once. Work only on the phase/task explicitly requested by the user.
- Before creating database migrations, check the ERD and JSON spec for consistency. If implementation needs a schema change, explain it and update the documentation first.
- Do not invent public datasets, facility details, project progress, model predictions, or data sources.
- Do not automatically train machine-learning models on every database table. ML use cases and algorithms remain undecided until dataset audit.
- Public endpoints return only published data and must not expose private data or unpublished drafts.
- Authentication and authorization must be enforced by the backend, not only by hiding frontend controls.
- Citizens can browse published public information without logging in. Login is required to submit feedback, view personal feedback history, and use the chatbot.
- One Admin role has full administrative permissions.
- Imported data may be corrected, but the original source file/source values and change history must be retained.
- Data is saved as a draft, validated, and then published. A correction to published data must not replace the current public version until the corrected version is validated and published.
- Prefer incremental, testable changes. Run relevant tests and report what was changed and what remains.
- Ask before adding product features or changing agreed business rules.

## Current stack
- Frontend: Next.js App Router, TypeScript, Tailwind CSS, Turbopack.
- Main backend: Python FastAPI.
- Main database: PostgreSQL.
- RAG: separate Python service; PostgreSQL + pgvector is the initial option, not a final lock-in.
- ML: use case and model are undecided until dataset audit.
