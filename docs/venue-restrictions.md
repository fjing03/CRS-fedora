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
> B005's no-Diploma derivation is client-side only today. R6 (B006 FOCS-only) is likewise DOC-ONLY — no code checks it. R7 (descriptions) is ENFORCED as display data since 2026-10-23.
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
| `T` (Tutorial) | `tutorial` |
| `L` (Lecture) | `tutorial`, `lecture_hall` |

> **User clarification 2026-10-20 (SDD `lh-l-only-revert`), verbatim ruling:**
> *"update : the Lecture Hall only allow for L only."* — the lecture hall
> stops admitting T (the T row narrows back; tutorials keep the L/T dual
> admission from the 2026-10-19 ruling).
>
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

### R3 — B006 Cisco Lab access — simplified **ALLOW / DISALLOW** (user decision 2026-10-24 — the complex PRIORITY model is DROPPED)

**Priority is retired.** The 2026-10-15/24 claimant/ranking machinery (priority
order, "everyone else yields", claims L/T/P) is REPLACED by a plain
ALLOW / DISALLOW model — nothing ranks; the future enforcement encodes plain
admission checks only.

- **Networking subjects (AMIT2033, BMIT2154, BMIT3084) — ALLOWED in B006** —
  plain allow, NO priority ranking, NO yield semantics. Session-type legality
  rides today's gate (`venues.allowed_session_types = 'P'` for B006 — see ⚠️1)
  until the enforcement change lands.
- **The population gate stays R6** (B006 = FOCS cohorts only; RAF/RBU never).
- **AMIT2034 (Fundamentals of Computer Networks) — the module's own law:
  its `L/T/P` sessions MUST execute in a lab, and it may use ANY lab**
  (`lab` or `cisco_lab` — B005, B009, B010, B011, B006). It is NOT a B006
  claimant — just any-lab access with no special standing in B006. Its
  current rows already conform (L/T in B010, P in B005; zero B006 rows).
  NOTE: AMIT2034's **L/T-in-a-lab is an R1 override FOR THIS MODULE ONLY** —
  R1's standard rows send L→tutorial/lecture_hall and T→tutorial; the
  enforcement change must encode this module-scoped exception.
- The 2026-10-24 "demotion" wording (AMIT2034 = priority-claimant demotion)
  is SUPERSEDED by this simplification: there are no priority claimants
  anywhere; all rules are ALLOW or DISALLOW.

⚠️ **Reality checks (2026-10-15, re-scoped 2026-10-24):**
1. `venues.allowed_session_types = 'P'` for B006 today → **only P sessions may occupy it**. The networking subjects' **L/T** in B006 and AMIT2034's L/T-in-B006 are **not legal today**: the enforcement change must widen the column + the engine check. That widening is **PLANNED-NEW**, not current policy.
2. `cisco_lab` maps to display vocabulary `'CiscoLab'` (producer payload) and `'Lab'` (consumer payload) — the enforcement change must keep one canonical encoding (see `LectureSessionCatalog::ruleMap()`).

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

### R6 — B006 (cisco_lab) = FOCS-only population gate — **DOC-ONLY (sv directive 2026-10-23, user-recorded)**

**Who may occupy B006: FOCS programmes only.**
- **ALLOW:** `DFT` (Diploma IT), `DSF` (Diploma Software Engineering), `RSD` (Bachelor IT) — all Faculty of Computing.
- **DISALLOW — never:** `RAF` (Bachelor Accountancy), `RBU` (Bachelor Business Administration).

**Decisions by the user (2026-10-23):**
- Scope = **B006 only** (the sole `cisco_lab` in the DB — the CHECK-constrained room_type list; verified 2026-10-23). Any future cisco_lab room needs an explicit migration/decision before inheriting this rule.
- **Priority dropped for the BINARY gate** (the sv's "especially RSD" nuance is NOT part of R6 — and SUPERSEDED 2026-10-24: scheduling priority is retired entirely per R3 — there is no ordering left to compose with this gate; R6 = plain admission).
- **Positive restatement of R2 (the user's extra, same rule):** "B005 is ONLY allowed degree students" — the enforced no-Diploma check unchanged.

**Live-data cross-ref (verified 2026-10-23):** AMCS1013's 2 B006 sessions host `DSF·y1g1` — legal today (no gate enforced), a **future conflict** once R6 enforcement lands; flagged here for the enforcement SDD's migration checklist. The other five B006 modules (AMIT2033 DFT, BMIT2154/BMIT3084/BMIT3173/BMIT3273/BMIS2003 RSD) already comply.

**Where this enforcement lands:** only in the future "venue-restrictions enforcement" SDD (§4), alongside R2/R3 — never ad hoc. (R4 dropped 2026-10-24.)

### R7 — Room descriptions — **ENFORCED (SDD venue-description-doc-b006-rule, 2026-10-23)**

Every room carries a plain `venues.description` (nullable text; the sv's directive: "add descriptions to every room, ignore advanced functions/backend like priority"). Applied by a reversible migration (down = dropColumn) on choice A; drafted first-draft texts are live, the user corrects wording after. Shown on the venue page (a small line under the venue dropdown) and the replacement-arrangement page (a line under the venue dropdown). `mock-data.js` venue entries carry a DOCUMENTED MIRROR of the DB texts — the DB wins on any difference; update both or drift is on the editor.

---

## 2. DB facts the rules read (unchanged)

| Table.column | Values | Used by |
|---|---|---|
| `venues.room_type` | `tutorial` / `lecture_hall` / `lab` / `cisco_lab` (CHECK constraint) | R1, R3 |
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

Turns every DOC-ONLY / PLANNED-NEW rule above into code at all three layers, keeping ONE rule source (`LectureSessionCatalog::ruleMap()` — or its enforced successor) consumed as data by: (1) the submit-side engine (authoritative OCC-time validation); (2) the arrangement venue dropdown + `venueEligible`; (3) the "Select a Session" modal + fallback. It also MUST resolve, per the 2026-10-24 simplification: the plain ALLOW/DISALLOW lists (networking = ALLOWED in B006, no ranking), AMIT2034's module-scoped lab law (L/T/P MUST run in a lab; ANY lab — R1's L/T rows overridden FOR THIS MODULE ONLY), the B006 `allowed_session_types` widening (⚠️1 — covers the networking subjects' L/T + AMIT2034's L/T; IoT is IRRELEVANT — R4 dropped), and R6's composition (B006 = FOCS-only admission + these plain allows, no ordering). Until it lands, treat R2/R3 as documentation, and never "quick-fix" them ad hoc.
