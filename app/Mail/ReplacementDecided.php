<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * FR 2.13 — the proposing lecturer is notified when their request is
 * approved or rejected (SDD span-aware-approval-flow, design D8). All the
 * decision facts are CAPTURED AT QUEUE TIME (spec notifications R1#2) —
 * the constructor takes the pre-release snapshot; the mail body never
 * re-reads a (deleted) junction for a rejected row.
 */
final class ReplacementDecided extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly int $requestId,
        public readonly string $moduleCode,
        public readonly string $decision,
        public readonly string $replacementTime,
        public readonly string $replacementVenue,
        public readonly ?string $reason,
        public readonly string $decidedBy,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: sprintf('[CRS#%d] Your replacement request was %s — %s', $this->requestId, $this->decision, $this->moduleCode),
        );
    }

    public function content(): Content
    {
        return new Content(
            text: 'mail.replacement-decided',
            with: [
                'requestId' => $this->requestId,
                'moduleCode' => $this->moduleCode,
                'decision' => $this->decision,
                'replacementTime' => $this->replacementTime,
                'replacementVenue' => $this->replacementVenue,
                'reason' => $this->reason,
                'decidedBy' => $this->decidedBy,
            ],
        );
    }
}
