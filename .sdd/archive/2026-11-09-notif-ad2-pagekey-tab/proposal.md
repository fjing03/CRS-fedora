# Proposal — notif-ad2-pagekey-tab

## Problem (found during the notifications-re-verify round, 2026-10-29)

The notifications island's AD-2 tab map **never engages**. Every panel
mounts with the viewed category `lecturer`, the default — so:

- on `/request-approval-ui` the PL does NOT see their `submitted` rows in
  the panel (live receipt: 5425's unread row 3 sits in the "other tabs"
  swap text while the approval queue is the page they are ON);
- a student NEVER sees their `update` rows in the panel (the claimed
  "your timetable got updated" surface of the AD-2 law);
- the category-aware caught-up copy reads against the wrong tab.

Evidence anchors (all read this session):

- `app/Livewire/NavNotifications.php:88-95` — `resolveRoleView()` matches
  camelCase keys (`studentMyTimetable`, `replacementHistory`, `requestApproval`).
- `resources/views/partials/ui-nav-bar.blade.php:126` — the island partial
  include passes `'pageKey' => $activeNav ?? ''` — the NAV KEBAB KEY
  (`request-approval`, …). A camel `'requestApproval'` match can never
  fire. Live proof: the :8000 snapshot shows
  `pageKey:"request-approval"` → `roleView:"lecturer"` on the approval page.
- The retired mock JS (git history, commit 821f5cb) — the AD-2 law read the
  tab from body[data-page] → the layout's `$pageKey`:
  `{ studentMyTimetable: 'student', replacementHistory: 'student',
     requestApproval: 'pl' }`, everything else → lecturer.

## Why the fix must come from `$pageKey`, NOT `resolveRoleView`

Two independent reasons:

1. **Every surface already publishes the camel key.** All 9 Livewire
   components pass e.g. `RequestApproval: pageKey=requestApproval`,
   `StudentMyTimetable: pageKey=studentMyTimetable`,
   `ReplacementHistory: pageKey=replacementHistory` (grep anchors in
   design.md), the layout writes it to `<body data-page>` (mq: the SAME
   var the mock law consumed), and ALL legacy mock-fallback templates set
   it too. The camel contract is complete and unbroken.
2. **The kebab nav keys cannot express AD-2.** `activeNav` collides across
   roles: `my-timetable` is BOTH the student's timetable page and the
   staff `/my-timetable-ui`; `replacement-history` is BOTH
   `/replacement-history-ui` (student) and `/my-request-history-ui`
   (lecturer/PL). Remapping `resolveRoleView()` to kebab keys therefore
   structurally CANNOT distinguish student from lecturer panels — the
   rejected-approach half of this proposal.

## The change (the WHOLE fix is one include-argument)

`resources/views/partials/ui-nav-bar.blade.php:127`:

```diff
-    @include('partials.ui-notifications-panel', ['pageKey' => $activeNav ?? ''])
+    @include('partials.ui-notifications-panel', ['pageKey' => $pageKey ?? $activeNav ?? ''])
```

- `$pageKey` is already in the ui-nav-bar scope (the layout scope's
  `->extends` data flows through `@include`); the panel partial and the
  island component `mount(pageKey:…)` / `resolveRoleView()` are UNTOUCHED —
  they were right all along.
- The `$activeNav` fallback is a defensive no-change default: any future
  surface that sets only `activeNav` keeps today's behavior (its kebab key
  falls through `resolveRoleView()` to the lecturer default).

## In scope

- The one-line include-argument fix + a comment documenting the rule
  (AD-2 = body[data-page] = layout `$pageKey`; kebab `activeNav` is the
  NAV highlight key, NOT the panel tab key).
- One feature test pinning the law: mounting with the camel keys yields
  the intended `roleView` (requestApproval → pl; studentMyTimetable →
  student; replacementHistory → student; anything else → lecturer). The
  existing suite already covers rendering; this adds the MAPPING.
- The :8000 Playwright receipts re-run (NOTE, folded from review round 1 🟡:
  the current caught-up/AD-2 receipt observes the fixed outcome but ALSO
  passes in the broken state — it is not a regression pin; during apply its
  row-visible leg will be tightened to assert the pl row's anchor is really
  in the list on /request-approval-ui. The D3 mapping test is the true
  regression pin).

## Out of scope

- No UI markup/classes/copy changes (the frozen panel is untouched).
- No NavNotifications logic changes; no emitter changes.
- The mock retirement lanes; the pending-bugs round; Reverb.

## Risks (all receipted, all small)

- **Behavior change on 3 pages** (request-approval → pl tab; both student
  pages → student tab): THIS IS THE LAW'S INTENT, but it flips what the
  panel shows today. The demo's live DB already has a real pl-tab row for
  5425 (row 3, unread, request #6) — the approval page's panel starts
  showing it, exactly the bridge's promised UX.
- The bell badge (F-2 whole-mailbox total) is unaffected — it never read
  pageKey.
- Low-risk receive: the existing :8000 spec re-run + the 1 new feature
  mapping test + the full suite.

## Success criteria

- `/request-approval-ui`: the panel's list shows the pl-tab rows (the
  PL's submitted/awaiting rows) without a reload.
- The student pages: the panel shows the student-tab rows when they exist.
- The lecturer pages: unchanged lecturer default (regression-free).
- Suite green (276 → 277+); lint + phpstan 0; the :8000 Playwright
  receipts green against the FIXED behavior; changelog updated.
