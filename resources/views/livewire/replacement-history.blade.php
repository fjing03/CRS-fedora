{{-- Livewire full-page view (SDD: wire-backend-into-refactored-ui, task 2.4).
     Replacement History — student view-only (FR 1.4): approved replacements
     for MY cohort, upcoming weeks only. Legacy mock template untouched for
     the D10 fallback. --}}

@section('title', 'Replacement History')

<div class="lw-page">

    <style>
        .col-slot { width: 220px; white-space: normal; }
        .col-week { width: 90px; }
        .col-venue { width: 80px; }
        .grid-scroll .timetable { min-width: 900px; }
        @media (max-width: 768px) {
            .grid-scroll .timetable { min-width: 0; }
        }
    </style>

    <!-- ─── Page Header ─── -->
    @include('partials.ui-page-header', ['title' => 'Replacement History', 'description' => 'Replacement classes confirmed or pending for your cohort — upcoming weeks only.', 'chips' => $cohortLabel !== null ? [['label' => $cohortLabel]] : []])

    @include('partials.ui-guide-block', [
        'guideTitle' => 'How to use this page',
        'guideItems' => [
            '<strong>Confirmed replacements</strong> — approved by the Programme Leader; attend the new slot instead',
            '<strong>View only</strong> — contact your lecturer for changes',
        ]
    ])

    <!-- ─── Replacements Table ─── -->
    <div class="grid-wrapper">
        <div class="grid-scroll">
            <table class="timetable data-table">
                <thead>
                    <tr>
                        <th style="width:40px">#</th>
                        <th>Course</th>
                        <th class="col-slot">Original Class</th>
                        <th class="col-slot">Replacement Class</th>
                        <th class="col-week">Week</th>
                        <th class="col-venue">Venue</th>
                        <th>Approved By</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $r)
                        <tr wire:key="rh-{{ $r['id'] }}">
                            <td style="width:40px">{{ $loop->iteration }}</td>
                            <td>
                                <span class="cell-code">{{ $r['code'] }}</span>
                                <span class="cell-name">{{ $r['name'] }}</span>
                            </td>
                            <td class="col-slot">{{ $r['originalSlot'] }} · Week {{ $r['originalWeek'] }}</td>
                            <td class="col-slot">
                                <span class="badge badge-replacement" style="margin-right:6px">Replacement</span>
                                {{ $r['replacement']['day'] }} {{ $r['replacement']['time'] }}
                            </td>
                            <td class="col-week">Week {{ $r['replacement']['week'] }}</td>
                            <td class="col-venue">{{ $r['replacement']['venue'] }}</td>
                            <td>{{ $r['decidedBy'] }}</td>
                        </tr>
                    @empty
                        <tr wire:key="rh-empty"><td colspan="7" style="text-align:center; padding:24px; color:var(--color-on-surface-variant)">No upcoming replacement classes for {{ $cohortLabel ?? 'your cohort' }}.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
