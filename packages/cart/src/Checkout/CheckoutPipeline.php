<?php

declare(strict_types=1);

namespace AIArmada\Cart\Checkout;

use AIArmada\Cart\Cart;
use AIArmada\Cart\Checkout\Contracts\CheckoutStageInterface;
use AIArmada\Cart\Checkout\Exceptions\CheckoutException;
use Throwable;

/**
 * Checkout pipeline orchestrating the multi-stage checkout process.
 *
 * Implements a saga pattern for checkout with support for:
 * - Sequential stage execution
 * - Automatic rollback on failure
 * - Stage-specific error handling
 * - Progress tracking
 */
final class CheckoutPipeline
{
    /**
     * @var array<CheckoutStageInterface>
     */
    private array $stages = [];

    /**
     * @var array<string, mixed>
     */
    private array $context = [];

    /**
     * @var array<string>
     */
    private array $completedStages = [];

    public function __construct(
        private readonly Cart $cart
    ) {}

    /**
     * Add a stage to the pipeline.
     */
    public function addStage(CheckoutStageInterface $stage): self
    {
        $this->stages[] = $stage;

        return $this;
    }

    /**
     * Add multiple stages to the pipeline.
     *
     * @param  array<CheckoutStageInterface>  $stages
     */
    public function addStages(array $stages): self
    {
        foreach ($stages as $stage) {
            $this->addStage($stage);
        }

        return $this;
    }

    /**
     * Set context data for the pipeline.
     *
     * @param  array<string, mixed>  $context
     */
    public function withContext(array $context): self
    {
        $this->context = array_merge($this->context, $context);

        return $this;
    }

    /**
     * Execute the checkout pipeline.
     *
     * @throws CheckoutException If any stage fails
     */
    public function execute(): CheckoutResult
    {
        $this->completedStages = [];

        try {
            foreach ($this->stages as $stage) {
                $this->executeStage($stage);
            }

            return new CheckoutResult(
                success: true,
                cart: $this->cart,
                context: $this->context,
                completedStages: $this->completedStages,
            );
        } catch (Throwable $e) {
            $this->rollback();

            if ($e instanceof CheckoutException) {
                throw $e;
            }

            throw CheckoutException::stageFailed(
                stage: end($this->completedStages) ?: 'unknown',
                message: $e->getMessage(),
                previous: $e
            );
        }
    }

    /**
     * Get the list of completed stages.
     *
     * @return array<string>
     */
    public function getCompletedStages(): array
    {
        return $this->completedStages;
    }

    /**
     * Get the current context.
     *
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Execute a single stage.
     */
    private function executeStage(CheckoutStageInterface $stage): void
    {
        $stageName = $stage->getName();

        if (! $stage->shouldExecute($this->cart, $this->context)) {
            return;
        }

        $result = $stage->execute($this->cart, $this->context);

        if (! $result->success) {
            throw CheckoutException::stageFailed($stageName, $result->message);
        }

        // Merge stage result into context
        $this->context = array_merge($this->context, $result->data);
        $this->completedStages[] = $stageName;
    }

    /**
     * Rollback completed stages in reverse order.
     */
    private function rollback(): void
    {
        $stagesToRollback = array_reverse($this->completedStages);

        foreach ($stagesToRollback as $stageName) {
            $stage = $this->findStageByName($stageName);

            if ($stage !== null && $stage->supportsRollback()) {
                try {
                    $stage->rollback($this->cart, $this->context);
                } catch (Throwable) {
                    // Log but continue rollback
                }
            }
        }
    }

    /**
     * Find a stage by its name.
     */
    private function findStageByName(string $name): ?CheckoutStageInterface
    {
        foreach ($this->stages as $stage) {
            if ($stage->getName() === $name) {
                return $stage;
            }
        }

        return null;
    }
}
