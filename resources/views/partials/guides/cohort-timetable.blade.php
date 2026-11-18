@if($guideTab === 'start')
    <p>Use this page to see a cohort’s weekly schedule, including classes taught by other lecturers.</p>
    <h4>1. Check which cohort you’re viewing</h4>
    <p>If you’re a staff member, choose a <strong>Faculty</strong> and <strong>Cohort</strong>, or check the choices already selected.@if($isStudent) If you’re a student, the page is set to your own cohort.@endif</p>
    <h4>2. Find the week and day</h4>
    <p>Use the arrows or week list to move between weeks. Choose <strong>Today</strong> to return to the current week. On a phone, tap a day to see its classes.</p>
    <h4>3. Open the class you want to check</h4>
    <p>Select a class block or card to open <strong>Class Details</strong> and check the lecturer, venue and time. A <strong>blue Replacement</strong> block is an approved new class time. An <strong>amber Pending</strong> block is not confirmed.</p>
@elseif($guideTab === 'reference')
    <h4>Read the cohort schedule</h4>
    <p><strong>Green</strong> marks a normal class. <strong>Blue</strong> marks an approved replacement. <strong>Amber</strong> marks a proposal awaiting approval. <strong>Red</strong> marks a conflict or public holiday: the class will not run as originally scheduled.</p>
    <p>For staff, a <strong>thick border</strong> marks your classes and a <strong>thin border</strong> marks other lecturers’ classes. The border tells you who teaches the class, not its status. A <strong>✓</strong> in the faculty or cohort list helps you find groups you teach.</p>
    <h4>Staff actions and weekly counts</h4>
    <p>For your own classes, <strong>Class Details</strong> can offer <strong>Arrange Replacement</strong> when that occurrence needs a replacement, or <strong>Cancel Class?</strong> when it is eligible for cancellation. These actions do not apply to other lecturers’ classes.@if($isStudent) Students can view the schedule but cannot arrange or cancel classes.@endif</p>
    <p>The summary covers the selected cohort and week. <strong>My Classes</strong> and <strong>My Teaching Hours</strong> show your part of that cohort’s schedule, not your teaching across all cohorts.</p>
    <h4>Print the selected cohort</h4>
    <p>Where <strong>Print</strong> is available, check that the right cohort is selected first. Choose one week or <strong>All Weeks (Whole Semester)</strong>. The semester printout shows normal classes only. For one week, turn off <strong>Show normal class only</strong> to include approved replacements and conflicts or public holidays. Pending and cancelled classes are never printed.</p>
@elseif($guideTab === 'shortcuts')
    @include('partials.guides.timetable-week-shortcuts')
@endif
