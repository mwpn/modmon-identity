<?php

declare(strict_types=1);

namespace Modules\Identity\Tests\Feature;

use App\Foundation\Runtime\ModuleManager;
use App\Foundation\SDK\Contracts\CapabilityRegistryContract;
use App\Foundation\SDK\Contracts\ModuleRegistrarContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Modules\Identity\Domain\Contracts\UserManagementContract;
use Modules\Identity\Models\User;
use Tests\TestCase;

/**
 * `UserManagementContract` — the write-side counterpart to the
 * read-only `UserQueryContract` (ADR-0042), consumed by RBAC's
 * staff-user management screen. Tested standalone here since Identity
 * must work without RBAC installed.
 */
class UserManagementContractTest extends TestCase
{
    use RefreshDatabase;

    private string $modulesJsonPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->modulesJsonPath = storage_path('framework/testing/modules.json');
        if (file_exists($this->modulesJsonPath)) {
            unlink($this->modulesJsonPath);
        }

        $this->app->forgetInstance(ModuleRegistrarContract::class);
        $this->app->forgetInstance(ModuleManager::class);
        $this->app->forgetInstance(CapabilityRegistryContract::class);
    }

    protected function tearDown(): void
    {
        if (file_exists($this->modulesJsonPath)) {
            unlink($this->modulesJsonPath);
        }

        parent::tearDown();
    }

    private function installIdentity(): void
    {
        $result = app(ModuleManager::class)->install('identity');

        if (! $result['success']) {
            $this->fail('Identity module could not be installed: '.implode(' | ', $result['messages']));
        }
    }

    public function test_create_hashes_the_password_and_rejects_duplicate_email(): void
    {
        $this->installIdentity();
        $users = app(UserManagementContract::class);

        $created = $users->create(['name' => 'Budi', 'email' => 'budi@example.com', 'password' => 'password123']);

        $this->assertSame('Budi', $created->name);
        $this->assertTrue(Hash::check('password123', User::query()->find($created->id)->password));

        $this->expectException(ValidationException::class);
        $users->create(['name' => 'Dup', 'email' => 'budi@example.com', 'password' => 'password123']);
    }

    public function test_create_rejects_a_short_password(): void
    {
        $this->installIdentity();

        $this->expectException(ValidationException::class);
        app(UserManagementContract::class)->create(['name' => 'Budi', 'email' => 'budi@example.com', 'password' => 'short']);
    }

    public function test_update_changes_name_and_email_and_a_blank_password_keeps_the_old_one(): void
    {
        $this->installIdentity();
        $users = app(UserManagementContract::class);
        $created = $users->create(['name' => 'Budi', 'email' => 'budi@example.com', 'password' => 'password123']);

        $updated = $users->update($created->id, ['name' => 'Budi Renamed', 'email' => 'budi@example.com', 'password' => '']);

        $this->assertSame('Budi Renamed', $updated->name);
        $this->assertTrue(Hash::check('password123', User::query()->find($created->id)->password));
    }

    public function test_update_can_change_the_password(): void
    {
        $this->installIdentity();
        $users = app(UserManagementContract::class);
        $created = $users->create(['name' => 'Budi', 'email' => 'budi@example.com', 'password' => 'password123']);

        $users->update($created->id, ['name' => 'Budi', 'email' => 'budi@example.com', 'password' => 'new-password']);

        $this->assertTrue(Hash::check('new-password', User::query()->find($created->id)->password));
    }

    public function test_update_for_a_missing_user_throws(): void
    {
        $this->installIdentity();

        $this->expectException(ValidationException::class);
        app(UserManagementContract::class)->update(999, ['name' => 'X', 'email' => 'x@example.com']);
    }

    public function test_delete_removes_the_user(): void
    {
        $this->installIdentity();
        $users = app(UserManagementContract::class);
        $created = $users->create(['name' => 'Budi', 'email' => 'budi@example.com', 'password' => 'password123']);

        $users->delete($created->id);

        $this->assertNull(User::query()->find($created->id));
    }

    public function test_delete_for_a_missing_user_throws(): void
    {
        $this->installIdentity();

        $this->expectException(ValidationException::class);
        app(UserManagementContract::class)->delete(999);
    }

    public function test_all_and_find_return_every_user_and_a_single_one(): void
    {
        $this->installIdentity();
        $users = app(UserManagementContract::class);
        $created = $users->create(['name' => 'Budi', 'email' => 'budi@example.com', 'password' => 'password123']);

        $this->assertCount(1, $users->all());
        $this->assertSame($created->id, $users->find($created->id)?->id);
        $this->assertNull($users->find(999));
    }
}
