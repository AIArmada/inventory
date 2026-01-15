<?php

declare(strict_types=1);

namespace AIArmada\Cart\Storage;

/**
 * Complete storage interface for cart persistence.
 *
 * Provides core CRUD operations for items, conditions, and metadata.
 */
interface StorageInterface extends CartStorageInterface
{
    // This interface extends CartStorageInterface.
    // No additional methods required - all are inherited from parent interface.
}
