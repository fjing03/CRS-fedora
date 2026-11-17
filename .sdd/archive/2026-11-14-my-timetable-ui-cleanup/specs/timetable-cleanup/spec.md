# Spec — My Timetable UI cleanup

> **Change:** `my-timetable-ui-cleanup`
> **Applies to:** `/my-timetable-ui`, `/student-my-timetable-ui`,
> `/cohort-timetable`, `/venue-timetable-ui`, `/my-replacements`,
> `/request-approval-ui`

## Terms

- **Card set:** the five `.summary-card` elements inside `#summaryBar`.
- **Back face:** `.summary-card-back`, the flipped side of a card.
- **Guide modal:** `#guideModal`, the single dialog opened by the header's
  `.guide-toggle-icon`.
- **Semester chrome:** `#semesterProgress` and `#weekSubtitle`.

## Requirements

### UI-01 — Summary card geometry

Every card in the six pages' card set is 96px tall on desktop and keeps the
existing column widths and responsive columns. The front face keeps the
existing value colour map. The `valueId` of every card is unchanged.

### UI-02 — Card back content

The back face is left-aligned, uses the 12px description type, and holds at most
three short bullets (`ul.summary-points > li`). It also holds one 44px
`.summary-info` button with `data-tip="Full explanation"` and an accessible
name `"<card label>: full explanation"`.

### UI-03 — Card interaction

Hover flips a card on pointer devices. Tap flips it on touch. Enter/Space flips
a focused card and prevents page scroll. The info button opens the full
explanation modal without flipping the card. Closing that modal restores the
source card's flipped state and focus. Each card exposes `role="group"` and an
`aria-label` that says it can be flipped.

### UI-04 — Guide modal

The header guide icon opens `#guideModal`; it does not toggle an inline block.
The modal contains the page's description (`page-desc`, rich HTML preserved)
and every guide item from the page. It closes via ×, the Close button, the
overlay, and Escape, and restores focus to the icon. It is a centred dialog on
desktop and uses the existing bottom-sheet `.modal` rules at `<=768px`.

### UI-05 — No inline guide

The six pages no longer render `.guide-block` / `#guideBlock`. No page keeps
both the inline guide and the modal.

### UI-06 — Semester chrome removed

`#semesterProgress` and `#weekSubtitle` do not render on My, Student or Venue
Timetable. Cohort Timetable does not gain them. `updateProgress()` and
`updateWeekSubtitle()` may still be called; they must remain null-safe.

### UI-07 — Week select surface

Every `.week-select` on the timetable pages has a
`var(--color-surface-variant)` resting background and a `var(--color-surface)`
hover background. Text (`var(--color-on-surface)`), the chevron, height,
padding, radius and focus ring are unchanged. Opened options keep a
`var(--color-surface)` background so they stay readable in both themes.

### UI-08 — Preserved behaviour

No summary value, calculation, payload field, filter, week navigation, class
detail modal, cancel-class modal or print path changes. The four pages continue
to fetch and display the same numbers.

## Scenario map

| Scenario | Requirements |
|---|---|
| Desktop timetable pages | UI-01, UI-02, UI-07 |
| Phone / tablet timetable pages | UI-01, UI-02, UI-03, UI-04, UI-07 |
| Keyboard-only user flips a card | UI-03 |
| User opens a card's full explanation | UI-03 |
| User opens the guide | UI-04, UI-05 |
| Week change after chrome removal | UI-06, UI-08 |
| Dark and light theme week select | UI-07 |
