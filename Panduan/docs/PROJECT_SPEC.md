# TeraPublic — Project Specification

**Status:** APBD module implementation baseline. Architecture uses Laravel + Next.js + Python AI service. Read `PRODUCT_GUIDE.md` for the full product explanation; ERD and JSON schema reflect the APBD source columns and migration.

## 1. Product purpose
TeraPublic is a civic-tech platform for transparency of public data, monitoring of regions, public facilities, and source-backed APBD records. The APBD module uses the eight columns present in its source dataset and does not infer project-level records or budget aggregates. The pilot scope is DKI Jakarta. For the full product vision, user journeys, public/admin screens, feature behaviour, MVP acceptance checklist, and explicit non-goals, read `PRODUCT_GUIDE.md` first.

## 2. Technology and service boundaries
- **Frontend:** existing Next.js App Router, TypeScript, Tailwind CSS, and Turbopack; code under `src/`.
- **Main backend:** Laravel REST API using MVC conventions plus a Service Layer and domain-oriented modules.
- **Main database:** PostgreSQL. Laravel migrations/Eloquent own operational product tables and business workflows.
- **Authentication:** Laravel Sanctum first-party SPA cookie/session authentication is the preferred default when the SPA and API share a top-level domain. CSRF, trusted/stateful domains, credentials, and CORS must be configured correctly.
- **AI backend:** separate Python service for RAG and, later, ML. It does not duplicate Laravel user, facility, APBD, or publication business logic.
- **Vector storage:** PostgreSQL + pgvector is an initial candidate. Decide exact data ownership and deployment details when implementing RAG.

Next.js owns page rendering, UI state, and API calls. Laravel owns API authorization, domain rules, data operations, imports, validation, publication, feedback, and audit behavior. Next.js Route Handlers may be used as a Backend for Frontend (BFF) when justified, but are not a second copy of the primary backend. Python owns AI-specific processing and inference workflows only.

## 3. Actors and access rules
### Public visitor (no login)
- View public monitoring dashboard.
- Search and browse published datasets.
- View published region, facility, and APBD information.
- View public charts, tables, and maps only where source data supports them.

### Registered citizen
- Can perform all public browsing actions.
- Must log in to submit feedback or suggestions.
- Can view their own feedback history, statuses, and responses only.
- Must log in to use the RAG chatbot; chat sessions are private to their owner.

### Admin (one role)
- Manage master data, datasets, imports, corrections, publication, feedback, knowledge sources, and audit history.
- All administrative access is enforced by Laravel middleware/policies, not only by frontend visibility.

## 4. Core data management decisions
- Managed records include administrative regions, public facilities, and APBD dataset rows.
- Regions and facilities can be manually entered or imported; APBD rows can be manually entered or imported from supported CSV/XLSX files.
- General cross-topic datasets use metadata and flexible row storage only where appropriate. Validate JSONB usage against actual MVP data size and query needs.
- Record source attribution and import provenance where applicable: source name/URL, source type, license/attribution, import job, raw file/source row.
- Original imported files and source values must be retained. Corrections create a new version/revision with an audit trail; do not silently overwrite source values.
- New data follows Draft -> Validation -> Published.
- The previously published version remains public until its replacement is validated and published.
- Data withdrawn as invalid must be hidden publicly but retained for audit/history by default.
- Public API responses return published versions only.
- APBD imports accept CSV/XLSX with normalized source-column headers, validate rows, report duplicate candidates, and return accepted/rejected counts with an Admin-only error report. This API imports directly; it does not currently provide a preview or manual column-mapping step.
- APBD rows retain the source's eight columns: `periode_data`, `tanggal`, `kode_opd`, `nama_opd`, `akun_jenis`, `nama_akun`, `anggaran`, and `realisasi`.
- Duplicate fingerprints are indexed as non-unique candidates only. Do not infer a unique combination key; imports report and skip a matching row rather than overwrite it.

## 5. Dataset, RAG, and ML decisions
### Dataset audit
Audit candidate datasets for schema, row count, periods, nulls, duplicates, identifier quality, source/license, geometry/coordinates, and query needs. An APBD table row is not evidence of an individual development project; preserve its original values and do not invent per-project records or totals.

### Machine Learning
- ML use case and algorithm are intentionally undecided.
- Audit candidate datasets and establish a measurable problem before selecting a model.
- Do not train on the entire database by default.
- Training uses task-specific curated data. Training and inference are separate activities.
- Python owns ML-specific pipelines, evaluation, model artifacts, and inference APIs; Laravel may request inference through a defined service API once a use case is approved.

### RAG chatbot
- Login is required to use the chatbot.
- Answers must be grounded in approved sources and cite retrieved evidence where available.
- If evidence is insufficient, the assistant must state that rather than inventing an answer.
- Laravel owns user authorization, conversation ownership, and application-level access rules. Python handles document parsing/chunking/embedding/retrieval/generation as the AI service contract specifies.
- Define whether document chunks/embeddings are stored in PostgreSQL + pgvector or a separate vector store before implementation. Do not let two services mutate the same tables without documented ownership.

## 6. Initial domain entities
See `../spec/terapublic.schema.json` for machine-readable conceptual entities and `ERD.mmd` for a visual overview.

- Identity/access: users, Laravel server-side sessions.
- Reference data: regions/region versions, categories, data sources.
- Domain data: facilities/facility versions and APBD records with source provenance and correction history.
- General catalog: datasets, dataset versions, dataset rows, import jobs.
- Governance: feedback, feedback events, audit logs.
- RAG: knowledge document metadata, document chunks/embedding references, chat sessions, chat messages, cited sources.

## 7. MVP boundary (provisional)
- Identity/access with one Admin role.
- Region master data.
- Facility and APBD record management.
- Dataset import, validation, versioning, publication, and provenance.
- Public catalog/monitoring for datasets selected after audit.
- Feedback submission, status handling, and private history.
- RAG is a product target; sequence its implementation after the data and access foundations are stable, unless competition delivery constraints require an earlier demonstrator.

ML remains a later decision until the dataset audit identifies a valid use case.

## 8. Out of scope until explicitly agreed
- Final ML use case/algorithm.
- Guaranteed completeness of all DKI public facility/APBD data.
- Automatic ingestion from every government API.
- Super Admin or Reviewer roles.
- Unapproved features such as notification channels, crowdsourced verification workflows, inferred APBD totals, or project-progress claims.
