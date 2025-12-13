<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Services;

use AIArmada\FilamentCart\Data\AlertEvent;
use AIArmada\FilamentCart\Models\AlertLog;
use AIArmada\FilamentCart\Models\AlertRule;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Dispatch alerts across multiple channels.
 */
class AlertDispatcher
{
    /**
     * Dispatch an alert based on the rule configuration.
     */
    public function dispatch(AlertRule $rule, AlertEvent $event): AlertLog
    {
        $channels = [];

        // Dispatch to each enabled channel
        if ($rule->notify_database) {
            $channels[] = 'database';
        }

        if ($rule->notify_email && ! empty($rule->email_recipients)) {
            $this->dispatchEmail($rule, $event);
            $channels[] = 'email';
        }

        if ($rule->notify_slack && $rule->slack_webhook_url) {
            $this->dispatchSlack($rule, $event);
            $channels[] = 'slack';
        }

        if ($rule->notify_webhook && $rule->webhook_url) {
            $this->dispatchWebhook($rule, $event);
            $channels[] = 'webhook';
        }

        // Create the log entry
        $log = AlertLog::create([
            'alert_rule_id' => $rule->id,
            'event_type' => $event->event_type,
            'severity' => $event->severity,
            'title' => $event->title,
            'message' => $event->message,
            'event_data' => $event->data,
            'channels_notified' => $channels,
            'cart_id' => $event->cart_id,
            'session_id' => $event->session_id,
        ]);

        // Update rule's last triggered timestamp
        $rule->markTriggered();

        return $log;
    }

    /**
     * Dispatch alert via email.
     */
    public function dispatchEmail(AlertRule $rule, AlertEvent $event): void
    {
        try {
            $recipients = $rule->email_recipients ?? [];

            if (empty($recipients)) {
                return;
            }

            foreach ($recipients as $recipient) {
                Mail::raw($this->formatEmailMessage($event), function ($message) use ($recipient, $event) {
                    $message->to($recipient)
                        ->subject("[{$event->severity}] {$event->title}");
                });
            }
        } catch (Throwable $e) {
            Log::error('Failed to dispatch alert email', [
                'rule_id' => $rule->id,
                'event_type' => $event->event_type,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Dispatch alert to Slack.
     */
    public function dispatchSlack(AlertRule $rule, AlertEvent $event): void
    {
        if (! $rule->slack_webhook_url) {
            return;
        }

        try {
            $color = match ($event->severity) {
                'critical' => '#dc2626',
                'warning' => '#f59e0b',
                default => '#3b82f6',
            };

            $payload = [
                'attachments' => [
                    [
                        'color' => $color,
                        'title' => $event->title,
                        'text' => $event->message,
                        'fields' => [
                            [
                                'title' => 'Event Type',
                                'value' => ucfirst($event->event_type),
                                'short' => true,
                            ],
                            [
                                'title' => 'Severity',
                                'value' => ucfirst($event->severity),
                                'short' => true,
                            ],
                        ],
                        'footer' => 'Cart Monitor',
                        'ts' => $event->occurred_at->timestamp,
                    ],
                ],
            ];

            if ($event->cart_id) {
                $payload['attachments'][0]['fields'][] = [
                    'title' => 'Cart ID',
                    'value' => $event->cart_id,
                    'short' => true,
                ];
            }

            Http::post($rule->slack_webhook_url, $payload);
        } catch (Throwable $e) {
            Log::error('Failed to dispatch alert to Slack', [
                'rule_id' => $rule->id,
                'event_type' => $event->event_type,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Dispatch alert to webhook.
     */
    public function dispatchWebhook(AlertRule $rule, AlertEvent $event): void
    {
        if (! $rule->webhook_url) {
            return;
        }

        try {
            $payload = [
                'event_type' => $event->event_type,
                'severity' => $event->severity,
                'title' => $event->title,
                'message' => $event->message,
                'cart_id' => $event->cart_id,
                'session_id' => $event->session_id,
                'data' => $event->data,
                'occurred_at' => $event->occurred_at->toIso8601String(),
                'rule_id' => $rule->id,
                'rule_name' => $rule->name,
            ];

            Http::timeout(10)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-Alert-Type' => $event->event_type,
                    'X-Alert-Severity' => $event->severity,
                ])
                ->post($rule->webhook_url, $payload);
        } catch (Throwable $e) {
            Log::error('Failed to dispatch alert webhook', [
                'rule_id' => $rule->id,
                'event_type' => $event->event_type,
                'webhook_url' => $rule->webhook_url,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Format email message content.
     */
    private function formatEmailMessage(AlertEvent $event): string
    {
        $lines = [
            "Alert: {$event->title}",
            '',
            $event->message,
            '',
            'Event Type: ' . ucfirst($event->event_type),
            'Severity: ' . ucfirst($event->severity),
            "Occurred At: {$event->occurred_at->toDateTimeString()}",
        ];

        if ($event->cart_id) {
            $lines[] = "Cart ID: {$event->cart_id}";
        }

        if ($event->session_id) {
            $lines[] = "Session ID: {$event->session_id}";
        }

        if (! empty($event->data)) {
            $lines[] = '';
            $lines[] = 'Additional Data:';
            foreach ($event->data as $key => $value) {
                $displayValue = is_array($value) ? json_encode($value) : (string) $value;
                $lines[] = "  {$key}: {$displayValue}";
            }
        }

        return implode("\n", $lines);
    }
}
