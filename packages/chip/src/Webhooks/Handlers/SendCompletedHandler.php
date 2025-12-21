<?php

declare(strict_types=1);

namespace AIArmada\Chip\Webhooks\Handlers;

use AIArmada\Chip\Data\EnrichedWebhookPayload;
use AIArmada\Chip\Data\WebhookResult;
use AIArmada\Chip\Enums\SendInstructionState;
use AIArmada\Chip\Events\PayoutSuccess;
use AIArmada\Chip\Models\SendInstruction;

/**
 * Handles send_instruction.completed and payout.success webhook events.
 */
class SendCompletedHandler implements WebhookHandler
{
    public function handle(EnrichedWebhookPayload $payload): WebhookResult
    {
        $sendInstructionId = $payload->get('id') ?? $payload->get('data.id');

        if (empty($sendInstructionId)) {
            return WebhookResult::skipped('No send instruction ID in payload');
        }

        $instruction = SendInstruction::query()
            ->withoutOwnerScope()
            ->where('id', $sendInstructionId)
            ->first();

        if ($instruction === null) {
            return WebhookResult::skipped('Send instruction not found locally');
        }

        // Update local status
        $instruction->update([
            'state' => SendInstructionState::COMPLETED,
        ]);

        // Emit Laravel event
        event(new PayoutSuccess(
            payout: \AIArmada\Chip\Data\PayoutData::from($payload->rawPayload),
            payload: $payload->rawPayload,
        ));

        return WebhookResult::handled("Send instruction {$instruction->id} marked as completed");
    }
}
