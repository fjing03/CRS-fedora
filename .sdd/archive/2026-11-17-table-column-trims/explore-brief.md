# Explore brief — table-column-trims

Written 2026-11-15 from a read-only survey of the live blades, `public/js/ui-common.js`,
the three prototypes and the tests. Nothing was edited.

## The source

The two staff prototypes carry one injected layer at the bottom of the file, called
**"the 9"** (`request-approval.html:2176`, `my-replacements.html:2219`). It binds to the saved
page with `?page=ra` / `?page=mr`. Two of its nine items are table-column trims:

- **#8** — the STAFF column drops the staff ID and the email.
- **#9** — the COURSE column drops the name and prints `AMSE2003 (T)`.

The layer patches `<tr>` cells only; it never touches the phone card, the pop-up or a notification
row. (`change89Row()` → `patchCourse()` / the staff branch, `request-approval.html:2298-2355`.)

## What the live code does today

| page | cell | built at | today's output |
|---|---|---|---|
| Request Approval | staff | `request-approval.blade.php:683-686` | `Surayaini Binti Basri (5425)` + a second line `surayaini@tarc.edu.my 📋` (click-to-copy) |
| Request Approval | course | `request-approval.blade.php:691` | `AMSE2003` + a second line `Software Testing and Quality Assurance` |
| My Replacements | course | `public/js/ui-common.js` → `replacementHomeRow(…)`: the `if (opts.merged)` branch (today `:3905-3919`, course cell `:3907-3911`) | `AMSE2003` + `Software Testing and Quality Assurance (L)` |
| Class Replacements (student) | course | `replacement-history.blade.php:309-312` (`courseCell()`) | `AMSE2003` + `Software Testing and Quality Assurance (L)` |

Callers of the My Replacements row builder: exactly one live page, `replacement-home.blade.php:793`
(passing `merged: true`). Inside that builder the **merged branch is the `if (opts.merged)` block**
(today `:3905-3919`; locate it by name, never by the number — `ui-common.js` grows as other lanes
land); the **legacy nine-cell body below it (today `:3939-3949`) belongs to the frozen mock template
and must stay byte-identical** —
editing it would change nothing on the live page and would break the template the lane is not about.
`resources/views/ui-design-templates/replacement-home-UI-design-template.blade.php:412` is a
frozen reference copy and is **not** a live consumer.

The student table has **no staff column** — there is no staff ID or email there to remove.

## Facts that decide the size of the job

- **Search is data-driven, not DOM-driven.** Request Approval builds its haystack from
  `r.courseCode + ' ' + r.courseName + ' ' + r.lecturer` (`request-approval.blade.php:650`); My
  Replacements matches `c.code` and `c.name` (`replacement-home.blade.php:711-712`). Dropping the
  visible name does not weaken search.
- **Sorting is on the code, not the name.** Request Approval `field: 'courseCode'` (`:584`), My
  Replacements `field: 'code'` (`:761`), student `field: 'course'` (`:362`).
- **The class type is already in the payload on all three pages.** Request Approval uses
  `r.classType` (`:527`, `:1090`); My Replacements `c.type`; the student row `r.type`.
  `UiLabels.classType(type, 'code')` returns the bare letter (`public/js/ui-common.js:3276-3281`),
  so the cell has to add its own parentheses.
- **The full name survives in the pop-up on all three pages** — the `.modal--detail` subtitle is
  `CODE — Name (Type)` (student `replacement-history.blade.php:624`; the two staff pop-ups build the
  same shape).
- **No test pins the cell's *text*, but one pins its *shape*.** `tests/Feature/CourseColumnTipTest.php:89-93`
  requires the Request Approval cell to open with the literal `html += '<td><div class="cell-code">' + r.courseCode`,
  and `:102-106` requires the merged builder's cell object to end `cls: 'col-code'` on its own line.
  Both keep passing only if the rewrite preserves those two shapes — so the rewrite is constrained,
  and the test is verified rather than edited. `tests/request-approval.spec.ts:57` asserts the
  **header** reads `Staff (Proposer)`; `:93` asserts the **pop-up strip**; `:96` counts the copy-email
  control **inside the modal** (1), not in the table. `tests/Feature/NotificationsBridgeTest` and
  `tests/notifications-island.spec.ts` assert notification **tips** (`Staff (Proposer): … (5425)`),
  a different surface this lane does not touch.
- **The CSS classes stay in use.** `lecturer-cell-id` and `lecturer-cell-email` are also used by the
  details strip (`request-approval.blade.php:932-936`) and the approve/reject card (`:516-519`).
  Only the table cell's use goes. (They are **not** used by
  `resources/views/partials/notif-row.blade.php` — notification tips are composed server-side.)
- **The course cell deliberately carries no hover text** — a 2026-11-09 user ruling, recorded at
  `request-approval.blade.php:689-690` ("the CELL carries no hover text — the column is explained
  once, on its header") and in the student blade's `courseCell()` comment. `UiLabels.courseTip()` —
  a helper written for a cell tooltip that the same-day ruling then withdrew — has **no caller**
  (`public/js/ui-common.js:3283-3296`); only `courseTipSubject` is used, by the three headers.

## Rejected approaches

1. **Port "the 9" wholesale.** Seven of its nine items are other lanes' business (#4 is asked for by
   `filters-button`; #1–#2 are the summary cards; #5–#7 are untouched and unrelated). Only #8 and #9
   are table columns. Bundling them would re-open a frozen lane.
2. **A CSS-only hide** (`display:none` on the second line). It leaves the ID and the email in the
   DOM, so they stay copyable by a select-all and reachable by a screen reader — the opposite of the
   intent. The ID and the email are personal data under FR 7.1; if they leave the queue view they
   should leave the markup.
3. **No SDD — the small-change exemption.** It was the alternative the user was offered and did not
   take. It would have qualified only while the change stayed on two pages; the student page makes it
   three, which is the repo's promote-on-3rd-duplication trigger.
4. **A shared "course cell" component for three pages.** Right long-term, wrong now: three one-line
   builders that already live in three different homes would need a new shared home plus a caller
   sweep, for a change that is half a line each. Revisit if a fourth caller appears.

## Open questions handed to the proposal

- Does the student table's course cell change too? (three pages vs two)
- Is the course name allowed to live only in the pop-up, with no hover rescue?
- The staff ID stays visible on the phone card and in the approve/reject confirm card — is that
  acceptable while `cards-and-badges-responsive` is still unbuilt?
