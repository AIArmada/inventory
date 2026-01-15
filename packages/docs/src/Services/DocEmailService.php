<?php

declare(strict_types=1);

namespace AIArmada\Docs\Services;

use AIArmada\Docs\Enums\EmailStatus;
use AIArmada\Docs\Mail\DocMail;
use AIArmada\Docs\Models\Doc;
use AIArmada\Docs\Models\DocEmail;
use AIArmada\Docs\Models\DocEmailTemplate;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Document email service for sending and tracking emails.
 */
final class DocEmailService
{
    /**
     * Send a document to a recipient.
     *
     * @param  array<string, mixed>  $variables  Additional template variables
     */
    public function send(
        Doc $doc,
        string $recipientEmail,
        ?string $recipientName = null,
        ?DocEmailTemplate $template = null,
        array $variables = [],
    ): DocEmail {
        // Find template if not provided
        $template ??= $this->findTemplate($doc, 'send');

        // Build variables for template
        $templateVars = $this->buildVariables($doc, $variables);

        // Render subject and body
        $subject = $template?->renderSubject($templateVars)
            ?? $this->getDefaultSubject($doc);
        $body = $template?->renderBody($templateVars)
            ?? $this->getDefaultBody($doc);

        $ownerAttributes = [];
        if (config('docs.owner.enabled', false)) {
            $ownerAttributes = [
                'owner_type' => $doc->owner_type,
                'owner_id' => $doc->owner_id,
            ];
        }

        // Create email record
        $email = $doc->emails()->create(array_merge([
            'doc_email_template_id' => $template?->id,
            'recipient_email' => $recipientEmail,
            'recipient_name' => $recipientName,
            'subject' => $subject,
            'body' => $body,
            'status' => EmailStatus::Queued,
        ], $ownerAttributes));

        // Queue the email
        $this->queueEmail($email, $doc);

        return $email;
    }

    /**
     * Send a reminder for an overdue document.
     */
    public function sendReminder(Doc $doc, string $recipientEmail): DocEmail
    {
        $template = $this->findTemplate($doc, 'reminder');

        return $this->send($doc, $recipientEmail, null, $template, [
            'days_overdue' => $doc->due_date?->diffInDays(CarbonImmutable::now()),
        ]);
    }

    /**
     * Find a template for a document type and trigger.
     */
    public function findTemplate(Doc $doc, string $trigger): ?DocEmailTemplate
    {
        return $this->getTemplateQueryForDoc($doc)
            ->where('doc_type', $doc->doc_type)
            ->where('trigger', $trigger)
            ->where('is_active', true)
            ->first();
    }

    /**
     * @return Builder<DocEmailTemplate>
     */
    private function getTemplateQueryForDoc(Doc $doc): Builder
    {
        $query = DocEmailTemplate::query();

        if (! config('docs.owner.enabled', false)) {
            return $query;
        }

        $includeGlobal = (bool) config('docs.owner.include_global', false);

        if ($doc->owner_type !== null && $doc->owner_id !== null) {
            return $query->where(function (Builder $builder) use ($doc, $includeGlobal): void {
                $builder->where('owner_type', $doc->owner_type)
                    ->where('owner_id', $doc->owner_id);

                if ($includeGlobal) {
                    $builder->orWhere(function (Builder $inner): void {
                        $inner->whereNull('owner_type')->whereNull('owner_id');
                    });
                }
            });
        }

        return $query->whereNull('owner_type')->whereNull('owner_id');
    }

    /**
     * Generate a tracking pixel URL.
     */
    public function getTrackingPixelUrl(DocEmail $email): string
    {
        $token = $this->generateTrackingToken($email, 'open');

        return route('docs.track.open', ['token' => $token]);
    }

    /**
     * Generate a tracked link URL.
     */
    public function getTrackedLinkUrl(DocEmail $email, string $url): string
    {
        $token = $this->generateTrackingToken($email, 'click', $url);

        return route('docs.track.click', ['token' => $token]);
    }

