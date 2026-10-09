# Design — b005-diploma-conflict

Frozen baseline: proposal.md (R1 PASS). Baseline context: explore-brief.md.

## 1. Evaluator shape — trait method, not service

`ResolvesTimetableTimeline` gains one private method; `VenueTimetable` calls the same code path:

```php
/**
 * Venue-restriction conflict (VENUE-RESTRICTIONS.md): a session conflicts when
 * its venue is B005 AND any cohort belongs to a D* (Diploma) programme.
 * Derived at render time — the DB state machine stays 3-state (FR 4.11).
 */
private function venueRestrictionConflict(ClassSession $s): bool
{
    return $s->venue !== null
        && $s->venue->room_code === 'B005'
        && $s->cohorts->contains(fn ($c) => str_starts_with((string) $c->programme->programme_code, 'D'));
}
```

*(Archive erratum 2026-10-09: shipped code uses the plain arrow — the `programme` relation is neverNull (phpstan `nullsafe.neverNull`); the nullsafe form in this block was the design-time draft. Do not re-introduce `?->` here in Slice B.)*

- **Trait**: `baseEvent()` sets `'status' => $this->venueRestrictionConflict($s) ? 'conflict' : 'normal'`. Covers MyTimetable / CohortTimetable / StudentMyTimetable (all use `baseEvent`).
- **VenueTimetable**: its event builder (component composes the same trait) calls the same method per slot's session — per-slot rows of session 38 all carry it. Full slot-status mapping: derived conflict overrides both slot-pending and normal (`conflict > pending > normal`, matching the `reduceTwins` severity map; pending rows cannot exist today, latent until Slice B).
- **Rejected**: `App\Services` evaluator — 2 consumers today; promote to a service when booking-time enforcement becomes the 3rd (Slice B), per §10.0.6.
- **`D*` prefix safety**: feature test asserts the programme table contains no non-diploma `D*` code (`DFT`, `DSF` only), pinning the prefix heuristic.
- **Eager-load note (venue view)**: add `'classSession.venue'` to the `TimeSlot::with([...])` array — `venueRestrictionConflict` reads `$s->venue->room_code`; without it the venue view lazy-loads per session (safe but N+1).

## 2. VenueTimetable twin-merge interaction (review 🔴-1)

