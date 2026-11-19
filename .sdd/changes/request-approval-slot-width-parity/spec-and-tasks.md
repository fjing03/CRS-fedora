# Request Approval: matching slot-column widths

Status: applied and verified locally. Independent review returned HTTP 429; the owner explicitly waived review for this fix.

## Scope and acceptance

- Keep the existing scrollable table, mobile cards, breakpoints, labels and actions. Match Original Slot to the roomier Requested Slot treatment: give both a common responsive width budget and keep normal time ranges on one line. Dates and long countdowns may wrap; don't truncate them.
- A browser-only check showed that forcing both columns to 224px at all sizes introduced needless scrolling on the current 1440px view. Use a responsive budget instead, and override the countdown's `nowrap` within the Requested Slot cell so a long Pending label cannot stretch only that column.
- Scope the CSS and column classes to this page's table. Keep other columns on the existing automatic sizing algorithm; no table-layout switch, shared theme change, selector renaming, or workflow changes.
- Measure the two rendered widths before/after at 769/1024/1440/1920px, including a labelled browser-only long Pending sample if none is served. Ensure the current 1440px decided table still fits, and 390px cards are unchanged. For this visual-only fix use targeted browser measurements/captures rather than rerunning unrelated suites. No business data writes.

## Verification

| Viewport | Original Slot | Requested Slot |
|---:|---:|---:|
| 769px | 184px | 184px |
| 1024px | 184px | 184px |
| 1440px | 184px | 184px |
| 1920px | 224px | 224px |

Both the four served decided records and a browser-only Pending sample matched these widths. All ordinary time ranges stayed on one line; the 18-working-day overdue label wrapped without stretching Requested Slot. The decided table fits at 1440px; the mixed browser sample with Pending controls still uses the existing horizontal scroller there. The 390px view still shows four cards and hides the table. Screenshots and exact geometry are in `/tmp/crs-approval-slot-width/`. No business data changed.
