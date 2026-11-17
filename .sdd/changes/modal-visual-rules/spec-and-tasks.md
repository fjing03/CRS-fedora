# Modal visual rules — compact specification and tasks

Date: 2026-11-18. Status: VERIFIED PASS after apply; not archived.
Approval: user chose B and said 'yes, go': dark selected-tab text and Close touch height only.
Cost rule: one combined artifact + one review round (user 2026-11-17). Shared consumers mean
this is not a single-consumer small-change exemption. No production edits before review.

## Evidence and intent

Pass-3 critique, docs/ui-drift-2026-11-17.md §9: active text rgb(0,77,152) on dark modal
rgb(27,40,56), dimmer than inactive labels; phone Close ~77×40 vs primary ~356×48.
Prototype reference: ui-prototype/my-replacements.html, footer comments/markup near lines
1027–1072; preserve its rails and footer order. Current theme.css explicitly says secondary
'outline, never full width, never the bottom-most target' near 2770. Keep that authored layout.
PRODUCT.md and DESIGN.md: Operate-mode refinement, existing palette only, 48×48 touch rule
is capability-gated, not a width rule. Disability/conformance audits are out of scope.

## Decisions / implementation

1. Existing `.modal-tab.active` keeps its current `--color-primary` text in light mode and
   blue `--color-primary` underline in both themes. Add a dark-only rule:
   `.dark .modal-tab.active { color: var(--color-on-primary-container); }`.
   No new token, no selected-tab geometry/weight/behaviour changes.
2. Inside `@media (any-pointer: coarse)`, floor the existing quiet Close button families:
   `.modal--detail .modal-footer .btn-outline:not(.is-primary)` and
   `.modal--detail .modal-footer .btn-close-modal` get `min-height: 48px; min-width: 48px`.
   Place this block AFTER the existing <=520 secondary-footer rule (for example with
   the existing coarse blocks near 6990+): the outline selector ties its specificity,
   so later source order is necessary. No `!important`. No width breakpoint on this
   floor, so 1440 touch also qualifies. Keep each family's existing width behaviour:
   outlined Close stays centred/auto-width; btn-close-modal retains its existing
   full-width phone treatment. Keep full-width primary and ordering.
   Do not change Reject/Cancel `.btn-danger`, top-right ×, primary buttons, global buttons,
   or the default mouse min-heights. This is not an app-wide touch audit.
3. CSS only in public/css/theme.css. No JS, Blade, DB, routes, dependencies, prototype edits,
   new UI pattern or print changes. Changelogs and this evidence record may be updated.

## Known consumers and limits

`.modal-tab` is created by shared DetailModal.render; seven live pages use it directly or
through openClassModal: My Timetable, Student My Timetable, Cohort Timetable, Venue Timetable,
My Replacements, Request Approval and student Class Replacements. The Close families under
`.modal--detail .modal-footer` serve six of these details shells (Request Approval has
no footer Close; its header exit is unchanged). Venue's static Select-a-Session
also uses `.modal--detail` and `.btn-close-modal` (label Cancel): it inherits the touch floor
as the same quiet exit component; its primary Arrange is not changed. This inherited exit
height is acknowledged, not a new cancellation behaviour. Confirm dialogs lacking
`.modal--detail` are unaffected. Tab underline/status fills remain unchanged.

Request Approval queue currently has no pending row. Never create data to open it: the live
shared CSS/DOM shell may be inspected with an explicitly labelled client-only DetailModal
presentation fixture. Do not claim this verifies a live pending approval flow.

## Acceptance criteria

- Dark selected-tab foreground equals existing --color-on-primary-container (currently
  rgb(107,163,224)); light selected-tab foreground remains --color-primary (rgb(0,77,152)).
  Underline remains --color-primary in both themes. Inactive tabs unchanged.
- Both Close families measure >=48×48 on coarse-pointer contexts at 390 and 1440.
  At mouse desktop 1440, height/width and footer ordering match baseline. A mouse phone
  keeps its baseline secondary size as well. No forced full-width secondary.
- Real staff/student details render legibly and stay within viewport at 390 touch, 1440
  mouse and 1440 touch, in both themes. Header/body/footer remain usable; long body scrolls.
- No status colours, actions, event wiring, text, routes or database content changed.

## Tasks / verification

- [x] One independent read-only review of this artifact + relevant CSS specificity. Record
      verdict and address comments here before freeze. No extra stage-by-stage review loop.
