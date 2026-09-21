<?php

namespace Tests\Unit;

use App\Enums\Role;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RoleTest extends TestCase
{
    /** @return array<string, array{0: Role, 1: bool, 2: bool}> */
    public static function permissions(): array
    {
        return [
            'admin' => [Role::Admin, true, true],
            'staff' => [Role::Staff, true, false],
            'viewer' => [Role::Viewer, false, false],
        ];
    }

    #[DataProvider('permissions')]
    public function test_each_role_can_do_exactly_what_it_should(Role $role, bool $adjustStock, bool $manageCatalog): void
    {
        $this->assertSame($adjustStock, $role->canAdjustStock());
        $this->assertSame($manageCatalog, $role->canManageCatalog());
    }

    public function test_a_role_is_read_from_its_stored_name(): void
    {
        $this->assertSame(Role::Staff, Role::from('staff'));
        $this->assertNull(Role::tryFrom('superuser'));
    }
}
