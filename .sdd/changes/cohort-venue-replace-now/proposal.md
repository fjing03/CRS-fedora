# proposal — `cohort-venue-replace-now` (2026-10-24)

## Why (user law)

> "Cohort page & Venue page shud've Replace Now button too. for OWN Original
> Conflicted Class."

The class-modal-affordances lane (frozen 2026-10-24) decided the cohort page
gets NO Replace Now. **The user's new decision supersedes that frozen point.**
The venue page's button exists but carries a latent off-by-one in its
handoff week (explore-brief Open-question Q1 receipt). The lane:

1. **Cohort page (`/cohort-timetable-ui`)** — ADD `Replace Now` to the
   `eventModal` footer, gated OWN + conflict (the venue gate family).
2. **Venue page (`/venue-timetable-ui`)** — FIX the handoff week
   arithmetic (`currentWeek` → `currentWeek + 1`; the arrangement's
   `pinnedSessionPayload` is 1-based-validated); the gate stays as-is.
3. **`BackNavigator.#routes`** — add the
   `'cohort-timetable': '/cohort-timetable-ui'` row so the handoff ride
   back lands the user on the COHORT page (vs today's replacement-home
   fallback). The four existing rows stay byte-identical.

## Out of scope

- my-timetable's Replace Now (already exists — the richer `occurrenceFact`
  gate; unchanged).
- The absence of the notifications bell on the arrangement page (BUG-04 —
  user-closed 2026-10-24: accepted as-is; no fix).
- Any submit/backend change — the handoff rides the arrangement page's
  EXISTING pinned-session resolution (`pinnedSessionPayload`, unchanged).
- The pending Bugs 01–07 doc list (docs/replacement-arrangement-pending-bugs.md)
  — a separate lane family once the user verifies.

## The gate (ONE formula, cohort + venue parity)

```js
const isMine = event.mine === true || event.lecturer === MockData.currentUser?.name;
replaceBtn.style.display = (isMine && event.status === 'conflict') ? 'flex' : 'none';
```

- same shape as the venue page's existing receipt (verbatim);
- NO new payload fields (the cohort's `baseEvent` already carries
  `id`/`lecturer`/`status` — the OWN trust anchor = the served rows, the
  client re-reads `event.lecturer` against `MockData.currentUser.name`).

## The handoff (per page — R1: NO `??` composite; each page reads its OWN field)

| page | session id | week arg | from |
|---|---|---|---|
| cohort | `currentModalEvent?.id` | `currentWeek + 1` | `'cohort-timetable'` |
| venue (T2, week arg only) | `currentModalEvent?.sessionId` (UNCHANGED) | `currentWeek + 1` (was raw `currentWeek`) | `'venue-timetable'` |

- the composite `?.id ?? ?.sessionId` from the first draft is DROPPED:
  venue events carry a truthy `id` (the SLOT id, not the session id) — the
  fallthrough would never fire and would hand off a wrong identity;
- the `+1`: the arrangement expects the 1-based human week
  (`pinnedSessionPayload` validates `week in 1..weeks`); the venue page's
  OWN other flows already pass `currentWeek + 1` (intentSessionOptions,
  the arrange-URL fallback) — the Replace Now button was the lone outlier;
- cohort events key the session id at `event.id` (the baseEvent shape);
  venue events key it at `event.sessionId` (the venue payload's additive
  field) — no payload edits on either side.

## The blast radius

- `resources/views/livewire/cohort-timetable.blade.php` — footer button +
  openModal gate line + the comment retirement ("no Replace Now on cohort —
  the frozen design D1/D3" → superseded note).
- `resources/views/livewire/venue-timetable.blade.php` — the handoff week
  arg (`currentWeek` → `currentWeek + 1`) — the ONE attribute change.
- `public/js/ui-common.js` — `BackNavigator.#routes` additive row.
- Tests: `tests/class-modal-affordances.spec.ts` — its venue rows ride the
  class-modal-affordances receipts; the off-by-one fix may move a venue
  row's asserted hand URL — re-anchor if it does (T-list item). NEW spec
  `tests/cohort-venue-replace-now.spec.ts` — the cohort rows assert the
  button's gate (own+conflict visible; others' conflict hidden; own
  non-conflict hidden; the handoff URL `?session=&week=<human>&from=
  cohort-timetable`) + one venue regression row re-verifying the fixed
  handoff week.
- page-changelogs/cohort-timetable-changelog.md +
  page-changelogs/venue-timetable-changelog.md — dated entries.

## Acceptance (the header line, one formula)

A user on EITHER page opening ANY class modal sees `Replace Now` ONLY IF
the class is THEIRS and conflicted; clicking it lands the arrangement page
at THAT occurrence's OWN `?session=<id>&week=<human-week>` pair; the way
back from there returns to the SAME page they left.
