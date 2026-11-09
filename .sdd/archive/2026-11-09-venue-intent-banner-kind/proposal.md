# Proposal — `venue-intent-banner-kind` (the user's option 2: ONE voice, kind-complete)

**Refi from the C2 lane (`venue-c2-toast-noise` 56d769b addendum):** the
manual-branch arrival is NOT silent — the amber `#bookingIntent` banner +
the cell pulse + the subject pre-select already speak. A floating toast =
a redundant third voice (house rule 6). The kind move = complete the EXISTING
ONE voice: the banner gains the fallback ATTRIBUTION clause.

## The change (1 clause, 1 blade)

`resources/views/livewire/replacement-arrangement.blade.php`
`renderBookingIntent()`'s `intentNeedsManual` branch: when
`fallbackAttribution` is live (this arrival's fallback prefill armed it —
the manual path never consumed it), append under the reason:

> *Pre-selected AMCS2093 (L) — its sessions fit B110; pick another free slot — the pre-selection stays.*

Frozen wording (echoes the success-toast family "its sessions fit …"; no
new colors/CSS — a plain `<em>` inherits the banner's tokens).

## Out of scope
- The no-match banner (zero-compatible family) — untouched.
- The selectBlock user-click toast — untouched.
- The floating toast — rejected by the user's option choice.
- The other banners' texts — untouched.

## Verify
- The `#bookingIntent` manual banner carries the attribution clause on the
  receipted trap arrival (W8-Tue 11:00 B110, 5770) — the C2/own-busy receipt.
- The no-match + no-attribution arrivals unchanged.
- venue-intent-modal spec: the file family green incl. the NEW row.
