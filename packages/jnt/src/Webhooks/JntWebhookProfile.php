<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Webhooks;

use AIArmada\CommerceSupport\Webhooks\CommerceWebhookProfile;
use Illuminate\Http\Request;

/**
 * Profile for determining if J&T webhooks should be processed.
 */
class JntWebhookProfile extends CommerceWebhookProfile
{
    /**
     * Determine if the request should be processed.
     */
    public function shouldProcess(Request $request): bool
    {
        // Only process if event type is present
        $event = $request->input('event') ?? $request->input('event_type');

        if (empty($event)) {
            return false;
        }

        // Process all valid J&T events
        $validPrefixes = [
            'shipment.',
            'tracking.',
            'delivery.',
            'pickup.',
        ];

        foreach ($validPrefixes as $prefix) {
            if (str_starts_with($event, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
