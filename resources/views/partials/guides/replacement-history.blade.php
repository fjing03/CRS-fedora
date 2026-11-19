@if($guideTab === 'start')
    <h4>1. Find the original class</h4>
    <p>Open <strong>Filters</strong> and choose <strong>Original Slot Week</strong> for the original class or <strong>Requested Slot Week</strong> for the proposal. If you don’t know whether there’s a proposal, leave the requested week on <strong>All Weeks</strong>.</p>
    <h4>2. Open the details and check the status</h4>
    <p>Select a row or tap a card to compare <strong>Original</strong> and <strong>Requested</strong>. If the replacement is <strong>Approved</strong>, attend at the new time and venue; a <strong>Pending</strong> replacement is not confirmed.</p>
@elseif($guideTab === 'reference')
    <h4>Why a class may still be awaiting arrangement</h4>
    <p><strong>Awaiting Arrangement</strong> does not always mean no request was ever made. A proposal that was withdrawn or rejected can also leave the class without an active request. If there is no active proposal, there is no new time to attend yet.</p>
    <p>This page is read-only. Your lecturer proposes the replacement; the Programme Leader decides.</p>
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
    <p><strong>Original</strong> is when the class was meant to run. <strong>Requested</strong> shows the proposed date, time and venue, if there is a proposal.</p>
    <p><strong>Completed</strong> means the approved replacement date has passed. Turn off <strong>Hide COMPLETED</strong> to find it.</p>
    <p><strong>Go to My Timetable</strong> is available for approved and completed replacements. For a pending proposal, check <strong>Requested</strong> in the details instead; there is no confirmed replacement to jump to yet.</p>
@endif
