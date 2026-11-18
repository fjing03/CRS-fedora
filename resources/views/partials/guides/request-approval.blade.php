@if($guideTab === 'start')
    <p>Use this page as a <strong>Programme Leader</strong> to review proposed replacements and decide whether they can go ahead.</p>
    <h4>1. Find the request</h4>
    <p>Start with <strong>Pending</strong> requests. Use <strong>Filters</strong> to search by course code, course name or proposer. Use <strong>Requested Slot Week</strong> for the date the lecturer wants to move the class <strong>to</strong>, or <strong>Original Slot Week</strong> for the date it was meant to run.</p>
    <h4>2. Check the proposal before deciding</h4>
    <p>Open the row or tap its phone card to see <strong>Request Details</strong>. Compare <strong>Original</strong> and <strong>Requested</strong>. Check the proposed date, full time range, venue, affected cohorts and <strong>Slot Validity</strong>. If the slot is marked as conflicting, read the reason before proceeding.</p>
    <p>A pending request is automatically rejected if it is still awaiting approval when <strong>three working hours or less</strong> remain before the proposed start. Check its deadline rather than using the request’s age to judge how much time is left.</p>
    <h4>3. Approve or reject</h4>
    <p>Choose <strong>Approve</strong> if the replacement can go ahead. An approval note is optional. Choose <strong>Reject</strong> if it cannot go ahead and enter a reason the lecturer can act on.</p>
    <p>Review the confirmation before committing. <strong>Approve and Reject cannot be undone.</strong> Approval confirms the replacement; rejection releases the proposed time so the lecturer can arrange another.</p>
@elseif($guideTab === 'reference')
    <h4>Queue, filters and request details</h4>
    <p><strong>Reset</strong> returns to the default queue: <strong>Pending</strong>, oldest submission first, with completed requests hidden. <strong>Clear all</strong> removes the filters instead. If you’re looking for a past decision, choose the relevant <strong>Status</strong> and check <strong>Hide COMPLETED</strong>.</p>
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
    <p><strong>Request age</strong> tells you how long ago the request was submitted. <strong>Approval deadline</strong> tells you how long you have to act. Working hours are <strong>Monday to Friday, 8:30 AM ~ 5:30 PM</strong>, excluding public holidays.</p>
    <h4>Desktop and phone actions</h4>
    <p>On desktop, use the row’s <strong>Approve</strong> or <strong>Reject</strong> action, or open <strong>Request Details</strong> first. Sort using headers that show a sort control; not every column is sortable.</p>
    <p>On a phone, tap the request card to open <strong>Request Details</strong>, then use <strong>Approve</strong> or <strong>Reject</strong> there. Those actions are available only while the request is <strong>Pending</strong>.</p>
    <p>For a desktop batch, tick the pending requests you want and review the selection before choosing <strong>Approve Selected</strong> or <strong>Reject Selected</strong>. A batch rejection uses <strong>one reason for every selected request</strong>. Use individual rejections when the reasons differ. Check the result for each request; some decisions can succeed while others are refused.</p>
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
