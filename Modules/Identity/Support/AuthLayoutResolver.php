<?php

declare(strict_types=1);

namespace Modules\Identity\Support;

/**
 * Selects the Identity auth shell view before Blade compiles it.
 *
 * Blade still compiles `<x-foundation::guest-shell>` inside an `@if` in the
 * same template, so GuestShell must live in a separate view that is only
 * `@include`d when available. Detection is overridable for regression tests
 * that simulate Foundation trees without GuestShell.
 */
final class AuthLayoutResolver
{
    public const GUEST_VIEW = 'identity::components.layouts.auth-guest';

    public const LEGACY_VIEW = 'identity::components.layouts.auth-legacy';

    private const GUEST_SHELL_CLASS = 'App\\Foundation\\Experience\\Components\\GuestShell';

    /** @var (callable(): bool)|null */
    private static $availabilityDetector = null;

    public static function guestShellAvailable(): bool
    {
        if (self::$availabilityDetector !== null) {
            return (bool) (self::$availabilityDetector)();
        }

        return class_exists(self::GUEST_SHELL_CLASS);
    }

    public static function view(): string
    {
        return self::guestShellAvailable() ? self::GUEST_VIEW : self::LEGACY_VIEW;
    }

    /**
     * @param  (callable(): bool)|null  $detector
     */
    public static function detectGuestShellUsing(?callable $detector): void
    {
        self::$availabilityDetector = $detector;
    }

    public static function clearDetector(): void
    {
        self::$availabilityDetector = null;
    }
}
