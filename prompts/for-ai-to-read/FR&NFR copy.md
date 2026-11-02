**Chapter 3**

> **Working copy for AI reading — `prompts/for-ai-to-read/FR&NFR copy.md`**
>
> Source: `../../../final/FR&NFR.md` (Chapter 3, §3.4). This is a **verbatim
> copy**, kept beside `CodingMAIN.md` so every agent reads the requirements from
> one place; `CodingMAIN.md` §7 quotes §3.4.1 and §3.4.2 from here.
>
> **Flexible to change — but only when very necessary.** A requirement may be
> amended when the implementation teaches something the paper missed (the way
> FR 2.17 arrived from the shipped 3-working-day rule). Before editing:
>
> 1. the change must be **forced** by a shipped behaviour, an explicit user
>    ruling, or a corrected misunderstanding — not a preference or a wording
>    nicety;
> 2. **keep the numbering stable** — append (2.17, 4.17 …), never insert or
>    renumber: FR 3.1 cites "FR 2.11 and FR 2.12", and tests, changelogs and
>    SDD lanes cite FR numbers;
> 3. **mirror it in three places** — this file, `CodingMAIN.md` §7 (sync line +
>    §7.1 traceability + §7.2/§7.3 verbatim tables), and the FYP1 report
>    (Table 3.5/3.6). A requirement that exists in only one of them is a defect;
> 4. **never delete or renumber** an existing FR/NFR — mark it superseded and
>    say why;
> 5. add a row to the change log at the bottom of this file.
>
> **Version:** `v2026-11-02` — **49 FRs / 26 NFRs**. Latest update:
> **2026-11-02** (added **FR 2.17**: a lecturer may only propose a slot at least
> three working days ahead, the third working day itself staying selectable).
>
> **Divergence from the report (known):** the FYP1 report `.docx` still carries
> 48 FRs (Table 3.5 has no 2.17 row). This copy and `CodingMAIN.md` §7 are the
> newer state; the report is updated only when the user asks.


**3.4 Functional Requirements and Non-Functional Requirements**

The requirements listed below are taken from the problem analysis and the literature review (for example, Babaei et al., 2015) in Chapters 1 and 2. The system is divided into five functional modules, as shown in Chapter 1, Section 1.5.1. Each functional requirement can be tested and is linked to the five modules and the five objectives.

**3.4.1 Functional Requirements**

*Table 3.5* groups the 49 functional requirements by actor.

## Table 3.5: Functional Requirements

