@if($guideTab === 'start')
    <p>Use this page to find a new time and venue for a class that cannot run as scheduled. A Programme Leader must approve your proposal before the replacement is confirmed.</p>
    <h4>1. Choose the class you’re replacing</h4>
    <p>Choose <strong>Course</strong>, then <strong>Slot</strong>. Check the original week, date and time so you replace the right class. The same course can have more than one class to arrange.</p>
    <p>If you arrived through <strong>Arrange Replacement</strong>, the original class may already be selected. On a phone, tap <strong>Select a course and original slot</strong> to open the controls. Once you’ve chosen a class, that section shows the class and its original time.</p>
    <h4>2. Choose a venue and date</h4>
    <p>Choose a <strong>Venue</strong> and browse by week. On a phone, tap a day to see its times.</p>
    <p>The replacement date must be <strong>at least three working days after today</strong>. Count Monday to Friday, skipping public holidays. The third working day itself is allowed. <strong>Earliest bookable</strong> takes you to the first date the calendar allows; you still need to find a time that fits.</p>
    <h4>3. Pick a time that fits the whole class</h4>
    <p>Choose a green <strong>Available</strong> start time. The page tries to select one continuous block for the original class’s full duration. A green start time can still be too close to another booking or the end of the day.</p>
    <p>Your selection turns blue. <strong>Pending</strong> and <strong>Conflict</strong> times cannot be selected. On a phone, tap a blocked time to open its details and see the reason when one is shown.</p>
    <h4>4. Review and send</h4>
    <p>Check the venue, date and full time range in the selection summary. Choose <strong>Submit Request</strong> and review the confirmation before sending.</p>
    <p>A successful submission takes you to <strong>My Replacements</strong>. <strong>Pending</strong> means the request was sent, but the new class time is not confirmed. If the request is still pending when <strong>three working hours or less</strong> remain before the proposed start, it is automatically rejected.</p>
    <h4>If you get stuck</h4>
    <ul>
        <li><strong>No time fits:</strong> try a different start time, day, later week or eligible venue.</li>
        <li><strong>Want to pick a different time?</strong> Clear the current selection first. On a phone, tap the selection summary and choose <strong>Remove selection</strong>.</li>
        <li><strong>Submission Conflict:</strong> read the reason. If the time is no longer free, refresh and choose another. If the class already has an active request, check <strong>My Replacements</strong> instead.</li>
        <li><strong>Outcome Unknown:</strong> check <strong>My Replacements</strong> before trying again. The request may already have been received.</li>
    </ul>
