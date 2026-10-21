# TeraPublic — Product Guide

**Product name:** TeraPublic  
**Descriptor:** Public Facilities & Accessibility
**Document role:** Main explanation of what TeraPublic is, who it serves, how it should behave, and which features we intend to build.  
**Status:** Product baseline for planning and implementation; dataset-specific details remain subject to source-data audit.

> AI coding agents must read this document before implementing screens, workflows, database changes, or APIs. Technical docs describe how to build the product; this document explains what product we are building and why.

---

## 1. Product in one paragraph

TeraPublic is a civic-tech web platform that helps people understand public information through a clear, searchable, and source-attributed view of data about regions, public facilities, development projects, and other public-interest topics. It begins with DKI Jakarta as the pilot area while keeping the product structure cross-topic. People can freely browse published monitoring, datasets, and supported public information without creating an account. People must sign in to send feedback, track their own feedback, or use the evidence-based RAG chatbot. An Admin manages data sources, enters or imports data, validates and publishes it, handles feedback, and manages the approved knowledge sources used by the chatbot.

## 2. The problem TeraPublic addresses

Public data can be difficult to use even when it is technically available. Information may be scattered across portals, spreadsheets, reports, and agency sources; users may have trouble understanding what a number means, which region or period it covers, or when it was updated. A raw dataset alone often does not answer practical questions such as:

- What public information is available about a region or topic?
- What does a particular public indicator actually show?
- Which source, period, and definition support the information?
- Is a location or project detail actually present in the available data, or is it unknown?
- Where can a citizen send feedback and later see what happened to it?

TeraPublic aims to make public information easier to discover, compare, and interpret, while clearly showing the source and limits of the available data.

## 3. Product vision and principles

### Vision

Make public information easier to discover, understand, and use for civic awareness and accountability.

### Product principles

1. **Public browsing by default.** Published public information is viewable without login.
2. **Traceable information.** Show the source, period, update date, units, and definitions when available.
3. **No invented facts.** Do not make up dataset records, locations, budgets, project progress, accessibility features, or model predictions.
4. **Cross-topic, not unfocused.** The product can cover multiple public-interest topics, but the MVP only promises what the selected sources actually support.
5. **Clear distinction between actual, derived, and unknown.** A sourced value, an aggregation computed by the application, and unavailable information must not be presented as the same thing.
6. **Responsible correction.** Preserve original import/source values and the history of corrections.
7. **Human publication control.** New or corrected data is not public until it has been validated and published.
8. **Private citizen activity.** Feedback history and chat sessions are private to the signed-in user, unless a future, approved requirement says otherwise.
9. **Useful over decorative.** UI choices should improve discovery, comprehension, accessibility, or task completion rather than add visual effects without purpose.

## 4. Intended users

### 4.1 Public visitor — no account

A person who wants to explore public information. They can:
- open the public site and monitoring dashboard;
- browse/search published datasets;
- view available region, facility, and development-project information;
- inspect charts, tables, and maps when supported by the source data;
- read source attribution, period, update date, and limitations where available.

They cannot submit feedback or use the chatbot until they sign in.

### 4.2 Registered citizen

A person with an account. They can do everything a public visitor can, plus:
- submit feedback or a suggestion;
- inspect their own feedback history, status changes, and Admin responses;
- use the RAG chatbot and view their own conversation sessions.

A registered citizen must not be able to read another citizen's feedback or chat history.

### 4.3 Admin

There is one Admin role with full administrative access. Admin responsibilities include:
- maintaining reference regions and categories;
- adding or importing facility/project data;
- managing dataset catalog metadata and import jobs;
- reviewing validation errors and duplicates;
- saving drafts, validating, publishing, superseding, or withdrawing data;
- responding to and updating feedback;
- managing approved RAG knowledge sources;
- reviewing audit logs and data-change history.

Do not add separate Super Admin, Reviewer, or agency roles unless explicitly approved later.

## 5. Product scope and geography

### Initial pilot

The pilot starts with DKI Jakarta to make data inspection and integration manageable.

### Topic scope

TeraPublic is cross-topic. Potential categories may include regional statistics, public facilities, infrastructure/development, transport, environment, education, economy, and other public-interest subjects when suitable official data exists. This list is illustrative; it is not a promise that every category will have complete coverage in the MVP.

