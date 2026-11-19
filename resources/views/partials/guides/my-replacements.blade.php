@if($guideTab === 'start')
    <h4>1. Find the class</h4>
    <p>Open <strong>Filters</strong> to search by course code, course name or cohort, or choose <strong>Need to Arrange</strong> under <strong>Status</strong>.</p>
    <h4>2. Open the details</h4>
    <p>Select a row or tap a card to open <strong>Replacement Details</strong>. Choose <strong>Arrange Replacement</strong> if its status is <strong>Need to Arrange</strong>.</p>
    <h4>3. Check your request</h4>
    <p>When <strong>Replacement Arrangement</strong> brings you back after submitting, check the <strong>Requested</strong> details. A <strong>Pending</strong> slot is held but not confirmed, and the request is automatically rejected if it’s still pending with three working hours or less before the proposed start.</p>
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
    <p>For a rejected request, read the reason in <strong>Status</strong> before arranging another time. For an approved request, choose <strong>Go to My Timetable</strong> to see the confirmed slot.</p>
    <h4>Filters, counts and approval timing</h4>
    <p>Use <strong>Original Slot Week</strong> if you know when the class was meant to run, or <strong>Requested Slot Week</strong> if you know the week of the proposed replacement.</p>
    <p>If the list is empty, check the active filter chips before assuming there are no classes to arrange.</p>
    <p>Remove an individual filter with its chip’s <strong>×</strong>. <strong>Clear all</strong> removes every filter; <strong>Reset</strong> restores the page defaults. The summary counts reflect the filtered list, so they can change when you change the filters. On a phone, use the <strong>Summary</strong> button when it is available.</p>
    <p>The approval deadline uses campus working hours: <strong>Monday to Friday, 8:30 AM ~ 5:30 PM</strong>, excluding public holidays. This is separate from the <strong>three working days’ notice</strong> required when choosing a replacement date.</p>
@elseif($guideTab === 'shortcuts')
    <p>Use these keys outside the guide. Search, page and week shortcuts work when you’re not in an input.</p>
    <table>
        <thead>
            <tr>
                <th scope="col">Key</th>
                <th scope="col">Action</th>
            </tr>
        </thead>
        <tbody>
            <tr><td><kbd>/</kbd></td><td>Focus <strong>Search</strong> when <strong>Filters</strong> is open.</td></tr>
            <tr><td><kbd>←</kbd> or <kbd>→</kbd></td><td>Go to the previous or next page of results.</td></tr>
            <tr><td><kbd>[</kbd> or <kbd>]</kbd></td><td>Go to the previous or next week in the <strong>Original slot week</strong> filter.</td></tr>
            <tr><td><kbd>Esc</kbd></td><td>Close the open dialog. If no dialog is open, clear the filters.</td></tr>
            <tr><td><kbd>?</kbd></td><td>Open this Shortcuts tab.</td></tr>
        </tbody>
    </table>
@endif
