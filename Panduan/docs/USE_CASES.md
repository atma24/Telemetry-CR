# TeraPublic — Use Cases and Workflows

## Actors
1. **Public Visitor** — no login required for published public information.
2. **Registered Citizen** — all public use cases plus feedback and chatbot.
3. **Admin** — one role with full administration privileges.

## Use-case matrix
| Actor | Use case | Auth | Result |
|---|---|---|---|
| Public Visitor | View monitoring dashboard | No | Published indicators/charts only |
| Public Visitor | Search/browse catalog | No | Published dataset metadata and public content |
| Public Visitor | View region/facility/APBD records | No | Published details only |
| Registered Citizen | Register/login/logout/current-user | Yes where applicable | Authenticated account/session |
| Registered Citizen | Submit feedback | Yes | Feedback created with `submitted` status |
| Registered Citizen | View feedback history | Yes | Only own feedback, history and Admin responses |
| Registered Citizen | Use RAG chatbot | Yes | Grounded answer with citations where available |
| Admin | Manage regions | Admin | Draft/versioned region records |
| Admin | Manage facilities | Admin | Draft/versioned facility records |
| Admin | Manage APBD records | Admin | Draft rows from the eight source columns; CSV/XLSX import and revision workflow |
| Admin | Manage datasets | Admin | Catalog metadata and versions |
| Admin | Import APBD data | Admin | Queued CSV/XLSX import, progress, validation results and Admin-only error report |
| Admin | Validate/publish data | Admin | Approved version becomes public |
| Admin | Correct/withdraw published data | Admin | History retained; current public version changes only according to publication rules |
| Admin | Manage feedback | Admin | Status changes/responses recorded; rejected feedback must have a reason |
| Admin | Manage RAG knowledge sources | Admin | Approved sources processed/indexed |
| Admin | View audit logs | Admin | Trace of important administrative actions |

## Import and publication workflow
1. Admin uploads CSV/XLSX (up to 20 MB); the original file is retained in private storage.
2. For APBD, the API returns an import job ID immediately; a queue worker reads normalized headers matching the eight source columns, with no preview or manual mapping step.
3. Admin polls the job status endpoint for rows processed and accepted/rejected counts.
4. The worker validates rows in bounded batches, records rejected rows and reasons, and saves valid APBD rows as drafts with source/import provenance.
5. Admin retrieves the private error report when rows were rejected.
6. Admin validates and publishes the drafts.
7. Public APIs serve published versions only.

## Correct an already published record
1. Admin creates a correction/new version; old public data remains visible.
2. Original source values and import source are retained.
3. Corrected version passes validation.
4. On publication, the new version becomes current and the previous version is marked superseded/retained for history.
5. Database transaction/constraint prevents conflicting current published versions.

## Feedback workflow
1. Citizen logs in and submits feedback.
2. Feedback can be general or reference at most one target: facility, APBD record, region, or dataset.
3. Initial status is `submitted`.
4. Admin moves status to `in_review`, `in_progress`, `resolved`, or `rejected` as appropriate.
5. Rejection requires a reason.
6. Each status/treatment change is stored in `feedback_events`.
7. Citizen can view only their own feedback and event history.

## RAG workflow
1. Citizen logs in and opens their own chat session.
2. Laravel authenticates the user and checks session ownership.
3. Laravel calls the private Python AI service with an authorized query/context.
4. RAG retrieves from approved knowledge sources and returns answer + source identifiers/snippets.
5. Chat response and citation references are stored according to the agreed data-ownership contract.
6. If evidence is insufficient, response says so rather than inventing an answer.

## Open design decisions
- Actual dataset MVP and dataset-specific typed columns, pending audit.
- Exact storage ownership for document chunks/embeddings between PostgreSQL/pgvector and the Python service.
- ML use case/algorithm pending dataset audit.
- Final deployment domains and Sanctum stateful-cookie configuration.
