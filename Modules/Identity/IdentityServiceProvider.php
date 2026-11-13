<?php

declare(strict_types=1);

namespace Modules\Identity;

use App\Foundation\SDK\Contributions\ContributesRoutes;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Foundation\Console\Kernel as FoundationConsoleKernel;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Support\ServiceProvider;
use Modules\Identity\Application\Services\StaffUserService;
use Modules\Identity\Console\Commands\CreateUserCommand;
use Modules\Identity\Domain\Contracts\UserManagementContract;
use Modules\Identity\Domain\Contracts\UserQueryContract;
use Modules\Identity\Infrastructure\Queries\EloquentUserQuery;
use Modules\Identity\Models\User;
use ReflectionProperty;

/**
 * Identity module service provider.
 *
 * Phase 1-3: runtime auth-provider wiring and UserQueryContract binding.
 * Phase 4: auth flow routes, views, and the identity:user:create command.
 *
 * Identity deliberately contributes no permissions, navigation, or
 * dashboard widgets (enforced by IdentityBoundaryTest) — it must install
 * standalone, below RBAC in the platform chain, with no authorization
 * concept of its own. A permission-gated staff-management UI consumes
 * `UserManagementContract` from RBAC instead (ADR-0042).
 */
class IdentityServiceProvider extends ServiceProvider implements ContributesRoutes
{
    public function register(): void
    {
        $this->app->singleton(UserQueryContract::class, EloquentUserQuery::class);
        $this->app->singleton(UserManagementContract::class, StaffUserService::class);

        // Called from register() (not boot()) because the Foundation boots
        // module providers after the application has already booted, and
        // Laravel only invokes boot() for providers registered pre-boot.
        $this->loadViewsFrom(__DIR__.'/Resources/views', 'identity');

        if ($this->app->runningInConsole()) {
            // Cold Artisan start: queue until Console Kernel constructs its
            // Artisan map (after deferred providers register their own
            // starting listeners). Never eager-resolve ConsoleKernel /
            // getArtisan() during BootProviders — that silently drops
            // framework commands such as view:clear / migrate.
            $this->commands([
                CreateUserCommand::class,
            ]);

            // Mid-process module:install / module:enable: Artisan may
            // already be running, so Artisan::starting() alone will not
            // attach the command. Register only when Artisan is already
            // constructed — never create it here.
            $this->app->afterResolving(ConsoleKernel::class, function (ConsoleKernel $kernel): void {
                $this->registerCreateUserCommandIfArtisanAlreadyStarted($kernel);
            });

            if ($this->app->resolved(ConsoleKernel::class)) {
                $this->registerCreateUserCommandIfArtisanAlreadyStarted(
                    $this->app->make(ConsoleKernel::class),
                );
            }
        }

        $this->wireAuthModel();
        $this->wireGuestRedirect();
    }

    public function boot(): void
    {
        //
    }

    /**
     * Attach identity:user:create only when the console Kernel already
     * owns a constructed Artisan application (mid-process enable/install).
     *
     * Calling Kernel::registerCommand() while Artisan is still null
     * constructs Artisan early and drops deferred framework commands.
     */
    private function registerCreateUserCommandIfArtisanAlreadyStarted(ConsoleKernel $kernel): void
    {
        if (! $this->consoleKernelHasStartedArtisan($kernel)) {
            return;
        }

        $kernel->registerCommand($this->app->make(CreateUserCommand::class));
    }

    private function consoleKernelHasStartedArtisan(ConsoleKernel $kernel): bool
    {
        if (! $kernel instanceof FoundationConsoleKernel) {
            return false;
        }

        $property = new ReflectionProperty(FoundationConsoleKernel::class, 'artisan');

        return $property->getValue($kernel) !== null;
    }

    /**
     * Point Laravel's users auth provider at Identity's User model while
     * the module is enabled. Never mutates .env.
     *
     * An explicit host AUTH_MODEL override is respected and takes
     * precedence over Identity's wiring.
     */
    private function wireAuthModel(): void
    {
        $override = env('AUTH_MODEL');

        if (is_string($override) && $override !== '') {
            return;
        }

        config()->set('auth.providers.users.model', User::class);
    }

    /**
     * Point Laravel's auth middleware guest redirect at Identity's login
     * form while the module is enabled.
     *
     * Laravel's ApplicationBuilder defaults to route('login') inside an
     * afterResolving(HttpKernel) hook. Registering our own afterResolving
     * callback after that default runs lets Identity override it without
     * host bootstrap/app.php edits and without Foundation knowing about
     * Identity. When Identity is disabled, a fresh process never loads
     * this provider, so Foundation falls back to Laravel's default and
     * never depends on identity.login.
     *
     * Laravel 13's Container::afterResolving() only queues callbacks — it
     * does not invoke them when the abstract is already resolved — so we
     * also apply the override immediately in that case (e.g. module:install
     * during an already-booted HTTP/console process).
     */
    private function wireGuestRedirect(): void
    {
        $wire = function (): void {
            $redirect = fn () => route('identity.login');

            Authenticate::redirectUsing($redirect);
            AuthenticateSession::redirectUsing($redirect);
            AuthenticationException::redirectUsing($redirect);
        };

        $this->app->afterResolving(HttpKernel::class, $wire);

        if ($this->app->resolved(HttpKernel::class)) {
            $wire();
        }
    }

    public function routeFiles(): string|array
    {
        return __DIR__.'/Routes/web.php';
    }
}
