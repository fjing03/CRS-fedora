@if($guideTab === 'start')
    <p>Use this page to see what is scheduled in a room and find a possible time for a replacement there.</p>
    <h4>1. Choose the room and date</h4>
    <p>Choose a <strong>Venue</strong> and check its capacity. Use the week controls to browse, or choose <strong>Today</strong> to return to the current date. On a phone, tap a day to inspect it.</p>
    <p>If you’re looking for the next opening in this room, choose <strong>Earliest bookable</strong>. It looks for room availability on a date that meets the booking notice. It does not guarantee that your particular class will fit.</p>
    <h4>2. Inspect a class or start from a free time</h4>
    <ul>
        <li>Select a <strong>class block</strong> to see its details, such as the course, lecturer and cohorts.</li>
        <li>Select an <strong>available time</strong>, choose <strong>Arrange</strong>, then pick the original class in <strong>Select a Session</strong>. Choose <strong>Arrange</strong> in that dialog to continue to <strong>Replacement Arrangement</strong>.</li>
    </ul>
    <h4>3. Review the proposed time before submitting</h4>
    <p>Check that the selected room and the full time range suit the class. A free room alone does not mean the lecturer and every affected cohort are free.</p>
    <p>Opening <strong>Replacement Arrangement</strong> from here does <strong>not</strong> send a request. Review the selection and submit it there.</p>
@elseif($guideTab === 'reference')
    <h4>Reading the venue timetable</h4>
    <p>The screen shows <strong>one selected week at a time</strong>. A green <strong>class block</strong> is a scheduled class; a green <strong>Available</strong> cell is a free room time you can inspect for a booking. Look at the label and shape, not the colour alone.</p>
    <p>Blue marks a replacement, amber marks a pending request, and red marks a conflict or public holiday. On class blocks, a thick border marks <strong>your</strong> class; a thin border marks <strong>someone else’s</strong>. The border does not tell you whether a room is free.</p>
    <h4>Why a free time might not work</h4>
    <ul>
        <li><strong>The whole class must fit.</strong> A time can be free at the start but too close to the next booking or the end of the day. If you see <strong>Not Enough Slot</strong> or <strong>Not Enough Slots</strong>, try a different starting time.</li>
        <li><strong>The room must suit the class.</strong> It needs enough seats and must allow the class’s session type under the venue rules. If you cannot find the class in <strong>Select a Session</strong>, check that the room is suitable and that the class still needs arranging. You can also start from the class in <strong>My Replacements</strong> and choose an eligible venue there.</li>
        <li><strong>The calendars must agree.</strong> <strong>Earliest bookable</strong> checks the selected room, not whether a particular class can use the entire time. The replacement must also meet the <strong>three-working-day</strong> notice rule and cannot land on a Sunday or public holiday.</li>
    </ul>
    <h4>Printing</h4>
    <p>Where the <strong>Print</strong> control is shown, choose one week or <strong>All Weeks (Whole Semester)</strong>. All Weeks prints normal classes only. For one week, turn off <strong>Show normal class only</strong> to include replacements and conflicts or public holidays. <strong>Pending and cancelled classes are never printed.</strong> The print selection does not change the week you’re viewing.</p>
@elseif($guideTab === 'shortcuts')
    <p>Use these keys on the desktop timetable.</p>
    <table>
        <thead>
            <tr>
                <th scope="col">Key</th>
                <th scope="col">Action</th>
            </tr>
        </thead>
        <tbody>
            <tr><td><kbd>[</kbd> or <kbd>]</kbd></td><td>Go to the previous or next week.</td></tr>
            <tr><td><kbd>B</kbd></td><td>With an <strong>Available</strong> grid cell focused, show its slot actions. This does not arrange or submit a request.</td></tr>
        </tbody>
    </table>
@endif
