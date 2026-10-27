# TeraPublic Backend Guide

> **Backend decision:** Laravel is the main API and business-logic backend. Python is a separate AI service for RAG and future ML. Next.js is the frontend; Route Handlers are optional BFF endpoints, not the source of truth for business rules.

## 1. Architecture method

Use a **modular monolith + MVC + Service Layer** for Laravel:
- **Model:** Eloquent models and relationships.
- **View:** the public/Admin UI is rendered by Next.js; Laravel API responses use Resources rather than Blade views for this product.
- **Controller:** thin API controllers validate the request through Form Requests, call services/actions, and return Resources.
- **Service/Action:** domain and application rules: import orchestration, validation, version creation, publication, feedback state transitions.
- **Repository:** optional, only when it makes query logic meaningfully reusable/testable. Do not add repository classes by default for every model just to imitate a pattern.
- **Policy/Middleware:** authorization on the server.
- **Jobs/Queues:** work that is slow or retryable, such as processing large imports or preparing AI knowledge documents.

Organize by domain where it improves clarity, while remaining idiomatic to Laravel. Do not implement all behavior in controllers or Eloquent models, and do not split into microservices unnecessarily.

## 2. Repository layout

Suggested top-level layout if Next.js and Laravel are maintained in one repository:

```text
terapublic/
├── src/                            # Existing Next.js frontend
├── public/                         # Next.js public assets
├── backend-laravel/                # Laravel API (or a separate repo)
│   ├── app/
│   │   ├── Models/
│   │   ├── Http/
│   │   │   ├── Controllers/Api/
│   │   │   ├── Requests/
│   │   │   └── Resources/
│   │   ├── Services/
│   │   │   ├── Imports/
│   │   │   ├── Publications/
│   │   │   └── Feedback/
│   │   ├── Actions/
│   │   ├── Policies/
│   │   ├── Jobs/
│   │   ├── Enums/
│   │   └── Support/
│   ├── routes/api.php
│   ├── database/migrations/
│   ├── database/factories/
│   ├── database/seeders/
│   ├── tests/Feature/
│   └── tests/Unit/
├── ai-service/                     # Python RAG / future ML service
│   ├── app/
│   ├── tests/
│   ├── pyproject.toml
│   └── README.md
├── docs/
└── package.json
```

A separate repository for Laravel or Python is also valid. Do not move the existing Next.js project just to match this example. If the projects are separate repositories, maintain the same written API contract and ownership boundaries.

## 3. Laravel domain organization

Use predictable names and keep related files grouped by domain. Example for `regions`:

```text
app/
├── Models/Region.php
├── Models/RegionVersion.php
├── Http/Controllers/Api/Regions/RegionController.php
├── Http/Requests/Regions/StoreRegionRequest.php
├── Http/Requests/Regions/UpdateRegionRequest.php
├── Http/Resources/RegionResource.php
├── Services/Regions/RegionService.php
├── Policies/RegionPolicy.php
└── Jobs/Imports/ImportRegionsJob.php
```

The exact namespace/folder can be made more domain-oriented if it stays autoloadable and consistent. Apply the same convention to facilities, APBD, datasets, publications, and feedback. Do not create empty modules for every future feature up front.

### Request flow

1. Request enters `routes/api.php`; Laravel applies the single `/api` prefix automatically.
2. Middleware checks authentication when required and applies rate limits where appropriate.
3. A Form Request validates shape and input constraints.
4. Controller calls a Service/Action for the business operation.
5. Service coordinates Eloquent transactions, versioning, provenance, and jobs.
6. API Resource returns a stable JSON response.
7. Feature/Unit tests cover access rules and business invariants.

## 4. Main Laravel modules

### Auth and users
- Citizen registration/login/logout/current user.
- One Admin role, provisioned securely; avoid public self-registration as Admin.
- Server-side authorization using middleware and policies.
- Password hashing via Laravel's password hashing tools.

### Regions
- Create/import/update region identity and versions.
- Validate hierarchy and official codes when available.
- Prevent duplicate identities and invalid parent-child relationships.

