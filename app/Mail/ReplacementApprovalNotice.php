<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * FR 1.9 — the affected cohort's students are notified when an approval
 * updates their timetable (SDD span-aware-approval-flow, design D8; each
 * cohort = its own queued job, no ordering contract with ReplacementDecided).
 */
final class ReplacementApprovalNotice extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly int $requestId,
        public readonly string $moduleCode,
        public readonly string $cohortLabel,
        public readonly string $replacementDate,
        public readonly string $replacementTime,
        public readonly string $replacementVenue,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: sprintf('[CRS#%d] Timetable update — %s replacement approved (%s)', $this->requestId, $this->moduleCode, $this->cohortLabel),
        );
    }

    public function content(): Content
    {
        return new Content(
            text: 'mail.replacement-approval-notice',
            with: [
                'requestId' => $this->requestId,
                'moduleCode' => $this->moduleCode,
                'cohortLabel' => $this->cohortLabel,
                'replacementDate' => $this->replacementDate,
                'replacementTime' => $this->replacementTime,
                'replacementVenue' => $this->replacementVenue,
            ],
        );
    }
}
