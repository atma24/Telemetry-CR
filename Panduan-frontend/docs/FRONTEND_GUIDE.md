# TeraPublic Frontend Guide

> **Status:** source of truth for frontend implementation. Read this before creating or restyling UI.
> **Product:** TeraPublic — Public Transparency & Civic Insights.
> **Current frontend:** Next.js App Router + TypeScript + Tailwind CSS + Turbopack; project uses `src/`.

## 1. Design intent

TeraPublic is a civic-tech data product. The interface must feel **trustworthy, clear, calm, data-literate, inclusive, and deliberately designed**. It is not a generic startup landing page, a motion showcase, or an AI-generated dashboard template.

The design should help people answer:
- What public information is available?
- Where does the information come from, and when was it updated?
- What does the data actually show?
- What can the public do next?

Prioritize information hierarchy, clear labels, source attribution, meaningful comparisons, readable tables, usable filters, and accessible interactions over decorative effects.

## 2. Non-negotiable anti-patterns (“do not make AI slop”)

Do not automatically generate or repeat these patterns:
- Giant gradient headline + generic two-column hero + three identical feature cards as the default page layout.
- Glassmorphism, glowing borders, aurora backgrounds, floating blobs, excessive gradients, or blurred decorative orbs without a product-specific reason.
- Animating every heading, card, counter, icon, and section just because an animation library is installed.
- A 3D globe/map/scene that adds weight but does not improve public understanding of the data.
- Using cards for every piece of information. Use tables for records, charts for trends, maps for spatial questions, and cards only for summaries or grouped content.
- Fake metrics, invented project progress, fabricated data provenance, misleading charts, or plausible-looking placeholder figures presented as real.
- Generic Lorem Ipsum or unlabelled demo data in a public-facing screen.
- Excessive pill badges, huge corner radii on every element, deep shadows, or arbitrary decorative icons.
- Duplicate navigation patterns, icon-only controls without accessible names, or long pages full of repeated sections.

A polished UI does not mean maximal visual effects. Use a small number of deliberate visual decisions consistently.

## 3. Brand system: Civic Trust palette

Use the palette from the TeraPublic brand reference. Do not invent new brand colors or silently replace these hex values.

| Token | Hex | Intended use |
|---|---|---|
| Primary Navy | `#15324B` | Main brand, page titles, navigation, strong structure |
| Secondary Teal | `#0F766E` | Primary actions, active controls, links, selected states |
| Accent Mint | `#50C7B2` | Small accents, selected data highlights, limited visualization accents |
| Highlight Amber | `#F4B740` | Warnings, notable data points, attention states; not a default button color |
| Background Light | `#F7FAFC` | App/page background |
| Surface White | `#FFFFFF` | Main content surfaces, tables, dialogs |
| Border Slate | `#DFE7ED` | Dividers, input borders, table separators |
| Text Primary | `#1F2937` | Body copy and high-priority data text |
| Text Secondary | `#64748B` | Helper text, metadata, subdued labels; verify contrast at actual font size |
| Teal Hover | `#0D6B63` | Hover/pressed state for teal actions |

### Color distribution

Use the reference distribution as a rough composition guide, not a rigid arithmetic rule:
- ~60% light background and white surfaces.
- ~20% navy structure and typography.
- ~10% teal actions and active states.
- ~5% mint accent.
- ~5% amber highlights.

### Color rules

- Primary button: white text on teal `#0F766E`; use `#0D6B63` for hover/pressed states.
- Main headings: navy `#15324B`.
- Body text: `#1F2937` on white/light surfaces.
- Secondary text: `#64748B`; check WCAG contrast against its actual background, especially for smaller text.
- Amber is normally paired with dark text, not white text.
- Do not communicate status by color alone. Include labels/icons/text (e.g. “Draft”, “Published”, “Needs review”).
- Do not add a semantic success/error palette until needed; choose accessible, restrained values and document them.
- Validate contrast for every important text/background pair. Do not rely on a contrast number copied from a mockup without checking the actual colors.

## 4. Typography

Use the brand typography:
- **Plus Jakarta Sans** for the product wordmark where available, page titles, and headings.
- **Inter** for body text, data tables, controls, labels, forms, and dense UI.