| No. | Requirement | Objective |
| :---- | :---- | :---- |
| 1.0 | **Student** |  |
| 1.1 | Students shall be able to log in using Student ID and password. | 4, 5 |
| 1.2 | Students shall be able to view their cohort timetable. | 5 |
| 1.3 | Students shall be able to view the status of replacement requests for their cohort. | 3, 5 |
| 1.4 | Students shall be able to view upcoming replacement details (new date, time and venue) for their cohort. | 3, 5 |
| 1.5 | Students shall not be able to create any replacement requests. | 4 |
| 1.6 | Students shall not be able to edit any replacement requests. | 4 |
| 1.7 | Students shall not be able to delete any replacement requests. | 4 |
| 1.8 | Students shall not be able to modify any timetable data. | 4 |
| 1.9 | Students shall receive email notifications for timetable updates. | 4, 5 |
| 2.0 | **Lecturer** |  |
| 2.1 | Lecturers shall be able to log in using Staff ID (four digits with an optional "P" prefix, e.g., P5425 or 5425) and password. | 4, 5 |
| 2.2 | Lecturers shall be able to view their own teaching timetable. | 5 |
| 2.3 | Lecturers shall be able to click a class on their own timetable, including conflicted or cancelled classes, to view its details. | 1 |
| 2.4 | Lecturers shall be able to start a replacement for a class from its details view. | 1 |
| 2.5 | Lecturers shall be able to select a replacement venue from a dropdown that defaults to the original venue of the class. | 1 |
| 2.6 | Lecturers shall be able to change the venue dropdown while arranging a replacement, which shall recalculate available slots within 500 ms. | 1 |
| 2.7 | Lecturers shall be able to view a weekly timetable grid (time x day) with colour-coded cells showing slot availability. | 1 |
| 2.8 | Lecturers shall be able to click a green slot to select it as the proposed replacement. | 1 |
| 2.9 | Lecturers shall be able to submit the replacement request for Programme Leader approval. | 2, 3 |
| 2.10 | Lecturers shall be able to cancel their own pending requests before the Programme Leader approves or rejects them. | 2 |
| 2.11 | Lecturers shall not be able to view or edit other lecturers' requests. | 4 |
| 2.12 | Lecturers shall not be able to approve or reject any request. | 4 |
| 2.13 | Lecturers shall receive an automated email when their submitted request is approved or rejected (Laravel Documentation, 2026c). | 4, 5 |
| 2.14 | Lecturers shall be able to view the timetable of a selected venue. | 1, 5 |
| 2.15 | Lecturers shall be able to view the history of their own replacement requests. | 3 |
| 2.16 | Lecturers shall be able to cancel their own scheduled class by providing a valid reason. | 4, 5 |
| 2.17 | Lecturers shall only be able to propose a replacement slot on a date at least three working days (Monday–Friday, excluding public holidays) after the current date; the third working day itself shall remain selectable. | 1, 3 |
| 3.0 | **Programme Leader** |  |
| 3.1 | Programme Leaders shall inherit all lecturer privileges except FR 2.11 and FR 2.12 (FR 2.1–2.10, 2.13–2.16) | 4 |
| 3.2 | Programme Leaders shall be able to view a FCFS queue of all pending replacement requests, sorted by submission time (earliest first). | 3 |
| 3.3 | Programme Leaders shall be able to view the proposer name, subject, affected cohort(s), proposed time, and proposed venue for each request in the queue. | 3 |
| 3.4 | Programme Leaders shall be able to view the pre-computed slot validity for each request. | 1, 3 |
| 3.5 | Programme Leaders shall be able to approve a request with one click. | 3 |
| 3.6 | Programme Leaders shall be able to reject a request by providing a mandatory reason. | 3 |
| 3.7 | Programme Leaders shall have every approval and rejection action automatically recorded in the audit trail, including Programme Leader identity, timestamp, action taken, slot ID, and rejection reason (if any). | 2, 3 |
| 4.0 | **System** |  |
| 4.1 | The system shall store timetable data for 14 teaching staff, 14 cohorts, and 23 Block B rooms as seed data. | 5 |
| 4.2 | The system shall store each room's details including room name, capacity, room type (Tutorial / Lecture Hall / Lab / Cisco Lab), and allowed session types (L, T, P, or a combination). | 1, 5 |
| 4.3 | The system shall apply a four-part check with a three-set intersection of lecturer availability, cohort free schedules (supporting multiple cohorts), and room vacancy, plus venue filtering that checks room capacity as the fourth part. | 1 |
| 4.4 | The system shall display a "No available slots" message when no common slot is found. | 1 |
| 4.5 | The system shall compute the intersection for single-cohort requests with one cohort schedule (lecturer x one cohort x room), and the capacity check shall still apply. | 1 |
| 4.6 | The system shall return an empty result set when a lecturer is fully occupied for the entire day. | 1 |
| 4.7 | For modules with session-type restrictions, the system shall filter venues to only those that allow the module's session type. | 1 |
| 4.8 | The system shall implement Optimistic Concurrency Control using an integer version column pattern on the time_slots table (Kung & Robinson, 1981). | 2 |
| 4.9 | The system shall allow exactly one submission to succeed when two lecturers submit a request for the same slot at the same time. | 2 |
| 4.10 | The system shall return a conflict alert to the lecturer whose submission did not succeed. | 2 |
| 4.11 | The system shall manage the slot state machine transitioning a slot's status through Available, Pending, and Occupied (state-machine method in Harel, 1987). | 2, 3 |
| 4.12 | The system shall log all Optimistic Concurrency Control validation outcomes in the audit trail (Kung & Robinson, 1981). | 2 |
| 4.13 | The system shall enforce Role-Based Access Control with three roles: Student (view-only), Lecturer (create/submit/cancel own), and Programme Leader (hybrid — inherits lecturer privileges plus approve and reject rights) (Laravel Documentation, 2026b). | 4 |
| 4.14 | The system shall redirect unauthenticated users to the login page. | 4 |
| 4.15 | The system shall send an automated email notification to the Programme Leader when a new replacement request is submitted. | 4, 5 |
| 4.16 | The system shall send all email notifications in the background through a database-backed queue (Laravel Documentation, 2026d). | 4, 5 |

**3.4.2 Non-Functional Requirements**

*Table 3.6* groups the 26 non-functional requirements into seven categories.

## Table 3.6: Non-Functional Requirements