### Facilities and APBD
- Support manual entry and import for facilities. APBD rows accept manual entry and CSV/XLSX imports against the eight source columns: `periode_data`, `tanggal`, `kode_opd`, `nama_opd`, `akun_jenis`, `nama_akun`, `anggaran`, and `realisasi`.
- Store structured fields needed for filtering/reporting in typed columns.
- Store less common/source-specific attributes in JSONB only when justified by actual datasets.
- Retain original imported values and source references.
- Keep APBD amount columns as exact fixed-precision decimals; do not calculate totals or assign project-level meaning unless a separately approved source supports it.
- Treat a stable source fingerprint as a non-unique duplicate candidate. Report and skip matching imports; do not overwrite an existing row or add a guessed combination unique constraint.

### Datasets and import jobs
- Dataset catalog and version records.
- Upload and safely store CSV/XLSX files outside executable/public paths.
- APBD imports use normalized header names for the eight columns; there is no preview or manual field-mapping endpoint.
- `POST /api/admin/apbd/import` stores the original file privately and queues row processing in batches of 500. It returns `202 Accepted` with the import job ID and a status URL; `GET /api/admin/apbd/imports/{id}` provides row counts and progress. The HTTP upload limit remains 20 MB.
- Validate row schema, types, required fields, region/category references and duplicates.
- Record accepted/rejected row counts and row-level errors.
- Run a database queue worker for imports: `php artisan queue:work database --timeout=3600 --tries=3`. Keep `DB_QUEUE_RETRY_AFTER` greater than the worker timeout (the default is 3660 seconds).
- Import retries are idempotent: duplicate candidates are reported and skipped, while rows already written by the same import job are not inserted again.

### Publication/versioning
- State flow: `draft` -> `validating` -> `published`; prior version may become `superseded`; withdrawn records may be `archived`/unpublished while retained for audit.
- Keep the current published version available until a new version passes validation and publication.
- Use transactions to switch current published version safely.
- Enforce at most one current published version per identity/dataset through database constraints or a clear current-version strategy, not only UI checks.
- Never expose drafts or validation-error details to public endpoints.

### Feedback and audit
- Feedback statuses: `submitted`, `in_review`, `in_progress`, `resolved`, `rejected`.
- Rejected feedback must have an explanatory reason.
- A feedback item may be general or reference at most one target among facility, APBD row, region, or dataset; encode this rule in validation and preferably a database constraint.
- Feedback history is append-only in `feedback_events`.
- Audit significant edits, imports, validation/publication, withdrawal, and feedback status changes.

## 5. Authentication integration with Next.js

Preferred default for a first-party web app: **Laravel Sanctum SPA authentication using secure cookie/session auth**, not a custom JWT implementation by default. Official Sanctum guidance says the SPA and API should share a top-level domain, though they can use different subdomains. Configure stateful domains, credentials, CSRF initialization, and CORS accordingly.

For direct API clients such as Postman, active Admins can request a Sanctum personal access token with `POST /api/admin/login` using their email and password. The plaintext token is returned once, expires after eight hours, and can be revoked by its holder with `DELETE /api/admin/token`. Send it as `Authorization: Bearer <token>`. Sanctum stores only the token hash. This does not replace cookie/session authentication for the first-party SPA.

Example production domain arrangement:
- `app.example.id` — Next.js.
- `api.example.id` — Laravel.

The actual domains are not decided yet. For local development, configure localhost hosts/ports and Sanctum stateful domains deliberately. If the deployment cannot share a top-level domain, revisit the authentication architecture before implementation.

Security rules:
- Use `HttpOnly`, `Secure` in production, and appropriate `SameSite` cookie settings.
- Use Laravel's CSRF protection for stateful mutations.
- Do not store long-lived session credentials in `localStorage`.
- Configure allowed CORS origins precisely; do not use wildcard origins with credentials.
- Send requests with credentials and appropriate `Accept: application/json` / origin headers.
- Use middleware plus policies for Admin and resource ownership.
- Rate-limit login, registration, feedback submission, and chatbot requests.
- Use Laravel database session storage if server-side database-backed sessions are desired; the generated `sessions` table must use the UUID-compatible `user_id` type if the User primary key is UUID.