- [x] Apply the two CSS rules; inspect exact diff against approved scope.
- [x] Batch browser visual inspection + computed-style measurements at the above sizes and
      themes, comparing fine-pointer desktop against baseline. Reuse live app on :8030;
      no database content writes, only login sessions and browser-local presentation data.
- [x] No Playwright test-suite rerun for pure CSS (standing user rule). Browser inspection
      is measurement, not a regression-suite run. Mechanical detector does not accept CSS-only
      targets: scan named consumer markup once if needed, never the entire repository.
- [x] Update affected page changelogs + survey statuses; record verification here and commit
      only this lane's files/hunks. Never push. Do not archive without approval.

## Review record

Round 1, 2026-11-18: independent read-only pi subprocess, PID 53070,
commandcode/z-ai/glm-5.3-flash. Raw report `/tmp/crs-modal-visual-review.txt`.
**PASS**, with two wording clarifications now incorporated: place the coarse block
AFTER the narrow rule (outline specificity ties), and preserve each Close family's
existing width rather than describing both as centred/auto-width. Reviewer explicitly
allowed apply after these clarifications without another round. No blockers remain.
Baseline recorded in `/tmp/crs-modal-visual/baseline.json` and screenshots; production
CSS is still unchanged at freeze. Request Approval has no footer Close (header exit
only); its tab text is the only proposed visual change on that page.

## Apply / verification receipt — 2026-11-18

Production diff is 15 added CSS lines, including comments: dark-only foreground rule
and one coarse-pointer Close-family floor. No existing width/order rule, no markup,
no JS, no database content or dependency change. User-authorised intent preserved.

Before/after: `/tmp/crs-modal-visual/baseline.json`, `after.json`, screenshots and
capture script `/tmp/crs-modal-visual-capture.js`. 38 rendering contexts per phase:
staff list + My Timetable + approval shell + venue exit shell at 390/1440, mouse/touch,
both themes; student list at 390 touch and 1440 touch/mouse, both themes. Comparisons
found zero unexpected changes in light text, underline, button order, other-button
sizes, mouse sizes or page overflow.

| Quiet exit / input | Before height | After height |
|---|---:|---:|
| Staff and student list Close, 390 touch | 40 | 48 |
| My Timetable Close, 390 touch | 44 | 48 |
| My Timetable Close, 1440 touch | 34 | 48 |
| Venue session-picker Cancel, 390 touch | 44 | 48 |
| Venue session-picker Cancel, 1440 touch | 34 | 48 |
| Staff/student list Close, 1440 touch | 48 | 48 |
| All inspected quiet exits, 1440 mouse | 34 | 34 |
| Outlined Close, 390 mouse | 40 | 40 |
| Legacy Close / venue Cancel, 390 mouse | 44 | 44 |

All quiet-exit widths remain their baseline values and >=48px on touch. Dark active
tab rgb(0,77,152) -> rgb(107,163,224), background rgb(27,40,56), readability ratio
1.79 -> 5.64. Light text stays rgb(0,77,152); both underlines stay rgb(0,77,152).
These are usability measurements, not disability/conformance claims.

Supplement: 18 real detail renders on Cohort, Venue and Student My Timetable
(390 touch; 1440 mouse/touch; both themes) all show expected tab tokens and Close
height (48 touch /34 mouse), no page overflow. At 390x568, staff body scrollHeight417
vs clientHeight258; footer bottom567 stays inside568 viewport. Header/body/footer
layout preserved. Screenshots inspected for staff dark phone, student light phone,
approval dark desktop and short-height staff sheet.

Limits: Request Approval pending queue is empty; only a visibly labelled client-only
DetailModal shell was used. Venue session-picker checks are CSS-shell presentation,
not a booking flow. Auth sessions only; no request/class/notification writes. No
physical-device or Safari verification. Cancel Class/Reject/Arrange heights are
unchanged and not certified by this Close-only task.

No Playwright suite rerun (pure CSS user rule). Once-only detector on the three
consumer list blades: 27 design-system-font-size advisories; markup unchanged and
sizes are existing DESIGN.md prose steps. No new rule. PHP gates not applicable:
no PHP/Blade edits. `git diff --check` clean before commit.

Acceptance criteria verified by direct browser measurements and diff inspection;
seven affected page changelogs + survey §9.2 updated. Ready to archive only if the
user asks; no push.
