<?php

declare(strict_types=1);

// ============================================
// Filament Shipping Actions Tests
// ============================================
// Note: Filament Table Actions require a full panel boot context.
// The actions are tested via the ShipmentResource and ReturnAuthorizationResource
// feature tests where they are actually used.

describe('Actions namespace', function (): void {
    it('has action files in the correct location', function (): void {
        $actionsPath = dirname(__DIR__, 4) . '/packages/filament-shipping/src/Actions';

        expect(file_exists($actionsPath . '/ShipAction.php'))->toBeTrue();
        expect(file_exists($actionsPath . '/PrintLabelAction.php'))->toBeTrue();
        expect(file_exists($actionsPath . '/CancelShipmentAction.php'))->toBeTrue();
        expect(file_exists($actionsPath . '/SyncTrackingAction.php'))->toBeTrue();
        expect(file_exists($actionsPath . '/BulkShipAction.php'))->toBeTrue();
        expect(file_exists($actionsPath . '/BulkPrintLabelsAction.php'))->toBeTrue();
        expect(file_exists($actionsPath . '/BulkCancelAction.php'))->toBeTrue();
        expect(file_exists($actionsPath . '/BulkSyncTrackingAction.php'))->toBeTrue();
        expect(file_exists($actionsPath . '/ApproveReturnAction.php'))->toBeTrue();
        expect(file_exists($actionsPath . '/RejectReturnAction.php'))->toBeTrue();
    });
});