Implementation guidance:
- Prefer Next.js font handling already supported by the project (for example `next/font/google`) if build/network requirements allow it. Preserve sensible sans-serif fallbacks.
- Inspect the installed Next.js/Tailwind versions before changing configuration. Do not introduce a Tailwind config file that conflicts with the installed Tailwind major version.
- Use a restrained type scale. Page titles should establish hierarchy, not dominate the content. Tables and metadata must remain comfortably readable.
- Use tabular numerals for metrics and aligned numeric columns when appropriate.
- Avoid using ALL CAPS for long labels; reserve it for small eyebrow labels where it aids hierarchy.

## 5. Library policy: use deliberately, not all at once

The supplied reference lists Motion, GSAP, React Spring, Anime.js, Three.js, React Three Fiber + Drei, Lenis, Lottie/DotLottie, Rive, Aceternity UI, Magic UI, and React Bits. These are options, **not a requirement to install all of them**.

### Default choices

1. **Tailwind CSS** — continue using the already-installed styling system.
2. **Motion (`motion/react`)** — preferred animation library for small component-level transitions and interaction states when CSS alone is insufficient.
3. **React Bits** — optional source for a small number of distinctive, useful components. Review and adapt each copied component to TeraPublic’s palette, accessibility, responsiveness, and existing architecture. Do not copy a whole demo page.
4. **Lucide React** — may be used for a consistent icon family if icons are needed. Use icons to support meaning; avoid decorative icon clutter.
5. **Recharts** — a candidate for data charts when appropriate to the installed stack and actual monitoring requirements. Charts must be driven by data and have labels, legends/tooltips where useful, and accessible summaries.

### Optional; require a specific reason

- **GSAP:** only for a genuinely complex, timeline-based or scroll-linked sequence that Motion/CSS cannot handle cleanly. Do not animate the same component with both GSAP and Motion.
- **Lenis:** optional for a carefully designed, editorial-style public landing page only if smooth scrolling improves the experience. Do not force it into data tables, admin workflows, or normal document navigation. Respect reduced-motion preferences and keyboard/native scrolling.
- **Aceternity UI or Magic UI:** alternatives for an isolated component, not additional design systems to layer on top of React Bits. Choose at most one component source for a given need, then adapt its styling.
- **Lottie/DotLottie or Rive:** only for a specific, lightweight, purposeful illustration/state that cannot be represented more simply. Do not use generic animations as fillers.
- **Three.js / React Three Fiber / Drei:** not part of the MVP default. Use only if an approved feature has a real spatial/3D requirement and the performance/accessibility cost is justified.

### Avoid redundant animation engines

Do not add React Spring and Anime.js on top of Motion by default. Do not add multiple engines to achieve the same type of transition. First consider CSS transitions and keyframes; then Motion; use a heavier specialist library only for a specific approved use case.

### Installation rule for AI agents

Before adding any dependency:
1. Inspect `package.json` and the existing codebase.
2. Check whether the desired capability already exists.
3. Explain why the dependency is needed and its effect on bundle size/maintenance.
4. Add only the dependency required for the current task.
5. Do not install every library named in this guide pre-emptively.

## 6. Motion and interaction principles

- Motion should communicate cause and effect: opening a dialog, expanding a filter, revealing a menu, changing a tab, or confirming an action.
- Prefer subtle opacity/transform transitions; avoid excessive bounce, spring, zoom, parallax, and scroll-jacking.
- Keep transitions short and consistent. Do not animate large amounts of tabular data on every render.
- Respect `prefers-reduced-motion` and ensure all actions remain usable with reduced motion.
- Loading states should be calm and informative; avoid endless decorative spinners when progress can be described.
- Keep focus rings visible and keyboard navigation functional.

## 7. Information architecture and page patterns

### Public pages

Expected routes may include:
- `/` — product introduction and clear entry to public monitoring.
- `/dashboard` — public monitoring overview.
- `/datasets` and `/datasets/[slug]` — data catalog/detail.
- `/facilities` and `/facilities/[id]` — facilities if supported by the selected datasets.
- `/projects` and `/projects/[id]` — development projects if supported by the selected datasets.

Public viewing must not require login. Only show published records. Clearly show source, period, last updated time, and limitations when those details are known.

