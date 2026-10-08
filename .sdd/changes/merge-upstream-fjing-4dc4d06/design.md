# Design — merge-upstream-fjing-4dc4d06

**Status:** draft (Batch 2) · **Frozen inputs:** explore-brief.md, proposal.md (frozen 2026-10-08)
**Nature:** mechanical merge — zero authored behaviour; this document is the exact choreography.

## §1 Merge choreography (execution order is normative)

```
# G2a — SHA pin + HEAD pin
git rev-parse upstream/fjing            # must print 4dc4d06…, else STOP → re-explore
git rev-parse HEAD                      # must print 52e25ca…, else STOP → re-explore

# G2b — preflight against the pinned SHA
git merge-tree --write-tree HEAD 4dc4d06
# exit 0 + no conflicted-file list → proceed; anything else → proposal invalid, STOP

# G2c — dirty-tree intersect
git status --porcelain | sort > /tmp/opencode/porcelain.txt
git diff --name-only 36c4d2c..4dc4d06 | sort > /tmp/opencode/census.txt
comm -12 /tmp/opencode/porcelain.txt /tmp/opencode/census.txt   # must print NOTHING, else ABORT (never stash/rm)

# Merge (by SHA)
git merge --no-ff 4dc4d06 -m "<message per §4>"

# Post-merge structural check (criterion 1 + 2 owner)
git log --format=%P -1 HEAD              # two parents: <52e25ca-sha> 4dc4d06…
git diff --name-only HEAD^1..HEAD | sort > /tmp/opencode/postmerge.txt
comm -13 /tmp/opencode/census.txt /tmp/opencode/postmerge.txt   # must print NOTHING (no extra paths)
comm -23 /tmp/opencode/census.txt /tmp/opencode/postmerge.txt   # must print NOTHING (no dropped paths)
# → exact equality with the 23-path census (frozen proposal: "exactly"). Any output → §6 rollback.
```

Expected `git status` pre-merge: untracked `.commandcode/` **and this change's own `.sdd/changes/merge-upstream-fjing-4dc4d06/`** — both outside the census → G2c passes by construction (matches §6's expected status). Scratch paths live under `/tmp/opencode/` (workspace preference).

## §2 Post-merge semantic checks (proposal step 3 → concrete greps)

