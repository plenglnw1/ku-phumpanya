# Frontend handoff (KU-BCG): API contracts as deployed

This document is for Tigger and the KU-BCG frontend. It records the backend contracts **as built and deployed** on 2026-10-29, which differ in a few places from the first handoff prompt; the differences are marked **Changed**. The full shapes are in `docs/api-spec.md` (v1.2.0).

Please keep the fallbacks: old stored results do not carry the new fields.

## Contracts

### C1: Result tags (deployed)

`result.tags = { bcg_pillars: string[], faculties: string[] }`, most frequent first.

- Pillars are `Bio`, `Circular`, and `Green`.
- Faculties are `วนศาสตร์`, `เกษตร`, `อุตสาหกรรมเกษตร`, and `ประมง`.
- **Fallback:** when `tags` is missing, derive the chips from `knowledge_graph.nodes` by `type`.

### C2: Async synthesis (backend ready, disabled on production)

**Changed: no polling loop is needed.**

1. `POST /api/search` may return `result.status = "pending"`. The draft already has evidence, the knowledge graph, the learning path, and tags; only the overview text is a fallback.
2. Render the draft immediately, then call `GET /api/search/history/{id}` **once**. That request runs Gemini synthesis, takes about 4–5 s, and returns `status = "ready"`. Concurrent requests for the same id wait for the same synthesis.
3. If that request fails or times out, keep the draft. Retry at most once.
4. A missing `status` means ready.

The backend turns on `GEMINI_ASYNC_SYNTHESIS` once the frontend handles `pending`. Tell Pleng when it does.

### C3: Delete account (deployed)

`DELETE /api/profile` with `{ "confirm_email": "<user email>" }` for Google accounts. Case does not matter.

- Success returns `204`; then log out and go to `/`.
- An email mismatch returns `422`, with the error under `errors.confirm_email`.

### C4: Consent (deployed)

**Changed: use `consent_required` from `GET /api/user`, not `consented_at === null`.** `consent_required` also becomes true again when the privacy notice version changes.

- Show the consent modal while `consent_required` is true.
- Accepting calls `POST /api/consent`, which returns the user.
- `google_id` is no longer in any user response.

### C5: Session (deployed)

- The session lasts 30 minutes idle and ends when the browser closes. There is no remember-me.
- **Idle UI:** warn at 25 minutes. At 30 minutes, call `POST /api/logout` and go to `/`.
- Any `401` from `/api/*` means the session expired; send the user to login.

### C6: Progress overview (new, deployed)

**Changed: use one call instead of combining per-roadmap requests.**

`GET /api/progress` returns:

- `data[]`, one entry per roadmap: `{ id, query, title, completed_count, total, percent, created_at }`.
- `summary`: `{ roadmaps, started, finished, completed_count, total, percent }`.

Use it for the `/progress` page. Marking a module is unchanged: `PUT /api/search/history/{id}/progress` with `{ phase, card, completed }`.

### C7: RECENTS (deployed)

`GET /api/search/recent` is already newest first, with an id tiebreak, and leaves out failed searches. Do not re-sort on the client.

## Six BCG topics (for the 6 × 3 tab test and Playwright)

These replace the topic list in the first prompt.

| id | Topic | Suggested query | Pilot faculties |
|---:|-------|-----------------|-----------------|
| 1 | Biomass & Bioenergy | ชีวมวลและพลังงานทดแทน | เกษตร, อุตสาหกรรมเกษตร, วนศาสตร์ |
| 2 | Chitosan & Bio-packaging | ไคโตซานและบรรจุภัณฑ์ชีวภาพ | ประมง, อุตสาหกรรมเกษตร, เกษตร |
| 3 | Microplastics & Water Quality | microplastics water quality | ประมง, อุตสาหกรรมเกษตร, เกษตร |
| 4 | Carbon Footprint & Carbon Neutrality | carbon footprint ภาคเกษตร | วนศาสตร์, เกษตร, อุตสาหกรรมเกษตร, ประมง |
| 5 | Sustainable Aquaculture & Probiotics | การเพาะเลี้ยงสัตว์น้ำที่ยั่งยืน probiotic | ประมง, อุตสาหกรรมเกษตร |
| 6 | Agroforestry & Biodiversity | วนเกษตร agroforestry ความหลากหลายทางชีวภาพ | วนศาสตร์, เกษตร, ประมง |

## Static routes

The KU Apache rewrite already serves `/progress/`, `/profile/`, and `/privacy/` from the static export. The pages need to exist in the Next build as `app/progress`, `app/profile`, and `app/privacy`.

## Evidence the gates need from the frontend

| Gate | Evidence path (KU-BCG) |
|------|------------------------|
| 1.4.1–1.4.2 | `docs/gates/1.4.1-2/` screenshots: 6 topics × 3 tabs, 0 console errors |
| 1.4.3 | Click-test screenshots of every menu, including Progress |
| 1.4.6 | `docs/gates/1.4.6/`: chromium, firefox, webkit, and iPad screenshots of every page |
| 1.5.1 | `docs/gates/1.5.1/` Playwright HTML report: happy path for 6 topics × 4 faculties |
| 1.5.3 | Screenshots of the consent modal, idle warning, profile page, and delete account |
