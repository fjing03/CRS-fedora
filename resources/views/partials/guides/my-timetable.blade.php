@if($guideTab === 'start')
    <h4>1. Find the class</h4>
    <p>Use the arrows or week list to change weeks, or choose <strong>Today</strong> to return to this week. On a phone, tap a day to see its classes.</p>
    <h4>2. Open Class Details</h4>
    <p>Select a class block or card to check its time, venue and cohorts. A <strong>Pending</strong> replacement holds the proposed time, but it isn’t confirmed.</p>
    <h4>3. Arrange a replacement if needed</h4>
    <p>Open the original class and choose <strong>Arrange Replacement</strong> if it appears in <strong>Class Details</strong>. Review the original class, choose a new time and submit the request in <strong>Replacement Arrangement</strong>.</p>
@elseif($guideTab === 'reference')
    <h4>Read the class blocks</h4>
    <table class="guide-status-table">
        <thead>
            <tr>
                <th scope="col">Block</th>
                <th scope="col">Meaning</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><strong>Green · Normal Class</strong></td>
                <td>Runs as scheduled.</td>
            </tr>
            <tr>
                <td><strong>Blue · Replacement</strong></td>
                <td>The Programme Leader approved the new class time.</td>
            </tr>
            <tr>
                <td><strong>Amber · Pending</strong></td>
                <td>A proposed replacement is waiting for a decision.</td>
            </tr>
            <tr>
                <td><strong>Red · Conflict / Public Holiday</strong></td>
                <td>The class will not run as originally scheduled. Open its details to see why.</td>
            </tr>
        </tbody>
    </table>
    <p>A cancelled class can still appear at its original time as a red block. After a replacement is approved, the original cancelled block may no longer be shown. Use <strong>My Replacements</strong> to compare the original and replacement details.</p>
    <h4>Cancel a class or withdraw a request</h4>
    <p><strong>Cancel Class?</strong> appears in <strong>Class Details</strong> for eligible classes you teach. It cancels that class occurrence; it does not book a replacement. To withdraw a <strong>pending proposal</strong> instead, use <strong>My Replacements</strong>.</p>
    <p>Opening <strong>Replacement Arrangement</strong> does not send a request. After submitting, use <strong>My Replacements</strong> to follow the decision.</p>
    <h4>Check the week summary or print</h4>
    <p>The summary covers the selected week. A small <strong>+N</strong> beside a total shows the pending portion separately. On a phone, open <strong>Week summary</strong> to see the counts and legend.</p>
    <p>Where <strong>Print</strong> is available, choose one week or <strong>All Weeks (Whole Semester)</strong>. The semester printout shows normal classes only. For one week, turn off <strong>Show normal class only</strong> to include approved replacements and conflicts or public holidays. Pending and cancelled classes are never printed.</p>
@elseif($guideTab === 'shortcuts')
    @include('partials.guides.timetable-week-shortcuts')
@endif