| # | File | Check | Expected |
|---|---|---|---|
| S1 | `public/js/ui-common.js` | `grep -nE "window\.(currentWeek|buildTimetable|updateWeekSubtitle|updateSummary|updateProgress)"` (B2-16: bare pipes + `-E`, never `\|`) | Wave-1 guard **sentinels present**: `window.currentWeek = currentWeek;` and the three `typeof window.* === 'function'` guards inside `jumpToToday()` |
| S2 | `public/js/ui-common.js` | `grep -n "event-conflict"` | their `4dc4d06` hunk **present here** — anchored: preflight exploration confirmed `git diff 36c4d2c..4dc4d06` adds `classList.add('event-conflict')` in this file (theme.css may also carry the style; that's additive, not a substitute) |
| S2b | `public/js/ui-common.js` | **added-line parity**: `git diff 36c4d2c..4dc4d06 -- public/js/ui-common.js \| grep '^+[^+]' \| sort` vs `git diff 36c4d2c..HEAD -- public/js/ui-common.js \| grep '^+[^+]' \| sort`, then `comm -23 <their-added> <head-added>` = **empty** (direction: nothing in THEIR added set missing from HEAD's added set; the reverse direction is non-empty by design — our Wave-1 guards) | every added line of their delta present in the merge (hunk-drop impossible); our `jumpToToday` guards are the only expected HEAD-side extra |
| S3 | `public/js/mock-data.js` | `grep -n "currentUser"` + subject-name spot check | `staffId: '5770'` **still hardcoded** (unchanged); real subject names present (e.g. not "Placeholder") |
| S4 | 6 × `page-changelogs/*` | header/section structure intact, both sides' entries interleaved | well-formed markdown, no duplicate section IDs |
| S5 | `.sdd/archive/2026-10-07-venue-event-blocks/design.md` | read their acceptance notes (read-only) → derive the §3 smoke assertions; **fallback if notes lack concrete selectors:** derive them from added class/token names in `git diff 36c4d2c..4dc4d06` | notes consistent with merged source; smoke never blocks on under-specified notes |
| S6 | `resources/views/partials/ui-nav-bar.blade.php` | `git diff HEAD^1..HEAD -- <path>` = **empty** | Wave-3 scope untouched |

## §3 Live smoke (proposal criterion 5)

Serve-restart sequence (Risks row 3 — **never `pkill -9 php`**):

```
pkill -f "[a]rtisan serve" || true
rm -f storage/framework/views/*.php
php artisan serve --port=8000 &        # verify http 200 on /login/student
```

Then authenticated GETs (student `25RSD0001` / staff `5425`, `Tarumt@2026`), one visible assertion per delta theme:

| Page (route under test) | Delta theme | Visible assertion |
|---|---|---|
| venue timetable UI | `a86e327` cohort-style event blocks | booked classes render as event blocks (not bare rows) |
| replacement home UI | `4dc4d06` origin trail + conflict colouring | page renders; conflict/origin trail elements present in markup |
| cohort timetable UI | `142ec2e` holiday badges + `bc748a3` tabbed modals | page renders; badge/modal markup present in source |
| any of the above | `f5d12ed` real subject names | module names non-placeholder |

Method: `curl` with session cookie (login flow as in Wave-2's smoke); assert HTTP 200 + grep the markup for the theme's class/element names (exact selectors read from upstream's archived design.md during S5, recorded in tasks execution notes).

## §4 Merge commit message (template)

```
merge: sync upstream/fjing (4dc4d06) — venue event blocks + UI polish

SDD merge-upstream-fjing-4dc4d06. Delta 36c4d2c..4dc4d06 (5 commits, 23
paths: UI/docs/SDD-history + one Playwright spec) — venue timetable
cohort-style event blocks, mock-data real subject names, holiday badges,
tabbed info modals, replacement origin trail + conflict colouring.
merge-tree preflight clean vs pinned 4dc4d06 (no resolution ledger);
post-merge census equality verified; 7 path-overlap files auto-merged
(explore-brief §1/§2); Wave-1 jumpToToday guards preserved; Wave-3
static-panel scope untouched. Gates re-run at merge commit per frozen
proposal criteria 4-5.
```

## §5 Gate procedures (unchanged house set)

| Gate | Command | Expected |
|---|---|---|
| phpunit | `php vendor/phpunit/phpunit/phpunit --no-coverage` | **110/110** (invariants 35/44/1988 unchanged); 523 assertions is the recorded Wave-2 T6.2 number — gate on the test count, treat assertion drift as investigate-don't-fail |
| phpstan | `vendor/bin/phpstan analyse --memory-limit=1G --no-progress` | 0 errors |
| pint | `composer run lint:check` | adminer-only baseline |
| invariants | (inside phpunit) T-1..T-5 | 35/44/1988 unchanged (delta touches no DB path) |

## §6 Rollback protocol

- G2a/G2c failures: routed in §1 (STOP/ABORT) — nothing to abort, no merge exists; report and stop.
- Failure while the merge is **in progress and uncommitted** (e.g. G2b surprise re-run, merge-time conflict): `git merge --abort` → tree reverts; report and stop.
- **Any post-commit failure, including house-gate failures or the §1 structural check:** verify `HEAD^1 = 52e25ca`, and expected status = only untracked `.commandcode/` + this change's own `.sdd/changes/merge-upstream-fjing-4dc4d06/` files (untracked until §7 commit 2), then `git reset --hard 52e25ca` — untracked `.sdd/` survives by construction.
- No force-push anywhere; upstream remains pull-only.

## §7 Commit plan after merge

1. The merge commit itself (§4 message) — upstream content only.
2. `docs(sdd): merge-upstream-fjing-4dc4d06 — frozen artifacts + apply/verify records` — this change's `.sdd/` dir + changelog append (one commit, merge-change precedent).
3. Archive moves the change dir (second `docs(sdd)` commit), per house archive choreography.