### Data availability constraint

The existence of a portal or dataset does not guarantee it contains individual locations, coordinates, per-project progress, budgets, contractors, accessibility attributes, or a full time series. Build only fields and visualizations that the inspected source supports. If information is missing, say it is unavailable rather than infer it as fact.

## 6. Public-facing experience

### 6.1 Public home page (`/`)

Purpose: explain what TeraPublic is and guide visitors to the public data experience.

Expected content:
- a clear explanation of public transparency and civic insights;
- direct navigation to monitoring and dataset exploration;
- selected public indicators only if reliable published data is available;
- concise explanation of how source data and update dates are displayed;
- links to relevant parts of the product.

Do not make the page a generic marketing template with fabricated statistics, generic feature cards, or a chatbot that pretends to have knowledge before its data sources are configured.

### 6.2 Monitoring dashboard (`/dashboard`)

Purpose: make the available public data easier to understand at a glance.

Expected behaviour:
- show summary indicators computed from published records or directly sourced indicators;
- offer filters such as topic, region, and period where the dataset supports them;
- use charts for supported trends/comparisons, tables for record-level information, and maps only when valid coordinate/location data exists;
- make units, periods, source, last-updated date, and definitions discoverable;
- distinguish source-provided indicators from application-calculated aggregations;
- handle unavailable, incomplete, or stale data honestly.

Dashboard content is determined after dataset audit. Do not promise a chart, map, trend, comparison, or KPI before confirming its input fields exist.

### 6.3 Public data catalog (`/datasets` and `/datasets/[slug]`)

Purpose: help people find and understand the source datasets behind public monitoring.

Catalog list should support search and reasonable filters over available metadata, such as category, source, region coverage, and period. Dataset detail should show, where known:
- title and description;
- source organization and source URL;
- category/topic;
- covered geography and time period;
- units/definitions or data dictionary if available;
- date imported/updated and publication status where public-facing;
- license/attribution information;
- a preview of published data when appropriate.

Never expose drafts, rejected rows, internal validation notes, private file paths, or admin-only metadata through public endpoints.

### 6.4 Region information

Purpose: provide a way to understand published data grouped by administrative region.

Use the hierarchy Province → city/regency or administrative city → district → urban village when supported. Region detail pages may aggregate indicators and show related facilities/projects only if valid, published records support those views. RW/RT are not separate master-region levels in the initial scope.

Store internal IDs and official region codes when available. Do not use a mutable region name as the database primary key.

### 6.5 Public facilities (`/facilities` and `/facilities/[id]`)

Purpose: show facility records that are actually supported by imported or Admin-entered data.

Potential fields include name, facility category, address, administrative region, coordinates, source, period/last-updated date, and supported attributes. These details are not all guaranteed to exist for every facility type.

- Manual entry and import are both supported for Admins.
- Use map markers only when valid coordinates exist.
- Accessibility details such as ramps, lifts, accessible toilets, or parking must only be shown when the source or a clearly attributed manual record supports the claim.
- Do not turn every aggregate facility statistic into individual facility records.

### 6.6 Development projects (`/projects` and `/projects/[id]`)

Purpose: surface project information that can be traced to an appropriate source or clearly attributed Admin entry.

Potential fields include project name, responsible agency, location(s), planned dates, status, source, budget, realization, and progress. Optional fields remain absent when not available; their values must not be fabricated.

A project can cover more than one region. In the conceptual data model, represent this relation through a project-version-to-region join table where needed. Separate aggregate development indicators from individual project records.

### 6.7 Feedback (`/feedback` or equivalent account area)

Purpose: allow signed-in citizens to send feedback and follow the recorded handling of their own submission.

Citizen flow:
1. Visitor chooses to submit feedback and is asked to sign in or register.
2. Citizen writes a subject and message and may attach the feedback to at most one supported target: general, a region, a facility, a project, or a dataset.
3. System creates a feedback record with `submitted` status.
4. Citizen can later see its status, status history, and Admin responses.

Admin flow:
1. Admin reviews incoming feedback.
2. Admin updates status and optionally gives a response.
3. A rejected feedback item must include a reason.
4. Each meaningful status change/response is recorded in feedback history.

