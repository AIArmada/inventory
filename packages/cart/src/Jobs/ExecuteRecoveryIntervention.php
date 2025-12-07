<?php

declare(strict_types=1);

namespace AIArmada\Cart\Jobs;

use AIArmada\Cart\AI\RecoveryOptimizer;
use AIArmada\Cart\CartManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Job to execute a cart recovery intervention.
 *
 * Handles various intervention types:
 * - Email reminders
 * - Push notifications
 * - SMS messages
 */
final class ExecuteRecoveryIntervention implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $backoff = 300;

    /**
     * @param  string  $cartId  Cart ID
     * @param  string  $strategyId  Strategy identifier
     * @param  array<string, mixed>  $strategy  Strategy details
     * @param  array<string, mixed>  $prediction  Prediction details
     */
    public function __construct(
        public readonly string $cartId,
        public readonly string $strategyId,
        public readonly array $strategy,
        public readonly array $prediction
    ) {}

    /**
     * Execute the job.
     */
    public function handle(CartManager $cartManager, RecoveryOptimizer $optimizer): void
    {
        $cartsTable = config('cart.database.table', 'carts');
        $cartRecord = DB::table($cartsTable)->where('id', $this->cartId)->first();

        if (! $cartRecord) {
            Log::info('Cart no longer exists, skipping intervention', ['cart_id' => $this->cartId]);

            return;
        }

        if ($cartRecord->recovered_at !== null) {
            Log::info('Cart already recovered, skipping intervention', ['cart_id' => $this->cartId]);
            $optimizer->recordOutcome($this->cartId, $this->strategyId, true);

            return;
        }

        if ($cartRecord->checkout_abandoned_at === null && $cartRecord->last_activity_at !== null) {
            $lastActivity = strtotime($cartRecord->last_activity_at);
            if (time() - $lastActivity < 900) {
                Log::info('Cart has recent activity, skipping intervention', ['cart_id' => $this->cartId]);

                return;
            }
        }

        $type = $this->strategy['type'] ?? 'email';

        try {
            $result = match ($type) {
                'email' => $this->executeEmailIntervention($cartRecord),
                'push' => $this->executePushNotification($cartRecord),
                'sms' => $this->executeSmsIntervention($cartRecord),
                'popup' => $this->recordPopupIntervention($cartRecord),
                default => $this->executeEmailIntervention($cartRecord),
            };

            Log::info('Executed recovery intervention', [
                'cart_id' => $this->cartId,
                'strategy_id' => $this->strategyId,
                'type' => $type,
                'result' => $result,
            ]);
        } catch (Throwable $e) {
            Log::error('Failed to execute recovery intervention', [
                'cart_id' => $this->cartId,
                'strategy_id' => $this->strategyId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Get job tags.
     *
     * @return array<string>
     */
    public function tags(): array
    {
        return ['cart-recovery', "cart:{$this->cartId}", "strategy:{$this->strategyId}"];
    }

    /**
     * Execute email intervention.
     *
     * @return array<string, mixed>
     */
    private function executeEmailIntervention(object $cartRecord): array
    {
        $userEmail = $this->getUserEmail($cartRecord);

        if (! $userEmail) {
            return ['status' => 'skipped', 'reason' => 'no_email'];
        }

        $template = $this->strategy['parameters']['template'] ?? 'cart_reminder';
        $discountPercentage = $this->strategy['parameters']['discount_percentage'] ?? null;

        $mailData = [
            'cart_id' => $this->cartId,
            'cart_identifier' => $cartRecord->identifier,
            'cart_total' => $cartRecord->total ?? 0,
            'discount_percentage' => $discountPercentage,
            'recovery_url' => $this->generateRecoveryUrl($cartRecord),
            'prediction' => $this->prediction,
        ];

        if (class_exists(\Illuminate\Mail\Mailable::class)) {
            $mailableClass = config("cart.recovery.mailables.{$template}");

            if ($mailableClass && class_exists($mailableClass)) {
                Mail::to($userEmail)->queue(new $mailableClass($mailData));
            }
        }

        return ['status' => 'sent', 'email' => $userEmail, 'template' => $template];
    }

    /**
     * Execute push notification intervention.
     *
     * @return array<string, mixed>
     */
    private function executePushNotification(object $cartRecord): array
    {
        $userId = $cartRecord->user_id;

        if (! $userId) {
            return ['status' => 'skipped', 'reason' => 'no_user'];
        }

        $notificationClass = config('cart.recovery.notifications.push');

        if ($notificationClass && class_exists($notificationClass)) {
            $user = $this->getUser($userId);

            if ($user) {
                Notification::send($user, new $notificationClass([
                    'cart_id' => $this->cartId,
                    'cart_total' => $cartRecord->total ?? 0,
                    'recovery_url' => $this->generateRecoveryUrl($cartRecord),
                ]));

                return ['status' => 'sent', 'user_id' => $userId];
            }
        }

        return ['status' => 'skipped', 'reason' => 'notification_not_configured'];
    }

    /**
     * Execute SMS intervention.
     *
     * @return array<string, mixed>
     */
    private function executeSmsIntervention(object $cartRecord): array
    {
        $phone = $this->getUserPhone($cartRecord);

        if (! $phone) {
            return ['status' => 'skipped', 'reason' => 'no_phone'];
        }

        $smsProvider = config('cart.recovery.sms_provider');

        if ($smsProvider && class_exists($smsProvider)) {
            $provider = app($smsProvider);
            $message = $this->buildSmsMessage($cartRecord);

            $provider->send($phone, $message);

            return ['status' => 'sent', 'phone' => $phone];
        }

        return ['status' => 'skipped', 'reason' => 'sms_not_configured'];
    }

    /**
     * Record popup intervention (for analytics).
     *
     * @return array<string, mixed>
     */
    private function recordPopupIntervention(object $cartRecord): array
    {
        DB::table('cart_popup_interventions')->insert([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'cart_id' => $this->cartId,
            'strategy_id' => $this->strategyId,
            'show_discount' => $this->strategy['parameters']['show_discount'] ?? false,
            'discount_percentage' => $this->strategy['parameters']['discount_percentage'] ?? null,
            'created_at' => now(),
        ]);

        return ['status' => 'recorded', 'type' => 'popup'];
    }

    /**
     * Get user email from cart record.
     */
    private function getUserEmail(object $cartRecord): ?string
    {
        if ($cartRecord->user_id) {
            $user = $this->getUser($cartRecord->user_id);

            return $user?->email;
        }

        $metadata = json_decode($cartRecord->metadata ?? '{}', true);

        return $metadata['email'] ?? null;
    }

    /**
     * Get user phone from cart record.
     */
    private function getUserPhone(object $cartRecord): ?string
    {
        if ($cartRecord->user_id) {
            $user = $this->getUser($cartRecord->user_id);

            return $user?->phone ?? null;
        }

        $metadata = json_decode($cartRecord->metadata ?? '{}', true);

        return $metadata['phone'] ?? null;
    }

    /**
     * Get user model.
     */
    private function getUser(string $userId): ?object
    {
        $userModel = config('auth.providers.users.model');

        if (! $userModel || ! class_exists($userModel)) {
            return null;
        }

        return $userModel::find($userId);
    }

    /**
     * Generate recovery URL.
     */
    private function generateRecoveryUrl(object $cartRecord): string
    {
        $baseUrl = config('cart.recovery.base_url', config('app.url'));
        $token = $this->generateRecoveryToken($cartRecord);

        return "{$baseUrl}/cart/recover/{$this->cartId}?token={$token}";
    }

    /**
     * Generate secure recovery token.
     */
    private function generateRecoveryToken(object $cartRecord): string
    {
        $data = $this->cartId.$cartRecord->identifier.($cartRecord->user_id ?? '');

        return hash_hmac('sha256', $data, config('app.key'));
    }

    /**
     * Build SMS message.
     */
    private function buildSmsMessage(object $cartRecord): string
    {
        $appName = config('app.name');
        $total = number_format(($cartRecord->total ?? 0) / 100, 2);
        $url = $this->generateRecoveryUrl($cartRecord);

        return "{$appName}: Your cart ({$total}) is waiting! Complete your order: {$url}";
    }
}
