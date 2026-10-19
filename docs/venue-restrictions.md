# Venue Restrictions — booking & assignment rules

> **Reading this file (for humans AND AI agents).** Every rule below has exactly one of
> three statuses. Never conflate them:
>
> | Status | Meaning |
> |---|---|
> | **ENFORCED** | Already live in code today (see where, per rule). |
> | **DOC-ONLY** | The rule is agreed policy, but NOTHING checks it yet — no engine check, no client filter. Implementing it requires its own SDD change. |
> | **PLANNED-NEW** | A user directive recorded here first (2026-10-15). It is a policy DELTA over today's behavior — not currently true anywhere. |
>
> Enforcement candidates defer to one future SDD change, **"venue-restrictions enforcement"** (§4). Before that change, the ONLY venue eligibility logic live in code is:
> * client matrix `venueEligible()` in `resources/views/livewire/replacement-arrangement.blade.php` (display-vocabulary: P→Lab, T→Tutorial, L→LectureHall; capacity ≥ session headcount; B005 hides when any cohort is `D*`);
> * server-side `allowed_session_types` / `room_type` use in `app/Livewire/ReplacementArrangement.php` (`venuesPayload`, `blockedVenuesPayload`) — **type + capacity only**;
> * `App\Services\LectureSessionCatalog::ruleMap()` (SDD `arrange-intent-session-choice`) — the same type rules as data, consumed by the "Select a Session" modal and the arrangement fallback.
> B005's no-Diploma derivation is client-side only today.
>
> **2026-10-19 SDD `lt-venue-capacity-rule` update:** the law widened (R1's
> row pair), and its consumers are now: the engine `eligibleVenues()`
> (room_type map + capacity), the venue page's producer-modal pool
> (`sessionAllowedForVenue` — same map), the client matrix (map over
> `rawRoomType`), and the shared wording helper (`venueTypeWording` in
> `ui-common.js`). The CSV column no longer gates any of them.

---

## 1. Rules

### R1 — Session-type → venue-type map — **ENFORCED (listed locations above)**

| Session type | Allowed venue room_type |
|---|---|
| `P` (Practical) | `lab`, `cisco_lab` |
| `T` (Tutorial) | `tutorial`, `lecture_hall` |
| `L` (Lecture) | `tutorial`, `lecture_hall` |

> **User clarification 2026-10-19 (SDD `lt-venue-capacity-rule`), verbatim ruling:**
> *"Lecture Hall & Tutorial Rooms are allow to have L/T."* — a venue admits an
> L/T session when **capacity fits** (R2's B005 no-Diploma check still applies
> additionally). The row pair was `T → tutorial` / `L → lecture_hall` until
> this date; the booking-side code now keys on the room-type law, and the CSV
> column `venues.allowed_session_types` is reference inventory only — where it
> disagrees (tests' lab fixture CSV `'L,T'`), the room_type verdict WINS.
> `ApiReadController`'s inventory still reads the CSV directly (its remaining
> reader — a deliberate, listed above; no edit expected).

No session may occupy a venue whose `room_type` is not in its row. Capacity and B005 checks (R2) apply **in addition**.

### R2 — B005 no-Diploma — **DOC-ONLY** as a *server rule*; **client-only today**

Diploma students may not use B005: reject when any cohort of the session's `programme_code` starts with `D` (`DFT`, `DSF` exist; Bachelor codes start with `R` — unaffected).

- Today it is surfaced as a **derived conflict** in the timetable pages (SDD `b005-diploma-conflict`) and used *client-side* in `venueEligible`/the modal — the submit-side engine does NOT check it yet.
- Audit 2026-10-09: 1 violation found (AMIT2034 P, Wed 11:00–13:00, combined DFT+DSF lecture) → flagged for replacement.

### R3 — B006 Cisco Lab priority — **DOC-ONLY** (verified delta: partially NEW policy)

