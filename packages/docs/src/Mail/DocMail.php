<?php

declare(strict_types=1);

namespace AIArmada\Docs\Mail;

use AIArmada\Docs\Models\Doc;
use AIArmada\Docs\Models\DocEmail;
use AIArmada\Docs\Services\DocEmailService;
use AIArmada\Docs\Services\DocService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Mailable for sending documents via email.
 */
final class DocMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly DocEmail $docEmail,
        public readonly Doc $doc,
        public readonly bool $attachPdf = true,
    ) {
        $this->queue = config('docs.email.queue', 'default');
    }

    public function envelope(): Envelope
    {
        $fromAddress = config('docs.email.from_address') ?? config('mail.from.address') ?? 'noreply@example.com';
        $fromName = config('docs.email.from_name') ?? config('mail.from.name') ?? config('app.name');

        return new Envelope(
            from: new Address($fromAddress, $fromName),
            to: [
                new Address(
                    $this->docEmail->recipient_email,
                    $this->docEmail->recipient_name
                ),
            ],
            subject: $this->docEmail->subject,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'docs::emails.document',
            with: [
                'body' => $this->docEmail->body,
                'doc' => $this->doc,
                'docEmail' => $this->docEmail,
                'trackingPixelUrl' => $this->getTrackingPixelUrl(),
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        if (! $this->attachPdf) {
            return [];
        }

        try {
            $docService = app(DocService::class);
            $pdfPath = $docService->generatePdf($this->doc, save: true);

            $docType = ucfirst(str_replace('_', '-', $this->doc->doc_type));

            return [
                Attachment::fromPath($pdfPath)
                    ->as("{$docType}-{$this->doc->doc_number}.pdf")
                    ->withMime('application/pdf'),
            ];
        } catch (Throwable) {
            return [];
        }
    }

    private function getTrackingPixelUrl(): ?string
    {
        if (! config('docs.email.tracking.enabled', true)) {
            return null;
        }

        try {
            $emailService = app(DocEmailService::class);

            return $emailService->getTrackingPixelUrl($this->docEmail);
        } catch (Throwable) {
            return null;
        }
    }
}
