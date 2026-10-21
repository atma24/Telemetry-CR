# TeraPublic AI CLI Specification Pack

This repository folder contains the working product, architecture, ERD, and UI specification for TeraPublic. It is intended to be read by an AI coding CLI and human developers.

## Files
- `AGENTS.md` — root instructions for coding agents.
- `docs/PRODUCT_GUIDE.md` — detailed explanation of the app we are building: problem, vision, users, screens, workflows, MVP, and non-goals. Read this first.
- `docs/PROJECT_SPEC.md` — concise product scope, access rules, and agreed decisions.
- `docs/BACKEND_GUIDE.md` — Laravel backend architecture, conventions, endpoint boundaries, and security rules.
- `docs/FRONTEND_GUIDE.md` — Next.js/Tailwind design system and frontend architecture.
- `docs/USE_CASES.md` — actors, permissions, and workflows.
- `docs/ERD.mmd` — conceptual ERD in Mermaid.
- `docs/IMPLEMENTATION_PLAN.md` — staged delivery plan.
- `spec/terapublic.schema.json` — machine-readable conceptual database schema.
- `spec/design-system.json` — machine-readable palette, typography, library policy, and UI constraints.

## How to use
1. Extract/copy this folder into the root of the TeraPublic repository.
2. Keep `AGENTS.md` at the repository root.
3. Start the AI CLI from that root directory.
4. Give one scoped task at a time. Ask for a plan/review before schema migrations or broad refactors.

## Read order for AI coding tools
1. `docs/PRODUCT_GUIDE.md` to understand what TeraPublic should do and why.
2. `docs/PROJECT_SPEC.md` and `docs/USE_CASES.md` for agreed scope, actors, and workflows.
3. `docs/FRONTEND_GUIDE.md` / `docs/BACKEND_GUIDE.md` for implementation conventions.
4. `docs/ERD.mmd` and `spec/terapublic.schema.json` for conceptual data structures.
5. `docs/IMPLEMENTATION_PLAN.md` for staged work.

## Important status notes
- These files define the agreed direction but the ERD and JSON schema are conceptual, not executable database migrations.
- Final field-level modeling must be checked against the actual candidate DKI Jakarta datasets before dataset-specific columns are frozen.
- Laravel is the main backend API and owns operational product data. Python is a separate AI service for RAG and future ML.
- ML use case and algorithm are intentionally undecided pending the dataset audit.
- Follow `docs/IMPLEMENTATION_PLAN.md`; do not implement every phase at once.
