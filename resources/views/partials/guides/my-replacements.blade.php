@if($guideTab === 'start')
    <p>Use this page to find your classes that need a replacement, arrange a new time, and follow requests you’ve already sent.</p>
    <h4>1. Find the class</h4>
    <p>Open <strong>Filters</strong>. Search by course code, course name or cohort, or choose <strong>Need to Arrange</strong> under <strong>Status</strong>.</p>
    <p>Use <strong>Original Slot Week</strong> if you know when the class was meant to run. Use <strong>Requested Slot Week</strong> if you know when the replacement was proposed. If the list is empty, check the active filter chips before assuming there are no classes to arrange.</p>
    <h4>2. Open the details and take the next step</h4>
    <p>Select a row on desktop or tap a card on a phone to open <strong>Replacement Details</strong>.</p>
    <ul>
        <li><strong>Need to Arrange:</strong> choose <strong>Arrange Replacement</strong> to open the booking page for that class.</li>
        <li><strong>Pending:</strong> the proposed time is held but not confirmed. You can cancel your request while it is still pending.</li>
        <li><strong>Rejected:</strong> check the reason in <strong>Status</strong>, then arrange another time.</li>
        <li><strong>Approved:</strong> choose <strong>Go to My Timetable</strong> to see the confirmed replacement.</li>
    </ul>
    <h4>3. Check back after sending a request</h4>
    <p>After a successful submission, <strong>Replacement Arrangement</strong> brings you back here. Check the new request and its <strong>Requested</strong> details. If it is still pending when <strong>three working hours or less</strong> remain before the proposed start, the system automatically rejects it.</p>
@elseif($guideTab === 'reference')
    <h4>Original and requested slots</h4>
    <p><strong>Original</strong> is the class that could not run as scheduled. <strong>Requested</strong> is the proposed replacement date, time and venue. They can fall in different weeks. A specific <strong>Requested Slot Week</strong> excludes classes without a requested slot. To find those classes, leave that filter on <strong>All Weeks</strong> and use <strong>Original Slot Week</strong> or <strong>Need to Arrange</strong>.</p>
    <h4>What you can do at each status</h4>
    <table>
        <thead>
            <tr>
                <th>Status</th>
                <th>What to do</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><strong>Need to Arrange</strong></td>
                <td>Start a proposal. A class can return to this status after you cancel its pending request.</td>
            </tr>
            <tr>
                <td><strong>Pending</strong></td>
                <td>Check the proposed time and approval deadline, or cancel the request if you no longer want to proceed.</td>
            </tr>
            <tr>
                <td><strong>Rejected</strong></td>
                <td>Read the rejection reason before choosing another time.</td>
            </tr>
            <tr>
                <td><strong>Approved</strong></td>
                <td>Open the confirmed replacement in <strong>My Timetable</strong>.</td>
            </tr>
            <tr>
                <td><strong>Completed</strong></td>
                <td>The approved replacement date has passed. Turn off <strong>Hide COMPLETED</strong> to find these entries.</td>
            </tr>
        </tbody>
    </table>
    <p><strong>Cancel this request</strong> withdraws the proposal and releases the proposed time. It does not cancel the original class or make it run again. Confirm only if you mean to withdraw the request; the cancellation cannot be undone.</p>
    <h4>Filters, counts and approval timing</h4>
    <p>Remove an individual filter with its chip’s <strong>×</strong>. <strong>Clear all</strong> removes every filter; <strong>Reset</strong> restores the page defaults. The summary counts reflect the filtered list, so they can change when you change the filters. On a phone, use the <strong>Summary</strong> button when it is available.</p>
    <p>The approval deadline uses campus working hours: <strong>Monday to Friday, 8:30 AM ~ 5:30 PM</strong>, excluding public holidays. This is separate from the <strong>three working days’ notice</strong> required when choosing a replacement date.</p>
@endif
