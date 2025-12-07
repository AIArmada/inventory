<?php

declare(strict_types=1);

namespace AIArmada\Cart\Jobs;

use AIArmada\Cart\AI\AbandonmentPredictor;
use AIArmada\Cart\AI\RecoveryOptimizer;
use AIArmada\Cart\CartManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Job to analyze carts for abandonment and trigger recovery strategies.
 *
 * This job runs periodically to:
 * 1. Identify carts at risk of abandonment
 * 2. Predict abandonment probability
 * 3. Queue appropriate recovery interventions
 */
final class AnalyzeCartForAbandonment implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 60;

    public function __construct(
        public readonly ?string $cartId = null,
        public readonly int $batchSize = 100
    ) {}

    /**
     * Execute the job.
     */
    public function handle(
        AbandonmentPredictor $predictor,
        RecoveryOptimizer $optimizer,
        CartManager $cartManager
    ): void {
        if ($this->cartId !== null) {
            $this->analyzeSpecificCart($this->cartId, $predictor, $optimizer, $cartManager);

            return;
        }

        $this->analyzeAbandonedCarts($predictor, $optimizer, $cartManager);
    }

    /**
     * Get the tags for the job.
     *
     * @return array<string>
     */
    public function tags(): array
    {
        return $this->cartId
            ? ['cart-abandonment', "cart:{$this->cartId}"]
            : ['cart-abandonment', 'batch'];
    }

    /**
     * Analyze a specific cart.
     */
    private function analyzeSpecificCart(
        string $cartId,
        AbandonmentPredictor $predictor,
        RecoveryOptimizer $optimizer,
        CartManager $cartManager
    ): void {
        $cartsTable = config('cart.database.table', 'carts');
        $cartRecord = DB::table($cartsTable)->where('id', $cartId)->first();

        if (! $cartRecord) {
            Log::warning('Cart not found for abandonment analysis', ['cart_id' => $cartId]);

            return;
        }

        try {
            $cart = $cartManager
                ->setIdentifier($cartRecord->identifier)
                ->setInstance($cartRecord->instance ?? 'default')
                ->getCurrentCart();

            $prediction = $predictor->predict($cart, $cartRecord->user_id);

            if ($prediction->needsIntervention()) {
                $strategy = $optimizer->getOptimalStrategy($cart, $prediction);

                $this->queueIntervention($cart->getId(), $strategy, $prediction);

                Log::info('Queued recovery intervention', [
                    'cart_id' => $cart->getId(),
                    'strategy' => $strategy->id,
                    'probability' => $prediction->probability,
                    'risk_level' => $prediction->riskLevel,
                ]);
            }
        } catch (Throwable $e) {
            Log::error('Failed to analyze cart for abandonment', [
                'cart_id' => $cartId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Analyze all potentially abandoned carts.
     */
    private function analyzeAbandonedCarts(
        AbandonmentPredictor $predictor,
        RecoveryOptimizer $optimizer,
        CartManager $cartManager
    ): void {
        $highRiskCarts = $predictor->getHighRiskCarts($this->batchSize);

        $analyzed = 0;
        $interventionsQueued = 0;

        foreach ($highRiskCarts as $cartData) {
            try {
                $cart = $cartManager
                    ->setIdentifier($cartData['identifier'])
                    ->getCurrentCart();

                $prediction = $predictor->predict($cart);

                if ($prediction->needsIntervention()) {
                    $strategy = $optimizer->getOptimalStrategy($cart, $prediction);
                    $this->queueIntervention($cart->getId(), $strategy, $prediction);
                    $interventionsQueued++;
                }

                $analyzed++;
            } catch (Throwable $e) {
                Log::warning('Failed to analyze cart', [
                    'cart_id' => $cartData['cart_id'] ?? 'unknown',
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('Completed abandonment analysis batch', [
            'carts_analyzed' => $analyzed,
            'interventions_queued' => $interventionsQueued,
        ]);
    }

    /**
     * Queue an intervention for execution.
     */
    private function queueIntervention(
        string $cartId,
        \AIArmada\Cart\AI\RecoveryStrategy $strategy,
        \AIArmada\Cart\AI\AbandonmentPrediction $prediction
    ): void {
        $delay = now()->addMinutes($strategy->delayMinutes);

        ExecuteRecoveryIntervention::dispatch($cartId, $strategy->id, $strategy->toArray(), $prediction->toArray())
            ->delay($delay)
            ->onQueue('cart-recovery');

        DB::table(config('cart.database.table', 'carts'))
            ->where('id', $cartId)
            ->update([
                'recovery_attempts' => DB::raw('recovery_attempts + 1'),
                'updated_at' => now(),
            ]);
    }
}
