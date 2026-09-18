{{--
    Dispatcher only: never place an x-foundation guest shell tag here.
    Blade compiles component tags in a template even inside @if, so the
    GuestShell markup lives solely in auth-guest.blade.php and is included
    only when AuthLayoutResolver selects that view at runtime.
--}}
@include(\Modules\Identity\Support\AuthLayoutResolver::view())