**Priority claimants:** Networking subjects, all session types `L, T, P` (DB list: AMIT2033 Networking Essentials, AMIT2034 Fundamentals of Computer Networks, BMIT2154 Switching and Routing Technologies, BMIT3084 Enterprise Networking — each L, T, P).
**Everyone else:** may use B006 at LOWER priority (never banned) — yield to networking/IoT demand.

⚠️ **Two reality checks (2026-10-15):**
1. `venues.allowed_session_types = 'P'` for B006 today → **only P sessions may occupy it**. Networking-claimant **L/T** sessions in B006 are **not legal today**: the enforcement change must widen the column + the engine check. That widening is **PLANNED-NEW**, not current policy.
2. `cisco_lab` maps to display vocabulary `'CiscoLab'` (producer payload) and `'Lab'` (consumer payload) — the enforcement change must keep one canonical encoding (see `LectureSessionCatalog::ruleMap()`).

- Open question assigned to the enforcement change: **how "Networking" identity is decided** — name-list match (misses edge cases, e.g. Internet Security) vs a curated module-category registry. Where the rule data lives is already solved (`ruleMap()` as data); which MODULES belong in it is not.

### R4 — IoT exclusivity — **PLANNED-NEW (user directive 2026-10-15; nothing implemented)**

**When an IoT module exists, its `P` sessions may use B006 ONLY** — excluded from every other lab (B005, B009, B010, B011). Stricter than today: overrides R1 for IoT-P (B005/B009/B010/B011 are `lab` and today legal for any P).

- Precondition: an IoT module in the DB (none exist yet — when added, classify it per R3's open question).
- Cross-ref: recorded as a deferred non-goal in SDD `arrange-intent-session-choice`.

### R5 — Audit trail (context only, no action)

Audit 2026-10-09: 6 non-networking sessions use B006 (AMCS1013 ×2, BMIS2003 ×2, BMIT3173, BMIT3273) — **legal under the priority rule** (lower priority, not violations).

---

## 2. DB facts the rules read (unchanged)

| Table.column | Values | Used by |
|---|---|---|
| `venues.room_type` | `tutorial` / `lecture_hall` / `lab` / `cisco_lab` (CHECK constraint) | R1, R3, R4 |
| `venues.allowed_session_types` | `P` for B005 & B006 today | historical guard; R1 supersedes when enforcement lands |
| `programmes.programme_code` | `DFT`, `DSF` (Diploma, `D*`); `RSD`, `RAF`, `RBU` (Bachelor, `R*`) | R2 |
| `time_slots_no_double_book_idx` partial unique index | `(venue, day, start, week) WHERE status IN ('pending','occupied')` | double-book protection (independent of these rules) |
| OCC `version` column | optimistic-concurrency flip | booking write path |

## 3. Layer map (where eligibility is decided TODAY)

| Layer | File | Checks live there today |
|---|---|---|
| Submit-side engine | `app/Services/MatrixIntersectionEngine.php` (+ `ReplacementArrangement::eligibleVenues`) | R1 only (type + capacity) |
| Arrangement client filter | `replacement-arrangement.blade.php` `venueEligible()` | R1 (display vocab) + capacity + B005 (client) |
| Producer modal + fallback | `app/Livewire/VenueTimetable.php::intentSessionOptions` / arrangement fallback (SDD `arrange-intent-session-choice`) | R1 + R2 via `ruleMap()` — the same data the enforcement change will extend |

## 4. "Venue restrictions enforcement" — the future SDD change

Turns every DOC-ONLY / PLANNED-NEW rule above into code at all three layers, keeping ONE rule source (`LectureSessionCatalog::ruleMap()` — or its enforced successor) consumed as data by: (1) the submit-side engine (authoritative OCC-time validation); (2) the arrangement venue dropdown + `venueEligible`; (3) the "Select a Session" modal + fallback. It also MUST resolve: the Networking module registry (R3's open question), the B006 `allowed_session_types` widening (R3 ⚠️1), and the IoT exclusivity check (R4). Until it lands, treat R2/R3/R4 as documentation, and never "quick-fix" them ad hoc.