@elseif($guideTab === 'reference')
    <h4>Original class and replacement slot</h4>
    <p><strong>Conflict Schedule</strong> identifies the class you’re replacing. <strong>Replacement Schedule</strong> is where you choose its new venue, week and time. Check both before submitting: the original class and the new time can fall in different weeks.</p>
    <p>You can replace a class affected by a public holiday, but you cannot schedule its replacement on a public holiday. If the original class already has a <strong>Pending</strong> or <strong>Approved</strong> request, check that request in <strong>My Replacements</strong> instead of starting another.</p>
    <h4>Venue eligibility and booking dates</h4>
    <p>A venue needs enough seats for <strong>all affected cohorts</strong> and must allow the class’s session type under the venue rules. An empty room is not necessarily suitable. On a phone, <strong>Choose venue</strong> lets you search by room code or name.</p>
    <p><strong>Booking notice example:</strong> if today is Thursday and there are no public holidays, Friday is working day one, Monday is day two and Tuesday is day three. Tuesday is the first date allowed by the notice rule. Saturday does not count towards notice, but a Saturday after the cutoff can still be selectable. Sundays and public holidays cannot be booked.</p>
    <h4>Timetable states</h4>
    <table>
        <thead>
            <tr>
                <th>State</th>
                <th>What it means</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><strong>Available</strong></td>
                <td>You can try this start time. The room, lecturer and all affected cohorts must be free for the whole block.</td>
            </tr>
            <tr>
                <td><strong>Your Current Selection</strong></td>
                <td>This is your proposed time. Selecting it does not reserve it.</td>
            </tr>
            <tr>
                <td><strong>Pending</strong></td>
                <td>A request is holding the time while it awaits approval. You cannot book it, whether the request is yours or someone else’s.</td>
            </tr>
            <tr>
                <td><strong>Conflict</strong></td>
                <td>The room, your timetable or an affected cohort’s timetable blocks the time. More than one can be in conflict.</td>
            </tr>
            <tr>
                <td><strong>Too soon</strong></td>
                <td>The date does not meet the booking notice. Choose a later date.</td>
            </tr>
        </tbody>
    </table>
    <p>Each slot is <strong>30 minutes</strong>. A two-hour class needs four consecutive slots on the same day. If the block runs past the last time shown, try an earlier start. On a phone, use <strong>Colours &amp; borders</strong> for the legend or tap a blocked time to inspect that specific slot.</p>
    <h4>Checking, changing or removing a selection</h4>
    <p>On desktop, <strong>Selection Summary</strong> shows the venue, date, time range and duration. Select a summary entry to return to its time, or use <strong>×</strong> to remove it.</p>
    <p>On a phone, tap the summary to open <strong>Saved selection</strong>. It shows the course, date, time, duration and venue. Choose <strong>Remove selection</strong> to clear it.</p>
    <p>Changing the course, original slot or venue can clear an existing selection. Read the confirmation before continuing. Leaving or refreshing the page can discard an unsent selection.</p>
    <h4>Approval and submission problems</h4>
    <ul>
        <li><strong>Pending:</strong> the new time is held, but the replacement is not confirmed.</li>
        <li><strong>Approved:</strong> the replacement is confirmed. Check its new date, time and venue in <strong>My Replacements</strong>.</li>
        <li><strong>Rejected:</strong> check the reason before choosing another time.</li>
    </ul>
    <p><strong>Approval deadline:</strong> the <strong>three-working-day</strong> rule decides which date you can choose. The <strong>three-working-hour</strong> rule decides when an unapproved request expires. Working hours are <strong>Monday to Friday, 8:30 AM ~ 5:30 PM</strong>, excluding public holidays. Check the request’s deadline in <strong>My Replacements</strong>.</p>
    <p>Availability is checked again when you submit. If the time was taken, follow the conflict message and choose another. If the phone tells you a selection is no longer bookable, review it, remove it if needed, and pick a later date. If the outcome is unknown, check <strong>My Replacements</strong> before trying again. Nothing is sent again automatically.</p>
@elseif($guideTab === 'shortcuts')
    <h4>Keyboard shortcuts</h4>
    <p>These shortcuts work in the desktop timetable view. They don’t run while you’re using a text field or dropdown. Close this guide before using the grid shortcuts.</p>
    <table>
        <thead>
            <tr>
                <th>Key</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <tr><td><kbd>Arrow keys</kbd></td><td>Move between grid cells.</td></tr>
            <tr><td><kbd>Enter</kbd> or <kbd>Space</kbd></td><td>Select or deselect the focused slot.</td></tr>
            <tr><td><kbd>Ctrl</kbd> or <kbd>⌘</kbd> + <kbd>Z</kbd></td><td>Undo the last slot selection change.</td></tr>
            <tr><td><kbd>[</kbd></td><td>Go to the previous week.</td></tr>
            <tr><td><kbd>]</kbd></td><td>Go to the next week.</td></tr>
            <tr><td><kbd>Ctrl</kbd> or <kbd>⌘</kbd> + <kbd>1</kbd>, <kbd>2</kbd> or <kbd>3</kbd></td><td>Choose the first, second or third venue in the current list.</td></tr>
            <tr><td><kbd>Esc</kbd></td><td>Close the guide or clear the grid focus.</td></tr>
            <tr><td><kbd>?</kbd></td><td>Open this Shortcuts tab.</td></tr>
        </tbody>
    </table>
@endif
