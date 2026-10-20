# TeraPublic AI CLI Specification Pack

This folder contains project context for an AI coding CLI such as Codex CLI, Claude Code, or another coding assistant that reads repository instructions.

## Contents
- `AGENTS.md` — agent entrypoint and working rules.
- `docs/PROJECT_SPEC.md` — agreed product scope and business rules.
- `docs/FRONTEND_GUIDE.md` — frontend design rules, anti-AI-slop principles, palette, typography, and library policy.
- `spec/design-system.json` — machine-readable design tokens and frontend constraints.
- `docs/USE_CASES.md` — actors, permissions, and workflows.
- `docs/ERD.mmd` — conceptual ERD in Mermaid syntax.
- `docs/IMPLEMENTATION_PLAN.md` — incremental build order.
- `spec/terapublic.schema.json` — structured conceptual schema for tools/agents.

## How to use
1. Extract/copy these files into the root of the TeraPublic repository.
2. Ensure `AGENTS.md` is in the repository root.
3. Start the AI CLI from that root directory so it can read the instructions.
4. Give it a small scoped task, for example: "Read AGENTS.md and the TeraPublic docs. Review the ERD for inconsistencies only; do not modify code yet. Report questions before implementation."

## Important
These files describe a **conceptual draft**, not final SQL migrations. Do not directly generate every table from this JSON without reviewing the open design questions and confirming MVP datasets.

## Frontend setup note
The current frontend is Next.js App Router with TypeScript, Tailwind CSS, Turbopack, and a `src/` directory. Read `docs/FRONTEND_GUIDE.md` before implementing UI. The guide deliberately limits libraries: do not install all of Motion, GSAP, React Spring, Anime.js, 3D, smooth-scroll, and UI kits together. Choose dependencies only for a concrete task.