    /**
     * Mark an email as opened via tracking.
     */
    public function trackOpen(string $token): bool
    {
        $data = $this->decodeTrackingToken($token);

        if (! $data || $data['type'] !== 'open') {
            return false;
        }

        $email = DocEmail::query()
            ->when(
                config('docs.owner.enabled', false),
                fn (\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder => $query->withoutOwnerScope(),
            )
            ->find($data['email_id']);
        $email?->markAsOpened();

        return $email !== null;
    }

    /**
     * Mark an email link as clicked via tracking.
     */
    public function trackClick(string $token): ?string
    {
        $data = $this->decodeTrackingToken($token);

        if (! $data || $data['type'] !== 'click') {
            return null;
        }

        $email = DocEmail::query()
            ->when(
                config('docs.owner.enabled', false),
                fn (\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder => $query->withoutOwnerScope(),
            )
            ->find($data['email_id']);
        $email?->markAsClicked();

        return $data['url'] ?? null;
    }

    /**
     * Build template variables from a document.
     *
     * @param  array<string, mixed>  $additional
     * @return array<string, mixed>
     */
    private function buildVariables(Doc $doc, array $additional = []): array
    {
        return array_merge([
            'doc_number' => $doc->doc_number,
            'doc_type' => $doc->doc_type,
            'issue_date' => $doc->issue_date->format('d/m/Y'),
            'due_date' => $doc->due_date?->format('d/m/Y') ?? '-',
            'total' => number_format((float) $doc->total, 2),
            'currency' => $doc->currency,
            'company_name' => $doc->company_data['name'] ?? config('docs.company.name'),
            'customer_name' => $doc->customer_data['name'] ?? 'Valued Customer',
        ], $additional);
    }

    /**
     * Get default subject when no template exists.
     */
    private function getDefaultSubject(Doc $doc): string
    {
        $type = ucfirst(str_replace('_', ' ', $doc->doc_type));

        return "{$type} #{$doc->doc_number}";
    }

    /**
     * Get default body when no template exists.
     */
    private function getDefaultBody(Doc $doc): string
    {
        $type = ucfirst(str_replace('_', ' ', $doc->doc_type));
        $company = $doc->company_data['name'] ?? config('docs.company.name');

        return "Dear Customer,\n\nPlease find attached {$type} #{$doc->doc_number}.\n\nTotal: {$doc->currency} " . number_format((float) $doc->total, 2) . "\n\nThank you for your business.\n\n{$company}";
    }

    /**
     * Queue an email for sending.
     */
    private function queueEmail(DocEmail $email, Doc $doc): void
    {
        try {
            $mailable = new DocMail(
                docEmail: $email,
                doc: $doc,
                attachPdf: config('docs.email.attach_pdf', true),
            );

            if (config('docs.email.queue_enabled', true)) {
                Mail::queue($mailable);
            } else {
                Mail::send($mailable);
                $email->update([
                    'status' => EmailStatus::Sent,
                    'sent_at' => CarbonImmutable::now(),
                ]);
            }
        } catch (Throwable $e) {
            $email->update([
                'status' => EmailStatus::Failed,
                'failure_reason' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Generate a tracking token.
     */
    private function generateTrackingToken(DocEmail $email, string $type, ?string $url = null): string
    {
        $data = [
            'email_id' => $email->id,
            'type' => $type,
            'url' => $url,
        ];

        $payload = json_encode($data);

        if ($payload === false) {
            return '';
        }

        return Crypt::encryptString($payload);
    }

    /**
     * Decode a tracking token.
     *
     * @return array<string, mixed>|null
     */
    private function decodeTrackingToken(string $token): ?array
    {
        try {
            $payload = Crypt::decryptString($token);

            /** @var array<string, mixed>|null $decoded */
            $decoded = json_decode($payload, true);

            return is_array($decoded) ? $decoded : null;
        } catch (Throwable) {
            return null;
        }
    }
}
