# Approved cancellation requirements

Frozen proposal/design are authority. All operations act on the authenticated owner's scheduled class.

## R1 — distinct source and landing identities
- WHEN cancelling an opened Approved replacement THEN client passes exact request ID and shown landing week/day, separately from normal session IDs and pending withdrawal IDs.
- Server MUST match request + source owner, source session ID (single), active semester, Approved state and landing coordinate under lock; missing/stale/foreign/mismatched targets MUST refuse without normal fallback.
- Cross-week/day/room landings retain the original request source week for history and Arrange Now. A source outside the cancellation window MUST NOT prevent cancellation of an in-window landing. An out-of-window landing MUST refuse, including single cancellation.
- Approved make-up notes MUST remain non-tickable. Normal row selection on another occurrence of the same session MUST NOT be hidden merely because that session also owns the clicked replacement.

## R2 — atomic selection and safe release
- Single and mixed day selections MUST share the batch/lock/release implementation. Every selected normal ID MUST resolve, otherwise the entire selection refuses. Repeated IDs within one list may normalize; conflicting normal/approved/pending targets MUST not be processed twice.
- Approved-only/request-only calls with no normal IDs MUST NOT silently cancel the entire normal day; legacy empty-selection whole-day callers retain existing behavior.
- Before writes lock the complete source-session/request/original/landing union through OccurrenceMutationLocks and revalidate all targets. Approved-only targets MUST NOT plan or release original cells.
- Approved cancellation MUST validate reason and trimmed Other detail, active semester, landing week/day/date, holiday/Sunday and server-side two-working-day cancellation window, full proven claim and newer-holder safety. Reuse the existing calendar, mapper and strict attached release.
- Exactly the proven occupied 1/2/4-cell landing run MUST become available with cleared owner and versions incremented once. Its own junction links are deleted; replacement_span, source exception/reason and approval evidence remain. A coherent legacy anchor/span fallback MUST release the full mapped run; missing/contradicting span or another active holder MUST refuse.
- Request MUST persist Cancelled and decided_at. Original history MUST NOT be rewritten or fabricated. The release/audit is linked to the exact request and retains source, actual landing and reason/detail evidence.
- Any later failure MUST roll back ALL selected classes/requests/links/slots/versions/audits. No refusal/rollback notification is sent; successful notification fan-out happens only after outer commit and failures do not turn a saved cancellation into a false rollback report. Retrying a closed request MUST not release cells again.

## R3 — honest client receipt
- Every acknowledged class receipt MUST include source session/week and server schedule facts; replacement receipt additionally identifies request and actual landing coordinates.
- Success count and list MUST be exactly the returned cancelled receipt set, not selected ticks plus a fabricated opened class. A defensive partial-receipt response MUST show only acknowledged rows.
- Approved cancellation MUST NOT invoke recurring cancellation mutation, append conflicted MockData rows, or create a recurring ledger entry at the landing/source week.
- Single Approved success keeps Arrange Now/Later and uses receipt source session/week for navigation. Day Close, Escape, header/overlay dismissal MUST all refresh server state after success. Failed calls leave business client state/ledger unchanged and show the existing inline refusal.
- Stale day-scope responses from an earlier opened modal MUST NOT replace the current modal's scope.

## R4 — history and all-view consistency
- After real isolated cancellation all four timetables MUST omit the former active replacement. The exact source cancellation MUST be visible again under the own-Approved rule; other sessions/weeks stay unchanged. No new second red landing-history event is introduced.
- My Replacements MUST retain one original occurrence row and request identity, show Need to Arrange, blank requested schedule and no Proposed tab. Student Class Replacements MUST show Awaiting Arrangement without private reasons. Persisted request/audit stays Cancelled.
- Notifications MUST use actual landing week/day/room/full span in body and tooltip, including junction-less fallback. Additive request linking retains existing request-linked inactive policy. Do not display the old source exception reason as the replacement's new cancellation reason; private detail remains in audit, never student payload. Normal request-less notices stay unchanged.

## R5 — safety and quality evidence
Backend writing tests MUST force and query socket/port/database/data_directory for a separate class_replacement_testing instance, refusing the wrong target before writes. Manual :8030 records, cron and server remain untouched/available. Browser mutation calls MUST be intercepted/refused, never reach manual server; label synthetic UI contracts separately from real isolated persistence. Preserve unrelated work. Required evidence includes focused pre-fix failures via saved original source override, backend targeted regressions, read-only browser UI contracts, syntax/lint/PHPStan, independent implementation review and scoped verify. Whole-feature acceptance, cleanup/archive/commit/push are separate gates.
