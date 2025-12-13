<?php

declare(strict_types=1);

namespace AIArmada\Chip\Webhooks;

use AIArmada\CommerceSupport\Webhooks\CommerceWebhookProfile;
use Illuminate\Http\Request;

/**
 * Profile for determining if CHIP webhooks should be processed.
 */
class ChipWebhookProfile extends CommerceWebhookProfile
{
    /**
     * Determine if the request should be processed.
     */
    public function shouldProcess(Request $request): bool
    {
        // Only process if event_type is present
        $eventType = $request->input('event_type');

        if (empty($eventType)) {
            return false;
        }

        // Process all valid CHIP events
        $validPrefixes = [
            'purchase.',
            'payment.',
            'payout.',
            'billing_template_client.',
        ];

        foreach ($validPrefixes as $prefix) {
            if (str_starts_with($eventType, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
