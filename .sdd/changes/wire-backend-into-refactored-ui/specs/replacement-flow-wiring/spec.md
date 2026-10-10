# Replacement Flow Wiring Specification

## Purpose

Wire the lecturer replacement journey onto the real engine, OCC reservation, and audit trail — slot-grid semantics, concurrency, PL inheritance.

## Requirements

### Requirement: Entry, history, venue view, PL inheritance

Replacement Home MUST list the lecturer's own classes, conflicted included, with start-a-replacement from details (FR 2.4), and MUST provide access to own request history (FR 2.15) and venue timetables (FR 2.14); the PL inherits all (FR 3.1).

#### Scenario: Start from own conflicted class

- GIVEN a conflicted class
- WHEN the lecturer starts a replacement from its details
- THEN the arrangement opens pre-filled for it

#### Scenario: PL inherits flow

- GIVEN a Programme Leader
- WHEN they submit a replacement request
- THEN the flow matches a lecturer's

### Requirement: Venue selection and recalculation

The venue dropdown MUST default to the original class venue (FR 2.5); changing it MUST recalculate slots within 500 ms (FR 2.6, NFR 1.1).

#### Scenario: Venue change recalculates

- GIVEN an arrangement on the default venue
- WHEN the lecturer picks another venue
- THEN the grid reflects it within 500 ms

### Requirement: Slot grid semantics

The arrangement MUST show a weekly time × day grid, colour-coded (FR 2.7). Clicking a green cell MUST select it as the proposal (FR 2.8); a multi-slot class MUST select its contiguous duration block — a 2-hour class needs 2 adjacent green cells (§3). Cells MUST derive the §10.0 table-B states with exact labels/tokens (FR 4.11), display-only; Reserved by Others = a pending slot held by another's active request. Only the clicked anchor slot is reserved; a block's other cells stay available (prototype limitation recorded in the design's Open Questions; transaction semantics per design D5).

#### Scenario: Two-hour contiguous selection

- GIVEN a 2-hour class with adjacent green cells
- WHEN the lecturer clicks the first
- THEN the whole contiguous block renders as Your Current Selection

#### Scenario: Reserved by Others cell

- GIVEN another lecturer's pending request holds a slot
- WHEN the grid renders
- THEN that cell shows "Reserved by Others", not selectable

### Requirement: Engine slot computation

Available slots MUST come from the four-vector intersection — lecturer × cohort free schedule(s) × room vacancy × capacity (FR 4.3); three vectors for a single cohort (FR 4.5). Session-type-restricted venues MUST be filtered (FR 4.7). No common slot — even a fully occupied day — MUST show "No available slots", never crash (FR 4.4, FR 4.6).

#### Scenario: Disallowed venue type filtered out

- GIVEN a lab-only module and a wrong-type venue
- WHEN the grid computes
- THEN that venue yields no available slots

#### Scenario: Fully occupied day

- GIVEN a lecturer booked all day
- WHEN the grid computes
- THEN "No available slots" displays

### Requirement: Submission with OCC

Submitting MUST create a pending request for PL approval (FR 2.9) behind a slot version check (FR 4.8). Two concurrent submissions: exactly one wins (FR 4.9); the loser gets a conflict alert (FR 4.10), its just-created request row removed while the conflict audit row survives (design D5). Both outcomes MUST be audited (FR 4.12).

#### Scenario: Concurrent submissions

- GIVEN two lecturers submitting for the same slot
- WHEN both execute
- THEN exactly one wins; the loser sees a conflict alert, holds no request row; the conflict audit row survives; both outcomes are audited

### Requirement: Cancellations

A lecturer MUST cancel their own pending request before decision (FR 2.10): the slot returns to available; nothing else changes. A lecturer MUST cancel their own class with a valid, mandatory reason (FR 2.16).

#### Scenario: Cancel pending frees only what it held

- GIVEN a pending request on one slot
- WHEN the owner cancels it
- THEN the slot returns to available, nothing else changes

#### Scenario: Class cancellation requires reason

- GIVEN a class cancellation without a reason
- WHEN processed
- THEN validation fails; the class stays scheduled

## Amendments (2026-10-10 unfreeze, batch 2 of 3)

Stale citation fix: the anchor-slot limitation cited "design D11", but the design's Architecture Decisions table is D1–D10 — the limitation lives in the design's Open Questions (with transaction semantics in D5). No behavioral change.
