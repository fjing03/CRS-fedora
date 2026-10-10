{{-- Livewire full-page view (SDD: wire-backend-into-refactored-ui, task 2.4).
     My Request History — own replacement requests, newest first (FR 2.15),
     with cancel-own-pending behind a confirm modal (FR 2.10). Status badges
     reuse the §10.0 legend tokens. Legacy mock template untouched for the
     D10 fallback. Documented divergences: bulk-cancel + pagination dropped
     (mock-era; rows are few per lecturer per semester). --}}

@section('title', 'My Request History')

<div class="lw-page">

    <style>
        .col-slot { width: 210px; white-space: normal; }
        .col-week { width: 90px; }
        .col-venue { width: 80px; }
        .col-status { width: 120px; }
        .col-action { width: 150px; }
        .grid-scroll .timetable { min-width: 1080px; }
        @media (max-width: 768px) {
            .grid-scroll .timetable { min-width: 0; }
        }
    </style>

    <!-- ─── Page Header ─── -->
    @include('partials.ui-page-header', ['title' => 'My Request History', 'description' => 'All replacement requests you have submitted this semester, newest first.'])

    @include('partials.ui-guide-block', [
        'guideTitle' => 'How to use this page',
        'guideItems' => [
            '<strong>Track status</strong> — Pending (amber), Approved (blue), Rejected (red), Cancelled (grey)',
            '<strong>Cancel pending</strong> — you can withdraw a request before the PL decides; its slot is freed',
            '<strong>Approved requests</strong> — appear on affected timetables as replacement blocks',
        ]
    ])

    <!-- ─── Toolbar: status filter ─── -->
    <div class="toolbar">
        <div class="toolbar-left">
            <div class="search-wrapper">
                <label for="statusFilter" style="font-size:12px; color:var(--color-on-surface-variant)">Status</label>
                <select id="statusFilter" wire:model.live="status" style="padding:8px 12px; border-radius:var(--radius-sm); border:1px solid var(--color-outline); background:var(--color-surface); color:var(--color-on-surface); font-family:inherit; font-size:13px">
                    <option value="all">All statuses</option>
                    <option value="pending">Pending</option>
                    <option value="approved">Approved</option>
                    <option value="rejected">Rejected</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </div>
        </div>
    </div>

    <!-- ─── Requests Table ─── -->
    <div class="grid-wrapper">
        <div class="grid-scroll">
            <table class="timetable data-table">
                <thead>
                    <tr>
                        <th style="width:40px">#</th>
                        <th>Course</th>
                        <th class="col-slot">Original Slot</th>
                        <th class="col-slot">Replacement Slot</th>
                        <th>Requested</th>
                        <th class="col-status">Status</th>
                        <th class="col-action">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($requests as $r)
                        <tr wire:key="r-{{ $r['id'] }}">
                            <td style="width:40px">{{ $loop->iteration }}</td>
                            <td>
                                <span class="cell-code">{{ $r['code'] }}</span>
                                <span class="cell-name">{{ $r['name'] }}</span>
                                @if ($r['remarks'] !== '')
                                    <div style="font-size:11px; color:var(--color-on-surface-variant)">“{{ $r['remarks'] }}”</div>
                                @endif
                            </td>
                            <td class="col-slot">
                                {{ $r['originalSlot'] }} · Week {{ $r['originalWeek'] }}
                            </td>
                            <td class="col-slot">
                                @if ($r['replacement'] !== null)
                                    {{ $r['replacement']['day'] }} {{ $r['replacement']['time'] }} · {{ $r['replacement']['venue'] }} · Week {{ $r['replacement']['week'] }}
                                @else
                                    —
                                @endif
                            </td>
                            <td>{{ $r['requestedAt'] }}</td>
                            <td class="col-status">
                                <span class="badge {{ match ($r['status']) {
                                    'pending' => 'badge-pending',
                                    'approved' => 'badge-replacement',
                                    'rejected' => 'badge-conflict',
                                    default => 'badge-past',
                                } }}">{{ ucfirst($r['status']) }}</span>
                            </td>
                            <td class="col-action">
                                @if ($r['status'] === 'pending')
                                    <button class="btn-action" type="button" wire:click="cancelConfirm({{ $r['id'] }})">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                        Cancel
                                    </button>
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr wire:key="empty-row"><td colspan="7" style="text-align:center; padding:24px; color:var(--color-on-surface-variant)">No requests yet — start one from Replacement Arrangement.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- ─── Cancel Confirm Modal (§10.0 rule 9) ─── -->
    @if ($confirmCancelId !== null)
        <div class="modal-overlay" style="display:flex" wire:key="cancel-modal-{{ $confirmCancelId }}">
            <div class="modal" style="max-width:420px">
                <div class="modal-header">
                    <h3 class="modal-title">Confirm Cancellation</h3>
                    <button class="modal-close" type="button" wire:click="cancelDismiss" data-tip="Close">&times;</button>
                </div>
                <div class="modal-body">
                    <p style="font-size:14px; color:var(--color-on-surface); line-height:1.5">
                        Are you sure you want to cancel this replacement request? This action cannot be
                        <span class="warn-keyword">undone</span> and the held slot becomes free for others.
                    </p>
                </div>
                <div class="modal-footer">
                    <button class="btn-close-modal" type="button" wire:click="cancelDismiss">No, Keep It</button>
                    <button class="btn-action" type="button" wire:click="cancel">Yes, Cancel Request</button>
                </div>
            </div>
        </div>
    @endif

    <!-- ─── Toast ─── -->
    <div class="copy-toast" id="mhToast"></div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            let toastTimer = null;
            Livewire.on('toast', function (payload) {
                const data = Array.isArray(payload) ? payload[0] : payload;
                const message = data && data.message ? data.message : String(data ?? '');
                const el = document.getElementById('mhToast');
                if (!el) return;
                el.textContent = message;
                el.style.background = /no longer/i.test(message) ? 'var(--color-error)' : 'var(--color-primary)';
                el.style.color = /no longer/i.test(message) ? 'var(--color-on-error)' : 'var(--color-on-primary)';
                el.classList.add('show');
                clearTimeout(toastTimer);
                toastTimer = setTimeout(function () { el.classList.remove('show'); }, 3500);
            });
        });
    </script>
</div>