| No. | Requirement |
| :---- | :---- |
| 1.0 | **Performance** |
| 1.1 | The system shall complete the matrix intersection calculation within 500 milliseconds from user input to grid display. |
| 1.2 | The system shall complete OCC validation with less than 100 ms extra time beyond the database write (Kung & Robinson, 1981). |
| 1.3 | The system shall load all dashboard pages within 2 seconds under normal load. |
| 1.4 | The system shall process queued email jobs within 1 minute of the queue worker starting. |
| 2.0 | **Security** |
| 2.1 | The system shall enforce role-based permission checks on all dashboard routes using Laravel middleware, code that runs before a page loads to check the user's rights (Laravel Documentation, 2026b). |
| 2.2 | The system shall hash all passwords using Bcrypt, a method that scrambles them so they cannot be read even if the data is stolen, and never store passwords in plain text (Laravel Documentation, 2026a). |
| 2.3 | The system shall use Laravel's Eloquent Object-Relational Mapping (ORM), which lets code work with database tables as normal objects, for all database queries to prevent SQL (Structured Query Language) injection, an attack that steals data through harmful database commands (Laravel Documentation, 2026g). |
| 2.4 | The system shall terminate inactive staff sessions after 30 minutes (Laravel Documentation, 2026e). |
| 2.5 | The system shall terminate inactive student sessions after 30 days (Laravel Documentation, 2026e). |
| 2.6 | The system shall apply Cross-Site Request Forgery (CSRF) token verification on all POST, PUT, and DELETE form submissions (Laravel Documentation, 2026h). |
| 3.0 | **Usability** |
| 3.1 | The system shall provide a responsive web interface that works on desktop, tablet, and mobile screen sizes. |
| 3.2 | The system shall display the slot availability grid with colour-coded cells labelled with their meaning for accessibility. |
| 3.3 | The system shall display error messages in clear and understandable language. |
| 3.4 | The system shall redirect each user to their own timetable page immediately after login. |
| 3.5 | The system shall support light and dark themes. |
| 3.6 | The system shall remember the user's chosen theme across sessions. |
| 4.0 | **Reliability** |
| 4.1 | The system shall guarantee that no double-booking occurs under any situation where many users submit at the same time (Kung & Robinson, 1981). |
| 4.2 | The system shall maintain at least 99% uptime during the evaluation and demonstration periods. |
| 4.3 | The system shall support database backup via PostgreSQL pg_dump, which is a tool that saves a copy of the database. |
| 5.0 | **Maintainability** |
| 5.1 | The system shall follow the PHP Standard Recommendation 12 (PSR-12) coding standard, verifiable through Laravel Pint. |
| 5.2 | The system shall track all database schema changes through Laravel migration files in database/migrations/. |
| 5.3 | The system shall separate core logic into dedicated service classes (e.g., MatrixIntersectionEngine, OCCValidator). |
| 6.0 | **Scalability** |
| 6.1 | The system shall support up to 50 concurrent users without performance degradation. |
| 6.2 | The system shall process email notifications through a separate queue worker to avoid blocking the web application (Laravel Documentation, 2026d). |
| 7.0 | **Legal and Ethical** |
| 7.1 | The system shall process only the minimum necessary data, which are timetable schedules, staff names, staff IDs, student names, student IDs, cohort codes, and TAR UMT email addresses (Personal Data Protection Act 2010 [Act 709]). |
| 7.2 | The system shall be developed only on localhost to ensure no university data leaves TAR UMT. |

In the security category, CSRF is a security check that stops a fake website from sending a request on a user's behalf.

## Change log (this copy)

| Version | Date | Change |
|---|---|---|
| `v2026-08-24` | 2026-08-24 | Resynced to the revised FR&NFR: FR renumbered (Student 1.1–1.9, Lecturer 2.1–2.16, PL 3.1–3.7, System 4.1–4.16); lecturer *edit* of a pending request dropped (cancel only); new FRs 2.3–2.4 (class details + start replacement), 2.14 (venue timetable), 2.15 (own request history), 2.16 (cancel own class with reason); MPU rule generalised to FR 4.7; NFR 2.4 split into staff 30 min / student 30 days; new NFR 3.5–3.6 (light/dark theme). |
| `v2026-10-01` | 2026-10-01 | Synced to **48 FRs / 26 NFRs** — the state `CodingMAIN.md` §7 quoted until 2026-11-02. |
| `v2026-11-02` | 2026-11-02 | **+FR 2.17** (lecturers may only propose a slot at least three working days ahead; the third working day itself stays selectable). Appended after 2.16 so FR 3.1's "FR 2.11 and FR 2.12" and every cited number stay valid. Now **49 FRs / 26 NFRs**; mirrored in `CodingMAIN.md` §7 (sync line, §7.1 row, §7.2 verbatim row). The FYP1 report Table 3.5 is **not** updated yet. |

How to bump the version: edit the requirement, update the `Version:` line in
the header, add a row here, then sync `CodingMAIN.md` §7 and (on request) the
report.
