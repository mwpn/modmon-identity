<?php

declare(strict_types=1);

namespace Modules\Identity\Tests\Feature;

use App\Foundation\Runtime\ModuleManager;
use App\Foundation\SDK\Contracts\CapabilityRegistryContract;
use App\Foundation\SDK\Contracts\ModuleRegistrarContract;
use App\Foundation\SDK\ModuleState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Exception\CommandNotFoundException;
use Tests\TestCase;

/**
 * Regression: Identity must not eager-resolve ConsoleKernel / getArtisan()
 * during BootProviders. That silently drops deferred Laravel commands
 * (view:clear, optimize:clear, migrate, …) when Identity is enabled at boot.
 */
class IdentityArtisanCommandsPreservedTest extends TestCase
{
    use RefreshDatabase;

    private string $modulesJsonPath;

    /** @var list<string> */
    private array $requiredFrameworkCommands = [
        'view:clear',
        'optimize:clear',
        'config:clear',
        'route:clear',
        'cache:clear',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->modulesJsonPath = storage_path('app/modules.json');
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

    public function test_framework_artisan_commands_remain_after_identity_install(): void
    {
        $this->installIdentity();

        $commands = array_keys(Artisan::all());

        foreach ($this->requiredFrameworkCommands as $command) {
            $this->assertContains(
                $command,
                $commands,
                "Framework command '{$command}' must remain registered after Identity is enabled.",
            );
        }

        $this->assertContains('identity:user:create', $commands);
        $this->assertContains('migrate', $commands);
    }

    public function test_identity_command_available_after_mid_process_install_and_gone_after_disable_fresh_boot(): void
    {
        $this->installIdentity();

        $this->artisan('identity:user:create', [
            '--name' => 'Owner',
            '--email' => 'owner@example.com',
            '--password' => 'secret-password',
        ])->assertSuccessful();

        $disable = app(ModuleManager::class)->disable('identity');
        $this->assertTrue($disable['success'], implode(' | ', $disable['messages']));
        $this->assertEquals(ModuleState::Disabled, app(ModuleRegistrarContract::class)->getState('identity'));

        \Illuminate\Console\Application::forgetBootstrappers();
        $this->refreshApplication();

        $this->assertEquals(
            ModuleState::Disabled,
            app(ModuleRegistrarContract::class)->getState('identity'),
        );
        $this->assertFalse(app(CapabilityRegistryContract::class)->has('identity.user'));
        $this->assertFalse(array_key_exists('identity:user:create', Artisan::all()));

        try {
            Artisan::call('identity:user:create', [
                '--name' => 'Nope',
                '--email' => 'nope@example.com',
                '--password' => 'secret-password',
            ]);
            $this->fail('identity:user:create must not exist when Identity is disabled on a fresh boot');
        } catch (CommandNotFoundException $e) {
            $this->assertStringContainsString('identity:user:create', $e->getMessage());
        }

        foreach ($this->requiredFrameworkCommands as $command) {
            $this->assertContains($command, array_keys(Artisan::all()));
        }
    }

    public function test_login_view_uses_foundation_guest_shell_when_available(): void
    {
        $this->installIdentity();

        $response = $this->get(route('identity.login'));

        $response->assertOk();
        $response->assertSee('Sign in', false);

        if (class_exists(\App\Foundation\Experience\Components\GuestShell::class)) {
            $response->assertSee('Back to home', false);
        } else {
            $response->assertSee(config('app.name', 'ModMon'), false);
        }
    }
}
