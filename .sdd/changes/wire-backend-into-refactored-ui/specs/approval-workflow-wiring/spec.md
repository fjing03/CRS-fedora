# Approval Workflow Wiring Specification

## Purpose

Give Programme Leaders a first-come-first-served approval queue wired to real pending requests, with one-click approval, mandatory-reason rejection, full auditing, and queued email notifications. Covers state transitions, slot-validity re-checking, and queue processing.

## Requirements

### Requirement: FCFS queue display

The approval queue MUST list all pending requests sorted by submission time, earliest first (FR 3.2). Each entry MUST show proposer name, subject, affected cohort(s), proposed time and venue (FR 3.3), plus the pre-computed slot validity for that request (FR 3.4). The queue MUST be reachable only by Programme Leaders.

#### Scenario: Queue ordered and populated

- GIVEN three pending requests submitted at different times
- WHEN the PL opens the approval queue
- THEN rows appear earliest-submitted first, each showing proposer, subject, cohorts, proposed time and venue, and slot-validity indicator

#### Scenario: Non-PL blocked from queue

- GIVEN an authenticated lecturer without the PL flag
- WHEN the lecturer requests the approval queue
- THEN the response is HTTP 403

### Requirement: Approval transition

Approving with one click (FR 3.5) MUST set the request to approved and its slot to occupied, and MUST release the original class block for that week so others may book it (design D7). Approval MUST first re-validate the slot: if the slot's state changed since submission, the re-check governs the outcome over the stored pre-computed validity.

#### Scenario: Approve a valid pending request

- GIVEN a pending request on a slot still held pending
- WHEN the PL confirms approval
- THEN the request becomes approved, the proposed slot becomes occupied, the original block is released and bookable, and an audit row records the approval

#### Scenario: Stale slot validity governs outcome

- GIVEN a pending request whose proposed slot was meanwhile occupied by another decision
- WHEN the PL attempts approval
- THEN the re-check fails, the request is not approved, and a clear stale-state message is shown

### Requirement: Rejection transition with mandatory reason

Rejecting MUST require a reason (FR 3.6): an empty or whitespace-only reason MUST fail validation. On success the request becomes rejected with the reason recorded and the slot returns to available.

#### Scenario: Reject without reason impossible

- GIVEN a pending request
- WHEN the PL submits rejection without a reason
- THEN validation fails and neither request nor slot changes

#### Scenario: Reject with reason

- GIVEN a pending request on a slot held pending
- WHEN the PL submits a valid reason
- THEN the request becomes rejected, the slot returns to available, and an audit row records the rejection with the reason

### Requirement: Audit trail for every decision

Every approval, rejection, and OCC outcome MUST be audited with the deciding PL's identity, timestamp, action taken, slot identifier, and rejection reason where applicable (FR 3.7, FR 4.12).

#### Scenario: Decision row contents

- GIVEN the PL decides any request
- WHEN the audit trail is inspected
- THEN the newest row carries PL identity, timestamp, action, slot ID, and the reason when rejected

### Requirement: Queued email notifications

All notification emails MUST be dispatched through the database-backed queue with the log mailer (FR 4.16): on submission to the Programme Leaders (FR 4.15); on decision to the proposer (FR 2.13); on approval also to affected-cohort students (FR 1.9). A queue worker MUST process each queued job within one minute (NFR 1.4).

#### Scenario: Emails queue and drain within budget

- GIVEN a request is submitted and then approved
- WHEN a worker drains the queue
- THEN PL submission mail, proposer decision mail, and cohort-student approval mail are delivered via the log mailer within one minute
