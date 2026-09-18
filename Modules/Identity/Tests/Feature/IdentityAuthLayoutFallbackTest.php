<?php

declare(strict_types=1);

namespace Modules\Identity\Tests\Feature;

use App\Foundation\Experience\Components\GuestShell;
use App\Foundation\Runtime\ModuleManager;
use App\Foundation\SDK\Contracts\CapabilityRegistryContract;
use App\Foundation\SDK\Contracts\ModuleRegistrarContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use Modules\Identity\Support\AuthLayoutResolver;
use Tests\TestCase;

/**
 * Proves auth layout selection never compiles foundation::guest-shell when
 * GuestShell is unavailable (legacy Foundation ^1.0 without ed4b9b2).
 */
class IdentityAuthLayoutFallbackTest extends TestCase
{
    use RefreshDatabase;

    private string $modulesJsonPath;

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

        AuthLayoutResolver::clearDetector();
    }

    protected function tearDown(): void
    {
        AuthLayoutResolver::clearDetector();

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

    public function test_dispatcher_view_never_references_guest_shell_component(): void
    {
        $dispatcher = File::get(dirname(__DIR__, 2).'/Resources/views/components/layouts/auth.blade.php');
        $legacy = File::get(dirname(__DIR__, 2).'/Resources/views/components/layouts/auth-legacy.blade.php');

        $this->assertStringNotContainsString(
            '<x-foundation::guest-shell',
            $dispatcher,
            'Dispatcher must not embed the GuestShell component tag — Blade compiles tags even inside @if.',
        );
        $this->assertStringNotContainsString('<x-foundation::guest-shell', $legacy);
        $this->assertStringContainsString(
            '<x-foundation::guest-shell',
            File::get(dirname(__DIR__, 2).'/Resources/views/components/layouts/auth-guest.blade.php'),
        );
    }

    public function test_auth_pages_render_legacy_layout_when_guest_shell_unavailable(): void
    {
        AuthLayoutResolver::detectGuestShellUsing(static fn (): bool => false);
        $this->assertFalse(AuthLayoutResolver::guestShellAvailable());
        $this->assertSame(AuthLayoutResolver::LEGACY_VIEW, AuthLayoutResolver::view());

        $this->installIdentity();

        foreach ([
            route('identity.login'),
            route('identity.password.request'),
            route('identity.password.reset', ['token' => 'regression-token']),
        ] as $uri) {
            $response = $this->get($uri);

            $response->assertOk();
            $response->assertDontSee('Back to home', false);
            $response->assertSee(config('app.name', 'ModMon'), false);
            $response->assertSee('&copy; '.date('Y'), false);
        }

        // Compiling the dispatcher must not resolve guest-shell when legacy is selected.
        $compiled = Blade::compileString(
            File::get(dirname(__DIR__, 2).'/Resources/views/components/layouts/auth.blade.php'),
        );
        $this->assertStringNotContainsString('guest-shell', $compiled);
        $this->assertStringNotContainsString('GuestShell', $compiled);
    }

    public function test_auth_pages_render_guest_shell_when_available(): void
    {
        if (! class_exists(GuestShell::class)) {
            $this->markTestSkipped('This host Foundation build does not ship GuestShell.');
        }

        AuthLayoutResolver::clearDetector();
        $this->assertTrue(AuthLayoutResolver::guestShellAvailable());
        $this->assertSame(AuthLayoutResolver::GUEST_VIEW, AuthLayoutResolver::view());

        $this->installIdentity();

        $this->get(route('identity.login'))
            ->assertOk()
            ->assertSee('Back to home', false)
            ->assertSee('Sign in', false);

        $this->get(route('identity.password.request'))
            ->assertOk()
            ->assertSee('Back to home', false);
    }
}
