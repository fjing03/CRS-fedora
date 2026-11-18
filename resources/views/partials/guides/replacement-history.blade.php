@if($guideTab === 'start')
    <p>Use this page to check what happened to a class that could not run as scheduled and find out whether a new time is confirmed.</p>
    <h4>1. Find the original class</h4>
    <p>Open <strong>Filters</strong>. Choose <strong>Original Slot Week</strong> if you know when the class was meant to run. Choose <strong>Requested Slot Week</strong> if you know the week of the proposed replacement. If you don’t know whether a new time has been proposed, leave the requested week on <strong>All Weeks</strong>.</p>
    <h4>2. Open the details and check the status</h4>
    <p>Select the row or tap the card. <strong>Original</strong> is when the class was meant to run. <strong>Requested</strong> is the new date, time and venue, when one has been proposed.</p>
    <ul>
        <li><strong>Awaiting Arrangement:</strong> there is no active replacement request.</li>
        <li><strong>Pending:</strong> your lecturer has proposed a slot, but the Programme Leader has not approved it. Don’t treat it as confirmed.</li>
        <li><strong>Approved:</strong> attend at the new time and venue. Choose <strong>Go to My Timetable</strong> to see the replacement there.</li>
        <li><strong>Completed:</strong> the approved replacement date has passed. Turn off <strong>Hide COMPLETED</strong> to find it.</li>
    </ul>
    <p>This page is read-only. Your lecturer proposes the replacement; the Programme Leader decides.</p>
@elseif($guideTab === 'reference')
    <h4>Why a class may still be awaiting arrangement</h4>
    <p><strong>Awaiting Arrangement</strong> does not always mean no request was ever made. A proposal that was withdrawn or rejected can also leave the class without an active request. If there is no active proposal, there is no new time to attend yet.</p>
    <h4>Use the two week filters together</h4>
    <table>
        <thead>
            <tr>
                <th scope="col">Filter</th>
                <th scope="col">What it matches</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><strong>Original Slot Week</strong></td>
                <td>The week the class was originally meant to run.</td>
            </tr>
            <tr>
                <td><strong>Requested Slot Week</strong></td>
                <td>The week of the proposed replacement. Classes without a proposed time do not match a specific requested week.</td>
            </tr>
        </tbody>
    </table>
    <p>If both are set, the class must match both. If you cannot find an entry, remove individual filter chips or choose <strong>Clear all</strong>. Check <strong>Hide COMPLETED</strong> when looking for a past replacement.</p>
    <h4>Read the summary and open the timetable</h4>
    <p>The summary counts reflect the list after filtering. <strong>Hours Awaiting Arrangement</strong> is the length of classes still without an active replacement request, not the time left to arrange them.</p>
    <p><strong>Go to My Timetable</strong> is available for approved and completed replacements. For a pending proposal, check <strong>Requested</strong> in the details instead; there is no confirmed replacement to jump to yet.</p>
@endif
