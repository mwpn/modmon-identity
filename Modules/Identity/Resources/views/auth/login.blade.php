<x-identity::layouts.auth title="Masuk">
    <h2 class="text-xl font-semibold tracking-tight text-gray-900 dark:text-white">Masuk</h2>
    <p class="mt-1.5 text-sm text-gray-500 dark:text-gray-400">Masuk ke akun Anda untuk melanjutkan.</p>

    <form method="POST" action="{{ route('identity.login.submit') }}" class="mt-6 space-y-5">
        @csrf

        <div>
            <label for="email" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Email</label>
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

        <div>
            <label for="password" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Kata Sandi</label>
            <input
                id="password"
                type="password"
                name="password"
                required
                autocomplete="current-password"
                class="block w-full rounded-lg border border-gray-300 bg-white px-3.5 py-2.5 text-sm text-gray-800 shadow-theme-xs transition placeholder:text-gray-400 focus:border-brand-300 focus:outline-none focus:ring-3 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-white dark:focus:border-brand-500"
            >
            @error('password')
                <p class="mt-1.5 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex items-center justify-between gap-3">
            <label class="flex items-center text-sm text-gray-600 dark:text-gray-400">
                <input type="checkbox" name="remember" value="1" class="mr-2 rounded border-gray-300 text-brand-500 focus:ring-brand-500 dark:border-gray-600 dark:bg-gray-900">
                Ingat saya
            </label>

            <a href="{{ route('identity.password.request') }}" class="text-sm font-medium text-brand-600 hover:text-brand-700 dark:text-brand-400">
                Lupa kata sandi?
            </a>
        </div>

        <x-foundation::button type="primary" tag="button" class="w-full">
            Masuk
        </x-foundation::button>
    </form>
</x-identity::layouts.auth>
