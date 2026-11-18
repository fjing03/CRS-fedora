@if($venue['code'] === '')
    <h4>No room selected</h4>
    <p>Choose a venue on the timetable to see its rules.</p>
@else
    <h4>{{ $venue['code'] }}@if($venue['short_name'] !== '' && $venue['short_name'] !== $venue['code']) · {{ $venue['short_name'] }}@endif</h4>
    <p>These rules describe what the room permits. Check availability, capacity and timing separately when arranging a replacement.</p>

    <h4>Default session types</h4>
    <p>{{ $venueGuideRules['defaultTypes'] === [] ? 'No default session types are listed for this room.' : implode(', ', $venueGuideRules['defaultTypes']) }}</p>

    @if($venueGuideRules['programmes'] !== null)
        <h4>Programme restriction</h4>
        @if($venueGuideRules['programmes'] === [])
            <p>No cohort programmes are listed as allowed. This restriction applies to all session types, including the defaults.</p>
        @else
            <p>Every cohort must be from one of these programmes: <strong>{{ implode(', ', $venueGuideRules['programmes']) }}</strong>. This applies to all session types, including the defaults.</p>
        @endif
    @endif

    <h4>Module exceptions</h4>
    @if(! $venueGuideRules['hasRoomExceptions'])
        <p>No module exceptions are listed for this room.</p>
    @elseif($venueGuideRules['exceptions'] === [])
        <p>No exceptions apply to the classes you teach this semester.</p>
    @else
        <p>These modules can also use this room for the session types listed. Any room-wide restrictions still apply.</p>
        <table class="guide-module-exceptions">
            <thead>
                <tr>
                    <th scope="col">Module</th>
                    <th scope="col">Additional session types</th>
                </tr>
            </thead>
            <tbody>
                @foreach($venueGuideRules['exceptions'] as $exception)
                    <tr>
                        <td>
                            <strong>{{ $exception['code'] }}</strong>
                            @isset($exception['name'])
                                <br>{{ $exception['name'] }}
                            @endisset
                        </td>
                        <td>{{ implode(', ', $exception['types']) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
@endif