Feedback is not automatically published as public content. The citizen sees only their own feedback; Admin can manage all feedback.

Initial statuses: `submitted`, `in_review`, `in_progress`, `resolved`, `rejected`.

### 6.8 RAG chatbot (authenticated)

Purpose: help citizens ask natural-language questions about approved public information and receive answers supported by retrievable evidence.

Expected behaviour:
- requires login;
- uses an approved knowledge base derived from approved public documents and/or datasets;
- provides citations or source references wherever retrieval supports them;
- explains when the available evidence is insufficient;
- does not pretend to know data that is not in approved sources;
- keeps each citizen's chat history private to that account.

Admin manages source documents and sees processing/indexing status. Laravel owns application authentication, authorization, and chat-session ownership. Python owns AI-specific parsing, chunking, embedding, retrieval, and generation. Exact vector storage ownership must be documented before implementing persistence.

RAG is a planned product feature, but it should be implemented after core data publication and access controls are stable. A demo may be brought forward only through an explicitly scoped task.

### 6.9 Machine Learning

Machine Learning is not yet a defined product feature with a committed prediction or classification target. Its use case must come from dataset audit and a concrete need.

Do not automatically train on all database records. A future ML flow must identify:
- the question/task the model is meant to solve;
- suitable input and target fields;
- enough relevant data and historical depth where required;
- leakage, missing values, representativeness, and evaluation concerns;
- a baseline and metrics to assess whether ML adds value.

Until that audit exists, do not display mock prediction outputs or implement an arbitrary algorithm just to claim that the product uses ML.

## 7. Admin experience

### 7.1 Admin dashboard

Provide a practical administrative overview, such as counts of drafts awaiting validation, recent import outcomes, published dataset counts, and feedback awaiting review. Counts must derive from application records; do not hardcode pretend operational values.

### 7.2 Data management

Admin-facing modules:
- Regions
- Public facilities
- Development projects
- Dataset catalog and imports
- Validation/publication
- Feedback
- RAG knowledge sources (later phase)
- Audit history

Each management view should provide appropriate search/filter, row actions, clear empty/error/loading states, and forms with validation. Use tables for record management rather than decorative cards for every row.

### 7.3 Manual entry and import

Facilities and projects can be entered manually or imported. Regions and general datasets may also be manually maintained/imported as their source structure requires.

Import flow:
1. Admin selects the import target and file/source.
2. System saves source metadata and a traceable import job.
3. System previews data and maps columns when necessary.
4. System validates required fields, types, parent-region/category references, duplicate candidates, and source details.
5. System retains original file/source values and identifies accepted/rejected rows with readable error reasons.
6. Valid candidates become a draft version, not public data.
7. Admin validates and publishes explicitly.

On a repeat import, compare stable external identifiers or a deliberate matching strategy. Never blindly insert every imported row as a new business entity.

### 7.4 Corrections and publication

- Raw import/source values must remain traceable.
- Corrections create a new version/revision and audit record.
- Current public version remains visible while a replacement is drafted or validated.
- New version becomes current only when publication succeeds.
- Withdrawn/invalid published records are hidden from public endpoints but their history is retained for audit by default.
- Protect publication transitions against conflicting current published versions.

### 7.5 Auditability

Record important operations such as creation, correction, import, publication, withdrawal, feedback status changes, and knowledge-source changes. Audit data should capture actor, action, entity reference, time, and relevant before/after summary without leaking secrets or unnecessary personal data.

## 8. Main end-to-end journeys

### Journey A — public visitor explores a dataset
1. Opens TeraPublic without an account.
2. Opens monitoring or dataset catalog.
3. Filters by topic, region, or period when supported.
4. Opens a dataset/facility/project detail.
5. Checks source, period, last update, units, and caveats.

Success: visitor can understand what is shown and where it came from without signing in.

### Journey B — Admin publishes imported data
1. Admin signs in.
2. Opens the relevant management module.
3. Imports a file or enters a record manually.
4. Previews and validates the data.
5. Reviews duplicate/invalid-row reports.
6. Saves draft and validates it.
7. Publishes the version.
8. The new version becomes visible through public APIs; old versions remain traceable.

Success: only approved data becomes public and the source plus change history can be traced.

