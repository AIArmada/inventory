<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Enums;

enum SerialEventType: string
{
    case Registered = 'registered';
    case Received = 'received';
    case Transferred = 'transferred';
    case Reserved = 'reserved';
    case Released = 'released';
    case Sold = 'sold';
    case Shipped = 'shipped';
    case Returned = 'returned';
    case RepairStarted = 'repair_started';
    case RepairCompleted = 'repair_completed';
    case ConditionChanged = 'condition_changed';
    case Disposed = 'disposed';
    case Lost = 'lost';
    case Found = 'found';
    case Recalled = 'recalled';
    case WarrantyUpdated = 'warranty_updated';
    case NoteAdded = 'note_added';

    /**
     * Get a human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Registered => 'Registered',
            self::Received => 'Received',
            self::Transferred => 'Transferred',
            self::Reserved => 'Reserved',
            self::Released => 'Released',
            self::Sold => 'Sold',
            self::Shipped => 'Shipped',
            self::Returned => 'Returned',
            self::RepairStarted => 'Repair Started',
            self::RepairCompleted => 'Repair Completed',
            self::ConditionChanged => 'Condition Changed',
            self::Disposed => 'Disposed',
            self::Lost => 'Marked as Lost',
            self::Found => 'Found',
            self::Recalled => 'Recalled',
            self::WarrantyUpdated => 'Warranty Updated',
            self::NoteAdded => 'Note Added',
        };
    }

    /**
     * Get the icon for UI.
     */
    public function icon(): string
    {
        return match ($this) {
            self::Registered => 'heroicon-o-plus-circle',
            self::Received => 'heroicon-o-inbox-arrow-down',
            self::Transferred => 'heroicon-o-arrows-right-left',
            self::Reserved => 'heroicon-o-lock-closed',
            self::Released => 'heroicon-o-lock-open',
            self::Sold => 'heroicon-o-shopping-cart',
            self::Shipped => 'heroicon-o-truck',
            self::Returned => 'heroicon-o-arrow-uturn-left',
            self::RepairStarted => 'heroicon-o-wrench-screwdriver',
            self::RepairCompleted => 'heroicon-o-check-circle',
            self::ConditionChanged => 'heroicon-o-clipboard-document-check',
            self::Disposed => 'heroicon-o-trash',
            self::Lost => 'heroicon-o-exclamation-triangle',
            self::Found => 'heroicon-o-magnifying-glass',
            self::Recalled => 'heroicon-o-bell-alert',
            self::WarrantyUpdated => 'heroicon-o-shield-check',
            self::NoteAdded => 'heroicon-o-document-text',
        };
    }

    /**
     * Get color for UI.
     */
    public function color(): string
    {
        return match ($this) {
            self::Registered, self::Received => 'success',
            self::Transferred => 'info',
            self::Reserved, self::Released => 'warning',
            self::Sold, self::Shipped => 'primary',
            self::Returned => 'warning',
            self::RepairStarted, self::RepairCompleted => 'gray',
            self::ConditionChanged => 'info',
            self::Disposed, self::Lost, self::Recalled => 'danger',
            self::Found => 'success',
            self::WarrantyUpdated, self::NoteAdded => 'gray',
        };
    }
}
