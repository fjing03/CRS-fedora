@if($guideTab === 'start')
    <h4>1. Find the request</h4>
    <p>As Programme Leader, start with <strong>Pending</strong> requests and use <strong>Filters</strong> to narrow the queue.</p>
    <h4>2. Check the proposal</h4>
    <p>Open <strong>Request Details</strong> to compare <strong>Original</strong> and <strong>Requested</strong>, and check <strong>Slot Validity</strong>. Use the <strong>Approval deadline</strong>, not the request’s age: a request still pending with three working hours or less before the proposed start is automatically rejected.</p>
    <h4>3. Approve or reject</h4>
    <p>Choose <strong>Approve</strong> (note optional) or <strong>Reject</strong> (reason required). Review the confirmation carefully: neither decision can be undone.</p>
@elseif($guideTab === 'reference')
    <h4>Queue, filters and request details</h4>
    <p><strong>Reset</strong> returns to the default queue: <strong>Pending</strong>, oldest submission first, with completed requests hidden. <strong>Clear all</strong> removes the filters instead. If you’re looking for a past decision, choose the relevant <strong>Status</strong> and check <strong>Hide COMPLETED</strong>.</p>
    <p>Search by course code, course name or proposer. Use <strong>Requested Slot Week</strong> for when the lecturer wants the class to run, or <strong>Original Slot Week</strong> for when it was meant to run.</p>
    <p>The details tabs answer different questions:</p>
    <table>
        <thead>
            <tr>
                <th>Tab</th>
                <th>What to check</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><strong>Requested</strong></td>
                <td>What is being proposed: new date, time, venue and slot validity.</td>
            </tr>
            <tr>
                <td><strong>Original</strong></td>
                <td>Which scheduled class the request replaces.</td>
            </tr>
            <tr>
                <td><strong>Class Info</strong></td>
                <td>Who proposed it and which cohorts and students are affected.</td>
            </tr>
            <tr>
                <td><strong>Status</strong></td>
                <td>Where the request stands and any recorded decision details.</td>
            </tr>
        </tbody>
    </table>
    <p>Check the proposed date, full time range, venue and affected cohorts. If <strong>Slot Validity</strong> flags a conflict, read the reason before deciding.</p>
    <p><strong>Request age</strong> tells you how long ago the request was submitted. <strong>Approval deadline</strong> tells you how long you have to act. Working hours are <strong>Monday to Friday, 8:30 AM ~ 5:30 PM</strong>, excluding public holidays.</p>
    <h4>Desktop and phone actions</h4>
    <p>On desktop, use the row’s <strong>Approve</strong> or <strong>Reject</strong> action, or open <strong>Request Details</strong> first. Sort using headers that show a sort control; not every column is sortable.</p>
    <p>On a phone, tap the request card to open <strong>Request Details</strong>, then use <strong>Approve</strong> or <strong>Reject</strong> there. Those actions are available only while the request is <strong>Pending</strong>.</p>
    <p>For a desktop batch, tick the pending requests you want and review the selection before choosing <strong>Approve Selected</strong> or <strong>Reject Selected</strong>. A batch rejection uses <strong>one reason for every selected request</strong>. Use individual rejections when the reasons differ. Check the result for each request; some decisions can succeed while others are refused.</p>
    <p>Approve if the replacement can go ahead. If you reject it, give a reason the lecturer can act on: rejection releases the proposed time so the lecturer can arrange another, while approval confirms the replacement.</p>
    <h4>If a decision does not go through</h4>
    <p>The slot can change after the details were opened, or the request may already have been decided. Read the result and check the latest <strong>Status</strong> and <strong>Slot Validity</strong> before trying again. A check mark in the list is not a guarantee that approval will still succeed.</p>
@elseif($guideTab === 'shortcuts')
    <p>Use these keys in the request table. Opening a decision dialog does not submit a decision.</p>
    <table>
        <thead>
            <tr>
                <th scope="col">Key</th>
                <th scope="col">Action</th>
            </tr>
        </thead>
        <tbody>
            <tr><td><kbd>↑</kbd> or <kbd>↓</kbd></td><td>Highlight the previous or next request.</td></tr>
            <tr><td><kbd>Enter</kbd></td><td>Open the highlighted request’s details.</td></tr>
            <tr><td><kbd>A</kbd></td><td>Open <strong>Approve Request</strong> for the highlighted Pending request.</td></tr>
            <tr><td><kbd>R</kbd></td><td>Open <strong>Reject Request</strong> for the highlighted Pending request.</td></tr>
            <tr><td><kbd>Esc</kbd></td><td>Close the open request or decision dialog, or clear the row highlight.</td></tr>
        </tbody>
    </table>
@endif
