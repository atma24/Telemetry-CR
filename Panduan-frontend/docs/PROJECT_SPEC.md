# TeraPublic — Project Specification

**Status:** Draft v1.0 — planning baseline; confirm before implementation.

## 1. Product purpose
TeraPublic is a civic-tech platform for transparency of public data, monitoring of regions, public facilities and development projects, and data-driven civic insights. The pilot scope is DKI Jakarta. The product is cross-topic, not limited to facilities or accessibility.

## 2. Technology
- Frontend: Next.js App Router, TypeScript, Tailwind CSS, Turbopack.
- Backend API: FastAPI (Python).
- Main database: PostgreSQL.
- RAG: separate Python service; PostgreSQL + pgvector is the initial option.
- Machine Learning: separate Python service if/when an evidence-backed use case is selected.

Next.js uses modular organization inspired by MVC separation of concerns, but does not need to copy Laravel's exact folder structure. FastAPI owns the main backend business logic, authorization, CRUD workflows, import, validation, and publication rules. Avoid duplicating business logic between Next.js and FastAPI.

## 3. Actors and access rules
### Public visitor (no login)
- View public monitoring dashboard.
- Search and browse published datasets.
- View published region, facility, and development-project information.
- View public charts, tables, and maps when the source data supports them.

### Registered citizen (login required for these actions)
- Submit feedback or suggestions.
- View their own feedback history, statuses, and responses.
- Use the RAG chatbot.
- May also use all public browsing features.

### Admin (one role)
- Manage master data, datasets, imports, corrections, publication, feedback, knowledge sources, and audit history.
- All administrative access must be checked in FastAPI.

## 4. Core data management decisions
- Master data includes administrative regions, public facilities, and development projects.
- Facilities and projects can be added manually or imported from datasets.
- Region data can also be entered manually or imported when a source is available.
- General cross-topic datasets need flexible metadata and row storage, subject to validation against the actual MVP datasets.
- A data source should be recorded wherever applicable, including source URL, source type, licensing/attribution details, and import provenance.
- Original imported files and original source values must be preserved. Corrections are recorded as new versions/revisions; do not silently overwrite source data.
- New data follows Draft -> Validation -> Published.
- The previously published version remains public until its replacement passes validation and is published.
- Data withdrawn as invalid should be hidden from public access but retained for audit/history rather than hard-deleted by default.
- Public APIs must return published versions only.
- Avoid duplicate regions, facilities, projects, or rows during imports. Import should provide preview, column mapping, validation, accepted/rejected row counts, and an error report.

## 5. ML and RAG decisions
### Machine Learning
- ML use case and algorithm are intentionally undecided.
- Audit candidate datasets first: schema, row count, period coverage, missing values, duplicates, target/labels (if required), and whether ML adds value over descriptive statistics.
- Do not train on the entire database by default.
- Training uses a task-specific curated dataset/pipeline. Training and inference are distinct activities.

### RAG chatbot
- Login is required to use the chatbot.
- The service should ground answers in approved sources and provide references/citations.
- If sources are insufficient, the chatbot should state that limitation rather than inventing an answer.
- Keep document processing, chunking, embedding, retrieval and generation in a RAG service or clearly isolated module.

## 6. Initial domain entities
See `../spec/terapublic.schema.json` for the machine-readable conceptual schema and `ERD.mmd` for a visual overview.

Core groups:
- Identity: users.
- Reference/master data: regions/region versions, categories, data sources.
- Domain data: facilities/facility versions, projects/project versions.
- Generic data catalog: datasets, dataset versions, dataset rows, import jobs.
- Governance: feedback, feedback events, audit logs.
- RAG: knowledge documents, document chunks, chat sessions, chat messages, cited sources.

## 7. Important modeling notes
- Use stable identity records plus version records where a published version must remain visible until a correction is approved.
- Each identity should have at most one currently published version at a time.
- Store original data/provenance and versioning metadata. Define exact immutable/versioning strategy during ERD finalization.
- `dataset_rows` may use PostgreSQL JSONB for flexible tabular rows in the initial design, but validate that choice against the size and query needs of actual MVP datasets. Highly queried indicators may need normalized/domain-specific tables.
- Feedback can optionally refer to a facility or project; feedback can also be general. Do not expose another citizen's feedback history.
- Admin audit logs should record important data edits, validation/publication actions, import operations, and feedback status changes.
- RAG embeddings may use pgvector initially, but a separate vector store remains possible. Do not prematurely lock in infrastructure.

## 8. MVP boundary (provisional)
Candidate MVP:
- Authentication and authorization.
- Region master data.
- Facility and development project management.
- Dataset import, validation, versioning, and publication.
- Public catalog and monitoring for the datasets actually selected after audit.
- Feedback submission and lifecycle management.

RAG and ML may be phased according to competition requirements, dataset audit, and implementation capacity. This document does not assert that an ML use case is already selected.

## 9. Out of scope until explicitly agreed
- Final ML algorithm/use case.
- Guaranteed completeness of all DKI public facility/project data.
- Automatic ingestion from every government API.
- Super Admin or Reviewer roles; current decision is one Admin role.
- Deleting source/original data when correcting an imported record.
