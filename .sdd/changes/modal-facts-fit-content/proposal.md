# Proposal — the facts strip must not widen the pop-up

> **Status:** draft · 2026-11-14 · follow-on to `modal-responsive-width`

## Why

The user's report (screenshot, 2026-11-14): the Request Details pop-up leaves a
large empty gap on the right of its rows. The rows themselves are short.

Cause, measured: the grey facts strip at the top (`.detail-facts`: Staff ·
Cohorts · Students · Conflict) is a wrapping flex row whose one-line
max-content width is ~900px. `width: fit-content` on `.modal` therefore resolves
to the pop-up's **ceiling** (680), not to the width of its rows. The tabs and
rows then sit in a 680px box and leave the gap.

Measured on a real page with a cloned `Request Details` shell: the pop-up is
**626px** wide; with the strip prevented from contributing its intrinsic width
it becomes **513px** (the tab rail decides), same content.

## What changes

One shared rule: `.modal .detail-facts { width: 0; min-width: 100%; }` — the
strip takes the pop-up's width but contributes **0** to the pop-up's intrinsic
size. The pop-up then fits its tabs and rows; the strip wraps onto a second row
when it must (taller by roughly one row, no gap).

Plus the maximum the user approved in the same breath (2026-11-14):

- `replacement-home.blade.php:363` — Confirm Cancellation: `635` → `680`.
  It stays a **maximum**: `fit-content` still shrinks it to its content.

**Correction found while applying.** "Select a Session" is NOT the pop-up that
carries `560`: it already uses `.modal--detail`, so its ceiling is already the
shared `680`. The `560` at `ui-intent-session-modal.blade.php:88` belongs to
`#slotWarningOverlay` ("Slot Too Short"), a different pop-up in the same file,
which the parent lane deliberately left untouched. It is therefore **not**
changed here; the user is asked separately.

## In scope

`public/css/theme.css`, the two blade lines above, `ModalConsistencyTest`, the
existing `modal-responsive-width.spec.ts` (one added assertion), one appended
`page-changelogs/modal-responsive-changelog.md` entry.

## Out of scope

Content, wording, tabs, row order, colours, the arrangement page's deferred
`#confirmModal` block (T3b of the parent lane), every other lane's files.

## Risks

| Risk | Mitigation |
|---|---|
| The strip wraps and a pop-up gets taller | Measure the height before/after on the real pop-up; report it |
| A pop-up becomes narrower than its tabs need | The tab rail still contributes its own max-content; floor stays 420 |
| Another page relies on the strip's one-line look | Only `.detail-facts` is touched; the strip still spans the full width |

## Success criteria

1. Request Details / Replacement Details are narrower than today and their rows
   fill the box (no dead area on the right).
2. Nothing becomes narrower than 420px, and nothing wider than its ceiling.
3. The strip still spans the pop-up and stays readable when wrapped.
4. Existing modal suites unchanged (same pre-existing failures as the parent
   lane recorded).
