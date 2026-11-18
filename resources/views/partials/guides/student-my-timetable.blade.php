@if($guideTab === 'start')
    <p>Use this page to check when and where to attend your classes.</p>
    <h4>1. Find the day</h4>
    <p>Use the arrows or week list to move between weeks. Choose <strong>Today</strong> to return to the current week. On a phone, tap a day to see its classes.</p>
    <h4>2. Check the time and venue</h4>
    <p>Select a class block or card to open <strong>Class Details</strong>. Check the time, venue and lecturer, especially if the class has moved.</p>
    <ul>
        <li><strong>Normal Class:</strong> attend at the listed time and venue.</li>
        <li><strong>Replacement:</strong> the new time is approved. Attend at the replacement time and venue.</li>
        <li><strong>Pending:</strong> the new time is proposed only. Don’t treat it as confirmed.</li>
        <li><strong>Conflict / Public Holiday:</strong> the class will not run as originally scheduled. Check <strong>Class Replacements</strong> for an update.</li>
    </ul>
    <p>Your lecturer proposes the new time, and the Programme Leader decides whether to approve it. You cannot request or cancel a class here.</p>
@elseif($guideTab === 'reference')
    <h4>Read the timetable and week summary</h4>
    <p>The legend shows <strong>green</strong> for normal classes, <strong>blue</strong> for approved replacements, <strong>amber</strong> for pending proposals and <strong>red</strong> for conflicts or public holidays. A replacement is a change of slot; it does not necessarily mean a different lecturer.</p>
    <p>The summary covers the selected week. A small <strong>+N</strong> beside <strong>Total Classes</strong> or <strong>Class Hours</strong> shows the pending portion separately. On a phone, choose <strong>Week summary</strong> to see the counts and legend.</p>
    <h4>Follow a change or print your schedule</h4>
    <p>Use <strong>Class Replacements</strong> to compare the original and proposed slots or check whether a replacement is confirmed. Keep the timetable for checking when and where to attend.</p>
    <p>Where <strong>Print</strong> is available, choose one week or <strong>All Weeks (Whole Semester)</strong>. The semester printout shows normal classes only. For one week, turn off <strong>Show normal class only</strong> to include approved replacements and conflicts or public holidays. Pending and cancelled classes are never printed. Check the live timetable for any later changes.</p>
@elseif($guideTab === 'shortcuts')
    @include('partials.guides.timetable-week-shortcuts')
@endif