- Conflict derivation runs **per twin row** (session-level check on each `time_slots` row's session) **before** `mergeTwinEvents`.
- The existing severity map in `reduceTwins` **already ranks conflict highest** (`['normal' => 0, 'replacement' => 1, 'pending' => 2, 'conflict' => 3]`, added in merge-upstream-ui-2026-10) — no map change needed, but a feature test pins it: a twin pair (normal + conflict rows, crafted via reflection) merges to `conflict`.
- The flagship event (session 38: 4 slots/week, Wed 11:00–13:00) is a combined DFT+DSF lecture — all its rows derive conflict, so the (defensively) merged event stays `conflict`. Real twins remain impossible until Slice B (occupied/pending partial unique index); the merge path is defensive contract only.

## 3. Status precedence (review 🟡-1)

**Chain: `replacement` > `pending` > `conflict` > `normal`.**

- The trait's existing override order already produces this: `baseEvent` (conflict or normal) → loop overrides `pending` (pending request) → approved path overrides `replacement`. No code change — the chain is documented and **pinned by a precedence feature test**: a pending request on session 38 renders `pending` (yellow), not `conflict`; the conflict re-emerges when the request is gone. Honest semantics: an active replacement proposal supersedes the conflict display.
- Venue view: derived conflict overrides the slot-status mapping entirely (`conflict > pending > normal`, §1) — the view CAN emit `pending` for pending slots (VenueTimetable.php:108), so the tie-break is pinned, not assumed absent.

## 4. Display (owner-gated, per upstream parity)

**Ownership mechanism per page (exact keys — they are NOT interchangeable):**
- Venue view payload already carries `mine` (VenueTimetable.php:110, `lecturer_id === auth()->id()`) — the venue branch gates on `e.mine`.
- Trait payloads (My/Cohort/StudentMy) carry **no** ownership key — `baseEvent` never sets one, and the cohort blade's existing `e.isMine` mine/pending branches are pre-existing **dead code** (out of scope, untouched). The cohort conflict branch therefore uses the upstream template idiom: name comparison against `MockData.currentUser` (verified set at cohort-timetable.blade.php:101 — name + staffId of the authed user).

| Page | Mechanism | Conflict rendering |
|---|---|---|
| MyTimetable (Daniel) | engine default (no statusClassFn) | `.event-conflict` loud red — free |
| StudentMyTimetable (DFT/DSF) | engine default | `.event-conflict` loud red — free |
| CohortTimetable | custom statusClassFn | NEW status branch placed AFTER the existing holiday `isConflict` branch (holiday wins on the latent holiday-day edge, matching the modal's precedence at ui-common.js:920–925): `e.status === 'conflict'` → `e.lecturer === MockData.currentUser.name ? 'event-conflict' : 'event-public-holiday'` (upstream idiom, `CohortTimetable-UI-design-template.blade.php:347-351`) |
| VenueTimetable | custom statusClassFn | existing conflict branch becomes owner-gated on the EXISTING `mine` key: `e.mine ? 'event-conflict' : 'event-public-holiday'` |

**Cohort owner-gating rationale (review 🟡-2)**: the conflict is cohort-inherent, yet the gate stays viewer-based uniformly. Rationale: the loud red means "needs YOUR action" (the owning lecturer's — Daniel sees loud on the cohort page via name comparison); other viewers (incl. DFT/DSF students on their pages — loud there is correct: it IS their class) see the quiet red + the existing Conflicts summary card (`computeSummary` counts `status === 'conflict'` for everyone) — information present, not alarm-styled. Uniform gating avoids inventing a second conflict visual per page (§10.0 same-meaning-same-colour).

- **Approved-replacement edge (reviewer optional)**: an approved replacement re-derives from `baseEvent` (original venue B005 → conflict) then overrides venue + `status='replacement'` — a replacement landing in another B005 slot renders blue `replacement`, never `conflict`. Accepted behaviour, noted here.
- **`event-public-holiday` dual meaning** (holiday OR others'-conflict): precedent exists (cohort legend tip already says "Scheduling conflict or public holiday"); no CSS/legend change. Do not add a third meaning.

## 5. Tests

Feature (`tests/Feature/` — new `VenueRestrictionConflictTest` + additions where they fit):
1. Session 38 derives conflict on all four page payloads (my-timetable via Daniel; cohort/student via a DFT cohort scope; venue view).
2. Negative: RSD-only B005 sessions stay `normal`.
3. Negative (B006, review 🟡-3): a B006 session (networking OR non-networking) stays `normal` — priority is not a violation.
4. Twin-merge severity: crafted normal+conflict twins merge → `conflict` (reflection, same pattern as the existing merge test).
5. Precedence: pending request on session 38 → `pending`; after removal → `conflict`.
6. `D*` prefix safety: programmes table contains no non-diploma `D*` code (currently `DFT`/`DSF` only — must not fail if a third diploma programme is added later).

Playwright: extend the venue spec only if cheap (B005 week 1 grid shows one `.event-conflict` block); my/student page coverage via existing engine-default path is acceptable as feature-tested.

## 6. Gates

`lint:check` · `types:check` (phpstan --memory-limit=1G) · phpunit (123 + new) · venue-db **4/4** *(soft-freeze erratum: was 3/3 — T5 adds one Daniel-login test)* · timetable-wiring 5/5 · nav-identity 3/3. Records-intact: exact row counts identical (derived change — no DB writes). Server restart via the guarded pattern only (never `pkill -9 php`).

## 7. Commit plan (no push without explicit authorization)

1. `feat(venue): B005 diploma-cohort conflict — derived flag + owner-gated rendering` (component + trait + cohort blade + tests).
2. `docs(sdd)` archive after verify.

## 8. Risks

- **`computeSummary` conflicts card counts derived conflicts for every viewer** (incl. on cohort pages next to a quiet block) — accepted (documented §4); consistent with upstream behaviour.
- **Changelog**: add entries to `venue-timetable-ui-changelog.md` (venue owner-gating) and `my-timetable-changelog.md` (trait conflict) — task mapping in tasks.md.
