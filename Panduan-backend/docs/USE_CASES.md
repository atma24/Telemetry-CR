# TeraPublic — Use Cases

## Actors
1. **Public Visitor** — no account required for public information.
2. **Registered Citizen** — account required for feedback and chatbot.
3. **Admin** — single administrative role.

## Use-case matrix
| Actor | Use case | Authentication | Result |
|---|---|---|---|
| Public Visitor | View monitoring dashboard | No | Published indicators and charts |
| Public Visitor | Search/browse dataset catalog | No | Published dataset metadata and public content |
| Public Visitor | View region/facility/project details | No | Published details only |
| Registered Citizen | Register/login/logout | Yes where applicable | Authenticated account/session |
| Registered Citizen | Submit feedback | Yes | Feedback record created with initial status |
| Registered Citizen | View own feedback history | Yes | Only the current user's feedback and status history |
| Registered Citizen | Use RAG chatbot | Yes | Grounded answer with citations where available |
| Admin | Manage regions | Admin | Draft/versioned region records |
| Admin | Manage facilities | Admin | Draft/versioned facility records |
| Admin | Manage development projects | Admin | Draft/versioned project records |
| Admin | Manage cross-topic datasets | Admin | Dataset metadata and versions |
| Admin | Import data | Admin | Import job, validation results and error report |
| Admin | Validate/publish data | Admin | Published version becomes public |
| Admin | Correct/withdraw published data | Admin | Version/change history retained; public version updated only by publication rules |
| Admin | Manage feedback | Admin | Status updates and responses recorded |
| Admin | Manage RAG knowledge sources | Admin | Documents processed/indexed for retrieval |
| Admin | View audit logs | Admin | Trace of administrative actions |

## Key workflows
### Import and publication
1. Admin selects a target type and uploads a CSV/XLSX file or supplies a manual entry.
2. System previews content and lets Admin map columns where applicable.
3. System validates required fields, data types, references to regions/categories, and possible duplicates.
4. System records rejected rows and validation errors.
5. Valid data is stored as a draft version, with source/import provenance.
6. Admin validates and publishes the version.
7. Public endpoints serve the published version only.

### Correct a published record
1. Admin creates a correction/new version.
2. Original source values and existing public version remain preserved.
3. Corrected version passes validation.
4. On publication, the new version becomes current and the previous version is marked superseded/retained for history.

### Citizen feedback
1. Citizen must log in to submit feedback.
2. Feedback may refer to a facility/project or be general.
3. Initial status is set by backend policy.
4. Admin reviews and updates status/responds.
5. Each status/treatment change is stored in feedback history.
6. Citizen can only view their own feedback and its history.

### RAG chat
1. Citizen logs in and creates/opens their own chat session.
2. RAG retrieves from approved knowledge sources.
3. Answer includes citation/source records where applicable.
4. If there is not enough evidence, the answer must say so.
5. Chat session/message endpoints enforce ownership checks.

## Initial feedback statuses
Provisional: `submitted`, `in_review`, `in_progress`, `resolved`. A `rejected` state and its required reason should be finalized before implementation.

## Use cases intentionally undecided
- ML-specific flows, because the ML objective depends on the dataset audit.
- Automated data ingestion schedules.
- Detailed notification channels for feedback; do not add without agreement.
