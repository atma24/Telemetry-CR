# TeraPublic — Instructions for AI Coding Agents

Read these files before making changes, in this order:
1. `docs/PROJECT_SPEC.md` — product scope and agreed business rules.
2. `docs/BACKEND_GUIDE.md` — Laravel API architecture and backend conventions.
3. `docs/FRONTEND_GUIDE.md` — frontend architecture and UI/design rules.
4. `spec/design-system.json` — machine-readable design tokens and UI constraints.
5. `docs/USE_CASES.md` — actors, permissions, and workflows.
6. `docs/ERD.mmd` — conceptual database ERD.
7. `spec/terapublic.schema.json` — machine-readable conceptual schema.
8. `docs/IMPLEMENTATION_PLAN.md` — staged implementation order.

## Working rules
- Work only on the phase/task explicitly requested. Do not scaffold every feature in one pass.
- Treat `docs/PRODUCT_GUIDE.md` as the main description of the product. Do not redesign TeraPublic from zero or ask again about decisions already recorded there. The other documents translate the product into technical implementation details.
- Before migrations, compare the ERD and JSON schema; report and resolve inconsistencies first.
- Do not invent public datasets, facilities, APBD budget/realization values, source links, or ML predictions.
- Do not automatically train a model on every database table. ML use cases and algorithms remain undecided until the dataset audit.
- Public endpoints return published data only and never expose private information or drafts.
- Enforce authentication/authorization on the server; hiding frontend controls is not security.
- Public visitors can browse published information without login. Login is required to submit feedback, view personal feedback history, and use the RAG chatbot.
- There is one Admin role with full administrative permissions; do not add Super Admin or Reviewer roles without approval.
- Imported data may be corrected, but raw source files/source values and change history must be retained.
- The APBD source row has exactly eight dataset columns: `periode_data`, `tanggal`, `kode_opd`, `nama_opd`, `akun_jenis`, `nama_akun`, `anggaran`, and `realisasi`. Do not infer a unique business key from them; report duplicate candidates without overwriting records.
- New or corrected data follows Draft -> Validation -> Published. The current published version remains public until its replacement is validated and published.
- Ask before adding product features or changing agreed business rules.
- Prefer incremental, testable changes; report files changed, tests run, and known limitations truthfully.

## Agreed technology stack
- Frontend: existing Next.js App Router + TypeScript + Tailwind CSS + Turbopack; code is under `src/`.
- Main backend API: Laravel REST API using MVC conventions with a Service Layer and domain-oriented organization.
- Main relational database: PostgreSQL, owned by Laravel migrations/Eloquent for operational product data.
- Authentication: Laravel Sanctum first-party SPA cookie/session authentication when frontend and API share a top-level domain; configure CSRF, stateful domains, credentials, and CORS correctly.
- AI backend: separate Python service for RAG and future ML. It is not the owner of core users/regions/facilities/APBD publication workflows.
- Vector storage: PostgreSQL + pgvector is an initial option for RAG, not a final lock-in. Clarify ownership of AI-derived tables/embeddings before implementation.
- Next.js Route Handlers are optional BFF endpoints only when there is a concrete reason; do not duplicate Laravel CRUD/business rules in Next.js.

## Frontend-specific rules
- Follow `docs/FRONTEND_GUIDE.md` and `spec/design-system.json` for all UI work.
- Avoid generic AI-generated visual patterns; use the TeraPublic Civic Trust palette and specified typography.
- Library names in the design guide are options, not a request to install all dependencies. Inspect `package.json` first and add only what the scoped task needs.
- Prefer Tailwind/CSS for simple styling. Use Motion only for meaningful transitions when CSS is insufficient.
- Do not add 3D, smooth-scroll, or elaborate animation libraries to the MVP without concrete product reason and user approval.
- Use tables for records, charts for data-backed trends, and maps only when valid location data exists. Never fabricate production metrics.
