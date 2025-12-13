<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Services;

use AIArmada\FilamentCart\Events\CartRecovered;
use AIArmada\FilamentCart\Events\RecoveryAttemptClicked;
use AIArmada\FilamentCart\Events\RecoveryAttemptOpened;
use AIArmada\FilamentCart\Events\RecoveryAttemptSent;
use AIArmada\FilamentCart\Models\RecoveryAttempt;
use AIArmada\FilamentCart\Models\RecoveryCampaign;
use AIArmada\FilamentCart\Models\RecoveryTemplate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use InvalidArgumentException;
use Throwable;

/**
 * Service for dispatching recovery messages.
 */
class RecoveryDispatcher
{
    /**
     * Dispatch a recovery attempt based on channel.
     */
    public function dispatch(RecoveryAttempt $attempt): bool
    {
        if ($attempt->status !== 'queued') {
            return false;
        }

        $template = $attempt->template;
        $cart = $attempt->cart;

        if (! $template || ! $cart) {
            $attempt->markAsFailed('Missing template or cart');

            return false;
        }

        $variables = $this->buildVariables($attempt);

        try {
            $result = match ($attempt->channel) {
                'email' => $this->dispatchEmail($attempt, $template, $variables),
                'sms' => $this->dispatchSms($attempt, $template, $variables),
                'push' => $this->dispatchPush($attempt, $template, $variables),
                default => throw new InvalidArgumentException("Unknown channel: {$attempt->channel}"),
            };

            if ($result) {
                $this->updateCampaignStats($attempt->campaign, 'sent');
                $this->updateTemplateStats($template, 'used');

                event(new RecoveryAttemptSent($attempt));
            }

            return $result;
        } catch (Throwable $e) {
            $attempt->markAsFailed($e->getMessage());

            return false;
        }
    }

    /**
     * Dispatch email recovery.
     *
     * @param  array<string, mixed>  $variables
     */
    public function dispatchEmail(RecoveryAttempt $attempt, RecoveryTemplate $template, array $variables): bool
    {
        if (! $attempt->recipient_email) {
            $attempt->markAsFailed('No email address');

            return false;
        }

        $subject = $template->renderSubject($variables);
        $htmlBody = $template->renderHtmlBody($variables);
        $textBody = $template->renderTextBody($variables);

        Mail::send([], [], function ($message) use ($attempt, $template, $subject, $htmlBody, $textBody) {
            $message->to($attempt->recipient_email, $attempt->recipient_name)
                ->subject($subject)
                ->html($htmlBody);

            if ($textBody) {
                $message->text(fn () => $textBody);
            }

            if ($template->email_from_email) {
                $message->from($template->email_from_email, $template->email_from_name);
            }
        });

        $attempt->markAsSent();

        return true;
    }

    /**
     * Dispatch SMS recovery.
     *
     * @param  array<string, mixed>  $variables
     */
    public function dispatchSms(RecoveryAttempt $attempt, RecoveryTemplate $template, array $variables): bool
    {
        if (! $attempt->recipient_phone) {
            $attempt->markAsFailed('No phone number');

            return false;
        }

        $body = $template->renderSmsBody($variables);

        // SMS implementation would go here
        // This is a placeholder that requires integration with SMS provider (Twilio, Vonage, etc.)

        // For now, just mark as sent
        $attempt->markAsSent();

        return true;
    }

    /**
     * Dispatch push notification recovery.
     *
     * @param  array<string, mixed>  $variables
     */
    public function dispatchPush(RecoveryAttempt $attempt, RecoveryTemplate $template, array $variables): bool
    {
        $push = $template->renderPush($variables);

        // Push notification implementation would go here
        // This is a placeholder that requires integration with push provider (OneSignal, FCM, etc.)

        // For now, just mark as sent
        $attempt->markAsSent();

        return true;
    }

    /**
     * Record an open event.
     */
    public function recordOpen(RecoveryAttempt $attempt): void
    {
        if (! $attempt->isOpened()) {
            $attempt->markAsOpened();

            $this->updateCampaignStats($attempt->campaign, 'opened');

            if ($attempt->template) {
                $this->updateTemplateStats($attempt->template, 'opened');
            }

            event(new RecoveryAttemptOpened($attempt));
        }
    }

