# Proposal — arrangement venue picker: collapsible category headers

> **Status:** draft · 2026-11-13
> **Change:** `arrangement-venue-picker-collapse`
> **Precedent:** `page-changelogs/venue-timetable-ui-changelog.md` § "2026-11-13 — mobile picker category headers are collapsible bars"
> **Lane shape:** light (proposal + design + tasks + review-log; no `specs/`)

## Why

The venue page's mobile venue sheet already groups its rows under **collapsible
category bars**: first group open, the rest collapsed, a chevron that points
right when closed, and search that opens any group holding a hit.

The replacement-arrangement page's mobile venue sheet is the same sheet built
separately, and it still renders each category as a **plain caption**:

```css
#arrangementMobile .arrangement-mobile-venue-group {
    margin: 10px 0 4px; font-size: 11px; font-weight: 700;
    letter-spacing: 0.6px; text-transform: uppercase;
    color: var(--color-on-surface-variant);
}
```

On a phone that means scrolling past every venue in every block and floor before
reaching the one you want. The user wants the arrangement sheet to behave like
the venue sheet. This is the port the venue-page work was explicitly staged for
("prove it here before porting to replacement-arrangement").

## Scope

**In scope** — `resources/views/livewire/replacement-arrangement.blade.php`
only, in two contiguous regions, plus one test file and one changelog entry.

**Explicitly out of scope**

- No class unification. The venue page keeps `.venue-picker-group`; the
  arrangement page gets `.arrangement-mobile-venue-group-*`. Unifying would mean
  moving both into `theme.css`, which `cancelled-slot-reuse` A19 claims.
- No change to `.arrangement-mobile-venue-item` or its `.selected` /
  `-main` / `-name` / `-meta` rules.
- No change to the desktop four-column `venue-dd-panel`.
- No change to selection: rows still call `venueDropdown.select(v.code)`.

### Why duplicate instead of unify

`grep -c "venue-picker-group" public/css/theme.css` → **0**. The venue page keeps
these rules page-local today (`venue-timetable.blade.php` lines 229–276). So
duplicating continues the existing arrangement rather than inventing a new one,
and it touches **no shared file**.

This is the **2nd** occurrence of the pattern. Per the repo's
promote-on-3rd-duplication rule, the 3rd copy must be promoted to
`theme.css` — recorded here so the promotion is not lost.

## What changes

### 1. CSS — `replacement-arrangement.blade.php` ~1257–1263

The caption becomes the interactive bar, mirroring the venue page:

- `--color-surface-variant` fill (darker than the sheet body and the rows),
  `1px solid var(--color-outline)`, `var(--radius-sm)`, `position: sticky; top: 0; z-index: 1`
- flex row, `space-between`, `10px 10px 10px 12px` padding, 700 weight, uppercase
- `-label` span: `min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap`
- `-chevron`: `flex: 0 0 auto; width/height: 14px; transition: transform 0.15s ease`
- `[aria-expanded="false"] .…-chevron { transform: rotate(-90deg) }`
- `-items[hidden] { display: none }`
- hover → `--color-outline-strong`; `:focus-visible` → `2px solid var(--color-primary); outline-offset: 1px`

**Compatibility guard:** every selector above is **new**. `.arrangement-mobile-venue-group`
itself keeps its name, so nothing that targets the class breaks — but its
`<div>` becomes a `<button>`. Verified safe: no test in `tests/` asserts
`.arrangement-mobile-venue-group` at all (`grep` → 0 hits).

### 2. JS — `renderArrangementVenuePicker`, lines 5332–5412

Today (lines 5372–5376, 5409):

```js
const heading = document.createElement('div');
heading.className = 'arrangement-mobile-venue-group';
heading.textContent = 'Block ' + group.block + ' · Floor ' + group.floor;
host.appendChild(heading);
// …items appended straight to host
```

Becomes:

- `heading` → `<button type="button">` with `aria-expanded`,
  `aria-controls="arrangementPickerGroup<N>"`, `data-venue-group`
- children: a `-label` span plus a chevron SVG built with
  `document.createElementNS`, **not** `createElement('svg')` — the
  `createElement('svg')` bug produces an HTML unknown element whose `<path>`
  never paints (stroke computes to `none`). The venue page hit this exact bug.
- a new `-items` div (`id="arrangementPickerGroup<N>"`, `hidden` unless expanded);
  items append to **it**, not to `host`
- a click handler that toggles **only that group**

### 3. Three behaviour rules, carried over verbatim

1. **Default** — `groupIndex === 0` expanded, all others collapsed.
2. **Search** — `const expanded = q ? true : groupIndex === 0`, so a hit is never
   hidden behind a collapsed header. This only *matters* when a search spans
   **two or more groups**; those extra groups would otherwise start collapsed.
   A search that resolves to a single venue yields one group at `groupIndex === 0`
   and opens with or without the rule — so the shipped `B010` assertion passes
   either way and proves nothing. Note `arrangementVenueParts` takes the **first
   digit** (`/\d/`, blade 5322), so `B010` groups under floor `'0'`, not "Floor 1".
3. **Selection** — still `venueDropdown.select(v.code)`; guards, chips and the
   selected state are untouched.

### 4. Tests — `tests/venue-dropdown-responsive.spec.ts`

One new test beside the existing mobile-sheet test:

- first group `aria-expanded="true"`, every later group `"false"`
- header background ≠ item background ≠ sheet body background
- each header is a `BUTTON`, contains exactly one
  `svg.arrangement-mobile-venue-group-chevron`, and its `aria-controls` resolves
- clicking header 1 collapses it (items hidden); clicking header 2 expands it
- chevron `transform` changes after collapse — **polled**, because the 0.15s
  transition races a bare read