### Authenticated pages

- `/login`, `/register` — account access.
- Feedback creation and personal feedback history — login required.
- Chatbot — login required, with sessions private to their owner.

### Admin pages

Expected areas:
- Overview/dashboard.
- Regions.
- Facilities.
- Development projects.
- Datasets/import jobs.
- Draft validation/publication.
- Feedback management.
- RAG knowledge sources (later phase).
- Audit history.

Admin and public views may share presentational components, but they must not share permission assumptions. Permissions are enforced by the backend.

### Use the correct presentation for the data

- **Tables:** administrative records, dataset rows, audit logs, import outcomes.
- **Charts:** temporal trends, distributions, comparisons, and summary indicators where data supports them.
- **Maps:** location-based questions only when valid coordinates/geospatial data exist.
- **Cards:** high-level summaries and semantically grouped content, not every record.
- **Forms:** clear labels, validation messages, explicit required/optional fields, and safe confirmation for destructive actions.

Every data page needs purposeful loading, empty, error, and success states. Empty states should tell users what is missing or how to proceed. Never pretend real data is present when it is not.

## 8. Design layout guidance

- Use a consistent spacing scale aligned to Tailwind’s spacing utilities; avoid arbitrary one-off spacing everywhere.
- Use `#F7FAFC` for the application background and white surfaces for key content areas.
- Use borders `#DFE7ED` for structure. Keep shadows subtle and purposeful.
- Use moderate corner radii consistently; do not make every element a giant pill.
- Give page titles, context/description, filters, data content, and source attribution a clear vertical hierarchy.
- For the Admin experience, prioritize efficient navigation and dense-but-readable information. Avoid oversized decorative headers that push tables below the fold.
- For the public experience, prioritize clarity and accessible explanation of indicators, sources, and limitations.
- Responsive layouts must work on narrow screens; wide data tables need an intentional responsive strategy rather than accidental overflow.

## 9. Code organization

Use the existing `src/` project structure and Next.js App Router. Do not replace working app configuration without a reason.

Target organization (create incrementally, only when needed):

```text
src/
  app/                 # route files and layouts
  modules/             # feature/domain-specific UI and frontend logic
    auth/
    regions/
    facilities/
    projects/
    datasets/
    feedback/
    chatbot/
  components/
    ui/                # shared low-level UI pieces
    layouts/            # shared layout shells
  lib/
    api/                # typed FastAPI client and request helpers
    utils/
  config/
  types/
```

Rules:
- Keep route files focused on composing pages and route-level loading/error boundaries.
- Keep domain-specific components close to their module.
- Keep truly shared components in `components/ui/`; do not create a universal component that contains unrelated product behavior.
- Keep frontend services focused on HTTP/API communication. Backend business rules belong in FastAPI, not duplicated in React components.
- Use TypeScript types for API responses and request payloads; avoid `any` unless narrowly justified.
- Do not expose secrets or private environment values in client-side variables.
- Add a new module only when implementing that feature; do not create dozens of empty folders in advance.

## 10. Data integrity and honest UI

- No invented public datasets, project progress, facility details, or prediction outputs.
- Clearly distinguish official/source data, manually curated data, and demo/mock data.
- If demo seed data is needed during development, label it as demo data and keep it out of production defaults.
- Show the source and update period when available.
- Do not present ML predictions as facts. ML is not finalized and must not be added as a decorative feature.
- Never display unpublished drafts on public routes.

## 11. Implementation workflow for the AI CLI

For each frontend task:
1. Read this guide and `spec/design-system.json`.
2. Inspect the current files and versions before proposing changes.
3. Summarize the smallest useful implementation plan before coding.
4. Implement only the requested page/component/phase.
5. Use the approved palette and typography; do not invent an alternative visual system.
6. Add a library only if the feature requires it and the user’s instruction permits it.
7. Check responsive behavior, keyboard operation, contrast, loading/empty/error states, and reduced motion.
8. Run the available lint/typecheck/build commands and report results truthfully.
9. Report files changed, dependencies added, tests run, and outstanding questions.

Do not scaffold all pages or implement all features in one pass. Ask before changing approved product rules or introducing a heavyweight visual effect.