    /**
     * Record a click event.
     */
    public function recordClick(RecoveryAttempt $attempt): void
    {
        if (! $attempt->isClicked()) {
            $this->recordOpen($attempt); // Open is implied

            $attempt->markAsClicked();

            $this->updateCampaignStats($attempt->campaign, 'clicked');

            if ($attempt->template) {
                $this->updateTemplateStats($attempt->template, 'clicked');
            }

            event(new RecoveryAttemptClicked($attempt));
        }
    }

    /**
     * Record a conversion event.
     */
    public function recordConversion(RecoveryAttempt $attempt, int $orderValueCents): void
    {
        if (! $attempt->isConverted()) {
            $this->recordClick($attempt); // Click is implied

            $attempt->markAsConverted();

            // Update campaign
            $campaign = $attempt->campaign;
            $campaign->increment('total_recovered');
            $campaign->increment('recovered_revenue_cents', $orderValueCents);

            if ($attempt->template) {
                $this->updateTemplateStats($attempt->template, 'converted');
            }

            // Update cart
            if ($attempt->cart) {
                $attempt->cart->update([
                    'recovered_at' => now(),
                    'metadata' => array_merge($attempt->cart->metadata ?? [], [
                        'recovered_by_campaign_id' => $campaign->id,
                        'recovery_attempt_id' => $attempt->id,
                        'last_recovery_strategy' => $campaign->strategy,
                    ]),
                ]);
            }

            event(new CartRecovered($attempt, $orderValueCents));
        }
    }

    /**
     * Generate tracking URLs.
     *
     * @return array{open: string, click: string, cart: string}
     */
    public function generateTrackingUrls(RecoveryAttempt $attempt): array
    {
        $baseUrl = config('app.url');

        return [
            'open' => URL::signedRoute('cart.recovery.track.open', [
                'attempt' => $attempt->id,
            ]),
            'click' => URL::signedRoute('cart.recovery.track.click', [
                'attempt' => $attempt->id,
            ]),
            'cart' => $baseUrl . '/cart?recovery=' . $attempt->id,
        ];
    }

    /**
     * Build template variables.
     *
     * @return array<string, string>
     */
    private function buildVariables(RecoveryAttempt $attempt): array
    {
        $cart = $attempt->cart;
        $urls = $this->generateTrackingUrls($attempt);

        $cartItems = '';
        if ($cart && is_array($cart->items)) {
            $cartItems = collect($cart->items)
                ->map(fn ($item) => sprintf(
                    '%s x %d - $%.2f',
                    $item['name'] ?? 'Item',
                    $item['quantity'] ?? 1,
                    ($item['price'] ?? 0) / 100,
                ))
                ->implode("\n");
        }

        return [
            'customer_name' => $attempt->recipient_name ?? 'Customer',
            'cart_url' => $urls['cart'],
            'cart_items' => $cartItems,
            'cart_total' => '$' . number_format(($attempt->cart_value_cents ?? 0) / 100, 2),
            'cart_item_count' => (string) ($attempt->cart_items_count ?? 0),
            'discount_code' => $attempt->discount_code ?? '',
            'discount_amount' => $attempt->discount_value_cents
                ? '$' . number_format($attempt->discount_value_cents / 100, 2)
                : '',
            'expiry_time' => $attempt->offer_expires_at
                ? $attempt->offer_expires_at->format('F j, Y g:i A')
                : '',
            'tracking_pixel' => sprintf(
                '<img src="%s" width="1" height="1" alt="" />',
                $urls['open'],
            ),
        ];
    }

    /**
     * Update campaign statistics.
     */
    private function updateCampaignStats(RecoveryCampaign $campaign, string $type): void
    {
        match ($type) {
            'sent' => $campaign->increment('total_sent'),
            'opened' => $campaign->increment('total_opened'),
            'clicked' => $campaign->increment('total_clicked'),
            default => null,
        };
    }

    /**
     * Update template statistics.
     */
    private function updateTemplateStats(RecoveryTemplate $template, string $type): void
    {
        match ($type) {
            'used' => $template->increment('times_used'),
            'opened' => $template->increment('times_opened'),
            'clicked' => $template->increment('times_clicked'),
            'converted' => $template->increment('times_converted'),
            default => null,
        };
    }
}
