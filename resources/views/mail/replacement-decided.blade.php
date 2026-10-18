Your replacement request [CRS#{{ $requestId }}] was {{ $decision }} — {{ $moduleCode }}

Decision by:   {{ $decidedBy }}
Make-up slot:  {{ $replacementTime }} @ {{ $replacementVenue }}
@if($reason)
Reason (rejected): {{ $reason }}
@endif
{{ $decision === 'approved' ? 'Your make-up class is now on the timetable at its new slot.' : 'You may submit a new make-up request for that occurrence.' }}
