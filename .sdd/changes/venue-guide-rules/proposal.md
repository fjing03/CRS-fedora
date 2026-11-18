# Venue rules in the Venue Timetable guide

## Goal and audience

Lecturers should be able to check **what the selected room permits** before they start arranging a replacement. Add **Venue rules** as a panel in Venue Timetable’s existing **How to use this page** modal. It applies to the selected room only, not to the selected week or time. No new modal, route, booking action, write, dependency or schema change. Existing Venue Timetable access stays unchanged: lecturers with or without Programme Leader rights.

## Domain and display

- **Default session types** come from the room-type rows of `LectureSessionCatalog::ruleMap()`, not `venues.allowed_session_types` (reference inventory). Render names and codes, e.g. **Practical (P)**. Default does not mean unrestricted: a room-wide restriction can still apply.
- A **module exception** adds a specific module and session type to the room’s defaults. The selected room admits exception pairs from the map’s lab widening only where that widening applies (`lab` or `cisco_lab`, or the configured B006 room code). As in booking, omit any exception type that has no array-valued base rule, even if it appears in `lab_access`. A module’s `L` exception does not imply `T` or vice versa. Show one row per module with only the appropriate types, in module-code order; use module name if available without dropping a rule when the name is missing.
- A **room-wide restriction** applies to every session type, including the defaults. For B006, surface the programme rule separately: every cohort must belong to an allowed programme (current codes: DFT, DSF, RSD), and an explicit programme exclusion takes precedence. Derive the displayed effective list from the live allow list minus the live banned list, not a second hard-coded list. A row in the exception list does not establish that the lecturer’s cohort or a particular booking is eligible. Capacity, availability and other booking checks remain separate.

## Shape and data flow

- Reuse the shared guide shell, reading pane, tabs and footer. On this page, order: **Getting started · Full reference · Venue rules · Shortcuts**. Keep the current default on open. Venue rules stays available on phones; the existing Shortcuts breakpoint is unchanged. Show room code/name first, then defaults, room-wide restriction if applicable, and a compact list of module exceptions. Use the existing guide typography and tokens. If three labels need room on small screens, let them wrap instead of hiding a panel.
- Build the guide’s view data server-side for the same resolved venue that renders the timetable. The existing room picker navigates to `?venue=…`; use the new page’s selected room on the next guide open. Week changes leave the room rules unchanged. No parallel venue selector or client-side room-rule state.
- PLs (`lecturer.is_pl`) see **all** module exception pairs for that room. Ordinary lecturers see only pairs they teach in the timetable’s displayed semester, matched by lecturer user ID, semester ID, module and session type. Deduplicate multiple cohorts or recurring sessions. Apply this filter before assembling any new view data; don’t send all rows to Venue Timetable and hide them in the browser. The existing booking payloads elsewhere are out of scope. No semester/teaching data means no personal exceptions, not the full roster.
- Distinguish **no exceptions for this room** from **no matching exceptions for your teaching this semester**. With no selected room, show a neutral empty state. Do not treat missing or unknown room type as an unrestricted room. Give no instructions to book from this panel.

## Apply and verify

1. Add a read-only projection from the canonical rule map and the current lecturer’s semester teaching assignments; reuse the booking rule’s room applicability for exception widening without changing the booking verdict. Wire the optional Venue rules panel into the existing shell without changing other pages’ tab lists or approved copy.
2. Test role filtering on the server: PL sees all selected-room pairs; an ordinary lecturer sees their own module/type pairs only, not another lecturer’s, a different type of a taught module, or a different semester. Cover duplicates, unknown/no room, a normal lab, a stray unknown exception type, B006’s restrictive rule (also applies to P; an explicit exclusion wins even if a programme appears on both lists), and non-labs without exceptions. Verify that unshown pairs are absent from the new guide data, not just visually hidden. Use isolated test doubles where useful; no demo DB changes.
3. On `class_replacement_testing`, use the existing staff and PL accounts to verify the role-specific tab, switching rooms (lab → non-lab → B006), default selection, keyboard focus/arrow navigation, Escape, pinned Close, touch targets and copy fit in both themes at desktop and compact-phone sizes. Check that week navigation, existing Shortcuts visibility, and other guide pages work as before. Review tab hierarchy, labels and empty states with Impeccable before shipping. No submissions or booking changes.

## Proposal review

Independent review passed after specifying that B006’s explicit programme exclusions take precedence and that exception types with no base rule are omitted.

## Verification

- **39 PHP unit tests passed** (venue guide projection and existing booking matrix). Pint, PHPStan and whitespace checks passed. A read-only check against `class_replacement_testing` confirmed the active semester returns the fixture lecturer’s exception and a missing or nonmatching semester returns none.
- **39 targeted Chromium tests passed** on `class_replacement_testing`: the PL sees all 13 rows on the current fixture; ordinary lecturers with and without matches get only their own rows in the initial guide markup; the picker switches B009 → B111 → B006; week navigation does not affect the rules; existing guides, focus and dialog behavior are unchanged. Empty/unknown room cases were verified in unit tests only.
- Impeccable: inspected the long PL list at 320px in both themes and on desktop. Widened the module column to keep names readable without mid-word breaks on the fixture. Touch controls remain at least 48×48 CSS px and Close stays reachable. No detector advisories in the new panel. Captures in `/tmp/crs-venue-guide-final/`.
- No schema changes, demo database changes, request submissions or permission changes.
