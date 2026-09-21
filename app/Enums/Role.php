<?php

namespace App\Enums;

/**
 * What a user may do:
 *  - viewer: read everything
 *  - staff:  read, and change stock (scan in / scan out)
 *  - admin:  everything, including products, warehouses and reorder levels
 */
enum Role: string
{
    case Admin = 'admin';
    case Staff = 'staff';
    case Viewer = 'viewer';

    public function canAdjustStock(): bool
    {
        return $this !== self::Viewer;
    }

    public function canManageCatalog(): bool
    {
        return $this === self::Admin;
    }
}
