<x-identity::layouts.auth :title="__('Forgot password')">
    <h2 class="text-xl font-semibold tracking-tight text-gray-900 dark:text-white">{{ __('Forgot your password?') }}</h2>
    <p class="mt-1.5 text-sm leading-relaxed text-gray-500 dark:text-gray-400">
        {{ __('No problem. Just let us know your email address and we will email you a password reset link.') }}
    </p>

    <form method="POST" action="{{ route('identity.password.email') }}" class="mt-6 space-y-5">
        @csrf

        <div>
            <label for="email" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Email') }}</label>
            <input
                id="email"
                type="email"
                name="email"
                value="{{ old('email') }}"
                required
                autofocus
                autocomplete="email"
                class="block w-full rounded-lg border border-gray-300 bg-white px-3.5 py-2.5 text-sm text-gray-800 shadow-theme-xs transition placeholder:text-gray-400 focus:border-brand-300 focus:outline-none focus:ring-3 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-white dark:focus:border-brand-500"
            >
            @error('email')
                <p class="mt-1.5 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>

        <x-foundation::button type="primary" tag="button" class="w-full">
            {{ __('Email Password Reset Link') }}
        </x-foundation::button>
    </form>

    <p class="mt-5 text-center text-sm">
        <a href="{{ route('identity.login') }}" class="font-medium text-brand-600 hover:text-brand-700 dark:text-brand-400">
            {{ __('Back to sign in') }}
        </a>
    </p>
</x-identity::layouts.auth>