Install/configure Sanctum using the official instructions for the chosen Laravel version. In current Laravel docs, `php artisan install:api` is the documented API/Sanctum setup path; confirm the generated files against the project before running commands.

## 6. API contract

Use the unversioned external API (`/api/...`) and document it using Laravel's generated OpenAPI tooling or an approved API specification. Keep response shapes and error formats consistent. Example endpoint families (indicative, not a full locked endpoint list):

- Public: `GET /api/dashboard`, `GET /api/datasets`, `GET /api/regions`, `GET /api/facilities`, `GET /api/apbd`.
- Auth: register/login/logout/current user according to Sanctum SPA flow; `POST /api/admin/login` issues a short-lived Admin bearer token and `DELETE /api/admin/token` revokes the current token.
- Admin: CRUD and import/validation/publication endpoints for reference/domain data.
- APBD Admin: `POST /api/admin/apbd`, `POST /api/admin/apbd/import`, `GET /api/admin/apbd/imports/{id}` for queue status/progress, `GET /api/admin/apbd/imports/{id}/errors` for the private row-error report, and record validation/publication routes.
- Feedback: authenticated citizen create/list-own/show-own; Admin list/review/respond/update status.
- RAG: Laravel validates user/session and forwards a scoped request to the private Python AI service, then stores/returns the chat result and citations according to the documented ownership strategy.
- ML: only add inference endpoints after an ML use case is approved.

Do not create duplicate endpoints in Next.js without a BFF need. Keep the frontend API client in `src/lib/api/` focused on requests, response types, error mapping, and credentials.

## 7. Python AI service boundary

Python service handles AI-specific work only:
- RAG document parsing, chunking, embeddings, retrieval, generation and evidence/citation payload.
- Future ML data preparation, training, evaluation, model artifact/version management, and inference after audit.

Laravel remains the authority for the authenticated user, role, public publication state, and application records. Keep the Python service private to the application network where possible. Authenticate service-to-service calls, use timeouts, validate payloads, avoid exposing internal error details, and define data ownership before allowing Python to write any database table.

For RAG, do not index drafts or private citizen feedback as public knowledge unless that use is explicitly approved. Store the source/version metadata needed to show citations and invalidate stale chunks after a source changes.

## 8. Environment and secrets

- Keep secrets in `.env`; commit only `.env.example` with non-secret placeholders.
- Do not prefix server secrets with `NEXT_PUBLIC_`.
- Use separate environment variables for frontend public API base URL, Laravel database connection, AI-service base URL, and service credentials.
- Never log passwords, cookies, session IDs, API keys, full private feedback, or unredacted auth headers.
- Store uploaded source files in private storage with size/type validation; public serving requires a deliberate publication-safe path.

## 9. Testing expectations

- Feature tests: public-only published reads, citizen ownership, Admin policies, validation, import conflict handling, publication/version-switch transactions, feedback rejection reason, and audit trails.
- Unit tests: import mapping, data normalization, version selection and business services.
- AI service tests: source citations, insufficient-evidence behavior, input limits, timeouts, and access boundary.
- Frontend tests/checks: typed API contract, loading/empty/error states, lint, typecheck, responsive layout and accessibility.

## 10. References

Use the version-matched official docs when implementing:
- Laravel routing/API: https://laravel.com/docs/13.x/routing
- Laravel Sanctum: https://laravel.com/docs/13.x/sanctum
- Next.js Backend for Frontend guidance: https://nextjs.org/docs/app/guides/backend-for-frontend
- Next.js Route Handlers: https://nextjs.org/docs/app/getting-started/route-handlers

This is an architectural guide, not a command to implement all modules at once. Follow `docs/IMPLEMENTATION_PLAN.md` and work phase by phase.