- **rule 2 proven properly:** search a term matching venues in **more than one
  group** (e.g. `tutorial`, matched via `humanizeType`), first assert the group
  count is **> 1** so the test cannot silently degrade to the vacuous
  single-group case, then assert **every** matching group is expanded.
  A single-venue search (`B010`, `B100`) proves nothing.

Regression re-runs:

- `tests/venue-dropdown-responsive.spec.ts` — the sheet itself
- `tests/arrangement-mobile.spec.ts` — its `t22DifferentVenue` helper queries
  `.arrangement-mobile-venue-item` / `data-venue-code`, both preserved

### 5. Changelog — `page-changelogs/replacement-arrangement-changelog.md`

A new **`##` entry at the top** of the file (its convention is newest-first; the
newest `##` is currently `## 2026-11-13 — cancelled-slot-reuse Part B: the room-only holder is now the
ACTUAL holder (S24/S25)` at line 1 — re-checked 2026-11-14).
It cross-references the venue-page precedent and carries a one-line **ownership
note**.

The note names **B03**, not B02: `cancelled-slot-reuse` **B02** edits the
arrangement blade (tooltip composition) and **B03** is the one that rewrites
*this changelog* ("Update arrangement changelog"). `renderArrangementVenuePicker`
belongs to this lane and must not be reverted by either.

The file also holds an uncommitted `###` block at lines **3812–3846** (the
2026-11-12 venue-sheet entry and the 2026-11-13 Selection Summary entry). Those
stay exactly where they are — this lane does not move, reorder or rewrite them.


## ⚠️ Constraint: this file is already dirty

This is not a normal clean-tree change and must not be treated as one.

```
resources/views/livewire/replacement-arrangement.blade.php   +424 / -164  uncommitted
page-changelogs/replacement-arrangement-changelog.md         +35          uncommitted
tests/venue-dropdown-responsive.spec.ts                      untracked
```

Two earlier sessions' work is uncommitted in that file, and **the regions this
proposal edits are inside that uncommitted work**:

- the CSS at 1257–1263 is part of a 94-line insertion starting at line 1226;
- `renderArrangementVenuePicker` exists only in the uncommitted diff.

Consequences:

- `git checkout -- resources/views/livewire/replacement-arrangement.blade.php`
  would destroy the 2026-11-12 venue-sheet work **and** the 2026-11-13
  Selection Summary arrow. It is not a valid rollback.
- `git checkout` does nothing to an **untracked** file, so the test cannot be
  reverted that way either.
- **A `cp` backup restored wholesale is just as destructive**: it drops every
  change another lane writes to the file between backup and restore. There is a
  lane doing exactly that — see below.

### A second lane is applying on these same two files

`cancelled-slot-reuse` is **`status: applying`** with
`implementation_approved: true`, and it is mid-run (its
`app/CancelledSlotCleanup*.php` services were written minutes ago). Two of its
unticked tasks land on the exact files this lane edits:

| Its task | What it does | File |
|---|---|---|
| **B02** (1.5h) | "Update arrangement desktop and mobile" — tooltip composition | `replacement-arrangement.blade.php` |
| **B03** (1.5h) | "Update arrangement changelog" | `page-changelogs/replacement-arrangement-changelog.md` |

So **rolling this lane back by restoring a whole-file copy would silently revert
that lane's work.** That is why rollback here must be a region restore — there is
no git command that isolates one lane's hunks in this file.

**Apply gate — absolute.** Do not apply until `cancelled-slot-reuse` **B02 and
B03 are both ticked**. There is no "apply alongside" option: B02 is the task most
likely to touch `renderArrangementVenuePicker`'s neighbourhood, and no rollback
mechanism can cleanly separate two lanes' edits in one file (see below).

This lane does **not** commit the pre-existing uncommitted work, does not
reformat it, and touches only its own hunks.

## Rollback summary

**Region restore only. There is no git command that works here.**

Why not:

- `git checkout` / `git restore` / `git stash` / `git clean` — revert or hide the
  pre-existing `+424/−164`, which is not this lane's to discard.
- `git diff <file> > lane.patch` then `git apply -R lane.patch` — **does not
  work either.** `git diff` diffs against the index/HEAD, so the patch contains
  *every* uncommitted change in that file: the 94-line venue-sheet CSS insertion
  at 1226–1319, `renderArrangementVenuePicker` itself, the Selection Summary
  arrow, this lane's edits, and any `cancelled-slot-reuse` hunk. Reverting it
  reverts all of it.
- `git diff -L<range>` — diffs against HEAD, so it would delete the uncommitted
  venue-sheet CSS wholesale.
- `cp` restore of the whole file — drops anything another lane wrote in between.

So the only safe mechanism is to put back **the two regions, and nothing else**:

| What | How |
|---|---|
| Blade | **Delete the whole CSS block T2 inserted** (bar, `-label`, `-chevron`, the `[aria-expanded="false"]` rotation, `-items[hidden]`, and the hover/focus pair — do not count them, just remove the block), **then** paste back the original `.arrangement-mobile-venue-group` rule saved in T1 — the selector through its closing brace, 1257–1264. Region 2 is restored wholesale: the whole `renderArrangementVenuePicker` function. Re-read both regions first to confirm the other lane has not edited them. |
| Changelog | Delete only the new `##` entry added at the top. |
| Test file | Delete the one added test. The file is **untracked**, so `git checkout` does nothing — and any commit of it drags another session's file along. |

A `diff -u` of the saved region text against the current region text, applied
with `patch -R` and ≥3 lines of context, is an acceptable automation of the same
thing — it fails loudly on a context mismatch instead of clobbering.

`git diff` stays **read-only**: T6 uses it to confirm the only hunks in the file
are this lane's two regions. It is never used to produce a revert.

