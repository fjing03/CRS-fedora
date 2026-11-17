{{-- ui-cohort-selects — the Cohort Timetable page's two bare semester-bar selects
     (cohort-venue-responsive-port, phase 1). Extracted VERBATIM from the page's
     inline semester-bar only so the responsive chrome can wrap the whole toolbar
     (selects + week nav) as one card — the markup contract is byte-identical:
     bare chips with no visible field labels, the full name rides the option
     `title`, taught rows carry `data-taught` (the SelectDd panel styles them bold
     blue + ✓), and the inline onchange wiring is untouched. --}}
<select id="facultySelect" onchange="onFacultyChange(this.value, { origin: 'user' })" @if($isStudent) disabled @endif>
    <option value="">Select Faculty</option>
</select>
<select id="cohortSelect" onchange="onCohortChange(this.value, { origin: 'user' })" disabled>
    <option value="">Select Cohort</option>
</select>
