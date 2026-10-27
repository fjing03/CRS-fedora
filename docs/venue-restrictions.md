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
> Enforcement candidates were served by SDD **"venue-restrictions enforcement"** — **LANDED 2026-10-24** (see the §4 receipt). The eligibility logic now lives at:
> * client matrix `venueMatrixAllows()` (under `venueEligible()`) in `resources/views/livewire/replacement-arrangement.blade.php` (the ONE client reflection of `ruleMap()` — module-conditioned; capacity ≥ headcount mirrored client-side; the B005 no-Diploma arm was REPEALED 2026-10-27 — B005 rides its type row
like any lab);
> * server-side `SessionAllowedForVenue` / `sessionVenueEligible()` (`app/Services/LectureSessionCatalog.php` + `app/Services/MatrixIntersectionEngine.php`) — the authoritative OCC-time read (R1 + R3 + R6 + R2, per the §4 receipt);
> * `App\Services\LectureSessionCatalog::ruleMap()` (SDD `arrange-intent-session-choice`) — the same type rules as data, consumed by the "Select a Session" modal and the arrangement fallback.
> The R3/R6 ALLOW/DISALLOW matrix + R6's FOCS gate are ENFORCED at engine/client/modal layers since 2026-10-24 (SDD `venue-restrictions-enforcement`). **B005's no-Diploma = REPEALED 2026-10-27** (SDD `b005-diploma-allowed`, the user's ruling — the keys, the deny block, the derived conflict and the client arm all died; see R2). R7 (descriptions) is ENFORCED as display data since 2026-10-23. R4/IoT = the DROPPED receipt (2026-10-24).
>
> **2026-10-19 SDD `lt-venue-capacity-rule` update:** the law widened (R1's
> row pair), and its consumers are now: the engine `eligibleVenues()`
> (room_type map + capacity), the venue page's producer-modal pool
> (`sessionAllowedForVenue` — same map), the client matrix (map over
> `rawRoomType`), and the shared wording helper (`venueTypeWording` in
> `ui-common.js`). The CSV column no longer gates any of them.
>
> **2026-10-24 SDD `venue-restrictions-enforcement`:** the campus lawbook is
> now CODE at all three layers — `LectureSessionCatalog::ruleMap()` was
> widened with the module-conditioned ALLOW/DISALLOW data (networking
> subjects' additive lab access; the B006 L/T roster; the R6 FOCS/banned
> population keys; R2 composed for EVERY type), and the engine is
> authoritative: `SubmitReplacementRequest` passes its session through
> `validateSlot(..., $session)` — a failing gate is the existing
> `engine_precheck_failed` loser, silently (no new UI flags; the imported
> violating rows stay untouched). The B005-diploma-conflict family is
> GONE post-repeal (2026-10-27).

---

## 1. Rules

### R1 — Session-type → venue-type map — **ENFORCED (listed locations above)**

| Session type | Allowed venue room_type |
|---|---|
| `P` (Practical) | `lab`, `cisco_lab` |
| `T` (Tutorial) | `tutorial` |
| `L` (Lecture) | `tutorial`, `lecture_hall` |

> **User clarification 2026-10-20 (SDD `lh-l-only-revert`), verbatim ruling:**
> *"update : the Lecture Hall only allow for L only."* — the lecture hall
> stops admitting T (the T row narrows back; tutorials keep the L/T dual
> admission from the 2026-10-19 ruling).
>
> **User clarification 2026-10-19 (SDD `lt-venue-capacity-rule`), verbatim ruling:**
> *"Lecture Hall & Tutorial Rooms are allow to have L/T."* — a venue admits an
> L/T session when **capacity fits** (the B005 no-Diploma check was
> REPEALED 2026-10-27). The row pair was `T → tutorial` / `L → lecture_hall` until
> this date; the booking-side code now keys on the room-type law, and the CSV
> column `venues.allowed_session_types` is reference inventory only — where it
> disagrees (tests' lab fixture CSV `'L,T'`), the room_type verdict WINS.
> `ApiReadController`'s inventory still reads the CSV directly (its remaining
> reader — a deliberate, listed above; no edit expected).

No session may occupy a venue whose `room_type` is not in its row. Capacity applies in addition (the B005 check was REPEALED 2026-10-27).

### R2 — B005 no-Diploma — **REPEALED 2026-10-27 (SDD `b005-diploma-allowed`)**

The user's ruling, verbatim: *"i think let's remove the B005
restrictions, by allowing Diploma cohorts to use it too."* — the law
(shipped 2026-10-24 in SDD `venue-restrictions-enforcement`) is REMOVED:
the rule keys left `ruleMap()`, the engine's deny block, the derived
conflict at :485 + the venue grid's severity-3 arm, the producer pool's
deny receipt, and the client matrix's hidden-B005 arm. B005 rides its
type row (`lab`) like every other lab.

- Standing-data consequence: the standing AMIT2034 P Wed 11:00–13:00
  B005 row (DSF-only, 21 students) renders CLEAN everywhere — the
  perpetually-red block the user has queried all session is explained by
  a law that no longer exists.
- The audit trail (kept for the record): ~~ENFORCED both sides since
  2026-10-24~~ → **REPEALED** in the same day the cohort bands were
  amended (the 20–25 diploma scaling). The 2026-10-09 audit + the
  2026-10-13/27 DFT2 unlinks stay as history.
- No `venues` migration: `allowed_session_types = 'P'` stays reference
  inventory (Q6 precedent); `ruleMap()` remains the sole authority.

### R3 — B006 Cisco Lab access — **ENFORCED** (plain ALLOW / DISALLOW — the priority model retired; SDD venue-restrictions-enforcement 2026-10-24)

**Priority is retired.** The 2026-10-15/24 claimant/ranking machinery (priority
order, "everyone else yields", claims L/T/P) is REPLACED by a plain
ALLOW / DISALLOW model — nothing ranks; the enforcement encodes plain
admission checks only.

- **ENFORCED as data since 2026-10-24** (SDD `venue-restrictions-enforcement`):
  the matrix lives in `LectureSessionCatalog::ruleMap()` —
  `lab_access_module_codes = [AMIT2033, BMIT2154, BMIT3084, AMIT2034]`
  (those modules' **L/T may ALSO use any lab** — additive: their ordinary
  halls/tutorials stay legal) and
  `b006_allowed_module_codes = [the same four codes — AMIT2034 is the
  FOURTH code of the B006 roster, not just the networking trio]` (their
  **L/T may use B006**); B006's P admission stays the base R1 row for
  everyone.
- **Networking subjects (AMIT2033, BMIT2154, BMIT3084, AMIT2034) — ALLOWED
  in B006**, plain allow, NO priority ranking, NO yield semantics. The
  composition order: type rows → the module additives → then the venue
  gates (R6 population; B005's arm REPEALED 2026-10-27) for EVERY session type — the widening composes freely (the
  REPEALED 2026-10-27 — nothing to hide behind anymore).
- **The population gate stays R6** (B006 = FOCS cohorts only; RAF/RBU
  never).
- ~~AMIT2034's `L/T/P` sessions MUST execute in a lab~~ — the 2026-10-24
  final ruling SUPERSEDES that labs-exclusive reading: the networking
  set's **L/T keep their R1 rows** AND gain labs additively; P = R1's
  normal labs-only row for every module (B005 was R2's compose-on —
  REPEALED 2026-10-27; AMIT2034's DSF has no booking-time ban at B005).
  Current rows conform (L/T in B010, P in B005; zero B006 rows).

⚠️ **Reality checks (2026-10-15 → 2026-10-24 receipts):**
1. ~~The enforcement change must widen the `allowed_session_types` column~~ — superseded (the matrix is module-conditioned; a flat column cannot carry it): **NO migration happened**; the column stays `'P'` for B006 as reference inventory, and `ruleMap()` is the sole authority at all three layers.
2. `cisco_lab` maps to display vocabulary `'CiscoLab'` (producer payload) and `'Lab'` (consumer payload) — the enforcement change keeps one canonical encoding (see `LectureSessionCatalog::ruleMap()`; the gate keys on the ROOM CODE `B006`, not the display type).

### R4 — IoT — **DROPPED (user decision 2026-10-24: skip the IoT concern)**

The 2026-10-15 IoT-exclusivity directive (P → B006 ONLY / excluded from
every other lab) and the same-day L/T lab addendum are **RETIRED — not
scheduled for enforcement**: the user does NOT plan to include any IoT
module in the dataset. There is no IoT rule to enforce.

- IoT's only live trace = the B006 venue DESCRIPTION text — **kept as is**:
  `Cisco Lab. Networking & IoT equipment room. For FOCS cohorts only.`
  (R7 data; a display string, NOT a rule).
- If an IoT module ever enters the dataset, a fresh user decision is
  required before any IoT rule exists.

- Cross-ref: the old "deferred non-goal" note in SDD `arrange-intent-session-choice` is OBSOLETE (the rule it deferred no longer exists).

### R5 — Audit trail (context only, no action)

Audit 2026-10-09: 6 non-networking sessions use B006 (AMCS1013 ×2, BMIS2003 ×2, BMIT3173, BMIT3273) — **legal (plain ALLOW model; not violations)**. Priorities are retired (2026-10-24); admission rides R6's FOCS-only gate.

### R6 — B006 (cisco_lab) = FOCS-only population gate — **ENFORCED (2026-10-24, SDD venue-restrictions-enforcement; directive sv 2026-10-23)**

**Who may occupy B006: FOCS programmes only.**
- **ALLOW:** `DFT` (Diploma IT), `DSF` (Diploma Software Engineering), `RSD` (Bachelor IT) — all Faculty of Computing.
- **DISALLOW — never:** `RAF` (Bachelor Accountancy), `RBU` (Bachelor Business Administration).

**Decisions by the user (2026-10-23):**
- Scope = **B006 only** (the sole `cisco_lab` in the DB — the CHECK-constrained room_type list; verified 2026-10-23). Any future cisco_lab room needs an explicit migration/decision before inheriting this rule.
- **Priority dropped for the BINARY gate** (the sv's "especially RSD" nuance is NOT part of R6 — and SUPERSEDED 2026-10-24: scheduling priority is retired entirely per R3 — there is no ordering left to compose with this gate; R6 = plain admission).
- ~~Positive restatement of R2~~ — REPEALED 2026-10-27 with R2 itself.

**Live-data cross-ref (verified 2026-10-23; receipt 2026-10-24):** AMCS1013's 2 B006 sessions host `DSF·y1g1` — the R6 POPULATION gate is SATISFIED (DSF = FOCS); its violation is the TYPE mismatch (the T row in a lab — see the R5 note), NOT the population gate. The other B006 modules (AMIT2033 DFT, BMIT2154/BMIT3084/BMIT3173/BMIT3273/BMIS2003 RSD) already comply.

**Where this enforcement lands:** LANDED 2026-10-24 — SDD `venue-restrictions-enforcement` enforced R2/R3/R6 at all three layers (see the preamble receipt). (R4 = the DROPPED receipt.)

### R7 — Room descriptions — **ENFORCED (SDD venue-description-doc-b006-rule, 2026-10-23)**

Every room carries a plain `venues.description` (nullable text; the sv's directive: "add descriptions to every room, ignore advanced functions/backend like priority"). Applied by a reversible migration (down = dropColumn) on choice A; drafted first-draft texts are live, the user corrects wording after. Shown on the venue page (a small line under the venue dropdown) and the replacement-arrangement page (a line under the venue dropdown). `mock-data.js` venue entries carry a DOCUMENTED MIRROR of the DB texts — the DB wins on any difference; update both or drift is on the editor.

---

## 2. DB facts the rules read (unchanged)

| Table.column | Values | Used by |
|---|---|---|
| `venues.room_type` | `tutorial` / `lecture_hall` / `lab` / `cisco_lab` (CHECK constraint) | R1, R3 |
| `venues.allowed_session_types` | `P` for B005 & B006 today | reference inventory ONLY (Q6: no widening migration — 2026-10-24); the engine + client + modal read `ruleMap()` as the sole authority; `ApiReadController`'s audit read is its remaining consumer |
| `programmes.programme_code` | `DFT`, `DSF` (Diploma, `D*`); `RSD`, `RAF`, `RBU` (Bachelor, `R*`) | (was R2 — repealed) R6 uses FOCS only |
| `time_slots_no_double_book_idx` partial unique index | `(venue, day, start, week) WHERE status IN ('pending','occupied')` | double-book protection (independent of these rules) |
| OCC `version` column | optimistic-concurrency flip | booking write path |

## 3. Layer map (where eligibility is decided TODAY)

| Layer | File | Checks live there today |
|---|---|---|
| Submit-side engine | `app/Services/MatrixIntersectionEngine.php` `validateSlot(..., $session)` + `sessionVenueEligible()` (+ the blocked-venues payload per session) | **R1 + R3 (module-conditioned) + R6 + R2 — the ONE matrix read, OCC-time authoritative** (SDD venue-restrictions-enforcement, 2026-10-24) |
| Arrangement client filter | `replacement-arrangement.blade.php` `venueMatrixAllows()` (under `venueEligible`) | the SAME matrix mirrored from `PAGE_DATA.ruleMap` (client; capacity mirrored as always) |
| Producer modal + fallback | `app/Livewire/VenueTimetable.php::intentSessionOptions` + the arrangement fallback `consumeBookingIntent` | R1 + R3 + R6 + R2 via the upgraded `sessionAllowedForVenue()` — one gate, all consumers |

## 4. "Venue restrictions enforcement" — **LANDED 2026-10-24** (SDD `venue-restrictions-enforcement`)

The change enforced R2/R3/R6 at ALL THREE layers with ONE rule source
(`LectureSessionCatalog::ruleMap()`, now carrying the module-conditioned
ALLOW/DISALLOW data) consumed as data by: (1) the submit-side engine —
the authoritative OCC-time validation, `validateSlot(..., $session)` +
`sessionVenueEligible()` (design D3) — plus the per-session blocked-venues
payload; (2) the arrangement venue dropdown (`venueEligible` → the ONE
client `venueMatrixAllows` composition, which also drives the booking-intent
fallback predicate); (3) the "Select a Session" modal (`sessionAllowedForVenue`,
upgraded in place, signature unchanged).

The composition ruling: type rows → the module additives → then the venue
gates (R6 FOCS/banned population; R2 no-Diploma REPEALED) for EVERY session type —
resolving the 2026-10-24 simplification's items (the plain ALLOW/DISALLOW
rosters; AMIT2034's additive lab law — the earlier labs-exclusive reading
repealed; the B006 widening by the ROOM CODE (no column migration — Q6;
ruleMap = the sole authority); R6's composition, no ordering). New venue
rules land here, never ad hoc.