### Journey C — citizen submits feedback
1. Citizen selects a feedback action.
2. If unauthenticated, they register/sign in.
3. Citizen submits a general or target-linked message.
4. Admin reviews and updates the status/responds.
5. Citizen returns to their own feedback history to read the latest status and response.

Success: a citizen can follow the recorded handling of their own submission without accessing another user's information.

### Journey D — citizen asks the RAG chatbot
1. Citizen signs in and opens the chatbot.
2. Citizen asks a question about approved public information.
3. Laravel verifies session and chat ownership before calling the internal Python AI service.
4. Python retrieves relevant evidence and returns an answer with source references when available.
5. If no adequate evidence exists, the answer communicates that limitation.

Success: the answer is traceable to approved information, and unsupported claims are not invented.

## 9. Information architecture

### Public navigation
- Home
- Monitoring
- Explore datasets
- Regions (if useful for the selected MVP data)
- Facilities (if supported by the selected datasets)
- Development projects (if supported by appropriate sources)
- Login/Register

Authenticated citizen area:
- My feedback and status history
- Chatbot and personal chat sessions

### Admin navigation
- Overview
- Regions
- Facilities
- Development projects
- Dataset catalog/imports
- Drafts and validation/publication
- Feedback management
- RAG knowledge sources (later phase)
- Audit log

Avoid exposing an empty navigation item for a feature that has not been implemented. UI routes can be introduced incrementally.

## 10. MVP and later phases

### MVP foundation
- Next.js public and Admin shells consistent with the design guide.
- Laravel API, authentication/authorization, and PostgreSQL migrations.
- Region master data.
- Manual/import flows for selected facilities and development project data.
- Dataset catalog, versioning, provenance, validation, and publication.
- Public catalog and monitoring views driven by a small, audited set of DKI Jakarta datasets.
- Account-based feedback and private feedback history.
- Audit trail for important administrative changes.

### Later/after the foundation
- RAG knowledge ingestion and authenticated chatbot with citations.
- More source integrations and scheduled ingestion, if requested and feasible.
- Additional datasets/topics and advanced comparisons.
- ML after an evidence-based use case is chosen.
- Advanced spatial queries if a real need emerges; basic markers can use latitude/longitude initially.

These phases may be reordered for a competition demo, but only with a deliberately limited scope. The system must not present unimplemented features as complete.

## 11. Product acceptance checklist

A working MVP should satisfy the following:

- A visitor can browse published public information without logging in.
- A visitor cannot read draft/private records through the API.
- Admin can create a region/facility/project manually or import supported data.
- The import flow reports accepted and rejected rows and their reasons.
- Imported source values and important changes remain traceable.
- Admin can validate and publish a draft; only the published version is publicly visible.
- Publishing a correction does not expose the draft early or lose the previous version history.
- A citizen must sign in to submit feedback and can only see their own feedback history.
- Rejection of feedback requires a reason and is recorded.
- The frontend shows source and date/period information wherever known and does not invent missing information.
- No ML output is shown until a real model/use case is approved and evaluated.

## 12. Explicit non-goals unless approved

- A platform that claims every dataset from every government agency is automatically synchronized.
- A live project-tracking system if the source does not publish current project-level status.
- Automatic crowdsourced public verification, public voting, or publicizing every feedback item.
- A public chatbot available without login (current decision requires login).
- ML predictions without audited data, defined targets, and evaluation.
- Additional administration roles beyond one Admin.
- Health-focused positioning; TeraPublic remains cross-topic civic transparency.
- Unverified claims about accessibility, budgets, contractors, work completion, or facility features.

## 13. Relationship to technical documents

- `PROJECT_SPEC.md` summarizes the product scope and agreed technical/business decisions.
- `USE_CASES.md` formalizes actor permissions and workflows.
- `ERD.mmd` and `spec/terapublic.schema.json` define conceptual data structures.
- `BACKEND_GUIDE.md` explains Laravel responsibilities and conventions.
- `FRONTEND_GUIDE.md` explains Next.js structure, design quality, palette, typography, and library policy.
- `IMPLEMENTATION_PLAN.md` defines a staged build order.
- `AGENTS.md` instructs AI coding tools on how to read and apply this material.

If technical artifacts conflict with this Product Guide, do not silently choose one. Report the discrepancy and preserve the most recent explicitly approved decisions.
