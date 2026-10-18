<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * FR 4.15 — the Programme Leader(s) are notified when a replacement request
 * is submitted (SDD span-aware-approval-flow, design D8; grill Q7=A: should
 * queue, log mailer in dev — nothing really sends).
 */
final class ReplacementSubmitted extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly int $requestId,
        public readonly string $moduleCode,
        public readonly string $moduleLabel,
        public readonly string $proposerName,
        public readonly string $replacementTime,
        public readonly string $replacementVenue,
        public readonly int $spanSlots,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: sprintf('[CRS#%d] New replacement request — %s %s', $this->requestId, $this->moduleCode, $this->moduleLabel),
        );
    }

    public function content(): Content
    {
        return new Content(
            text: 'mail.replacement-submitted',
            with: [
                'requestId' => $this->requestId,
                'moduleCode' => $this->moduleCode,
                'moduleLabel' => $this->moduleLabel,
                'proposerName' => $this->proposerName,
                'replacementTime' => $this->replacementTime,
                'replacementVenue' => $this->replacementVenue,
                'spanSlots' => $this->spanSlots,
                'openUrl' => url('/request-approval-ui'),
            ],
        );
    }
}
