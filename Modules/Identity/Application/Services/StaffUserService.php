<?php

declare(strict_types=1);

namespace Modules\Identity\Application\Services;

use Illuminate\Validation\ValidationException;
use Modules\Identity\Domain\Contracts\UserManagementContract;
use Modules\Identity\Domain\ReadModels\UserReadModel;
use Modules\Identity\Models\User;

/**
 * Eloquent-backed implementation of `UserManagementContract` — the
 * write-side counterpart to `EloquentUserQuery` (ADR-0042). Internal to
 * this module except through the contract.
 */
final class StaffUserService implements UserManagementContract
{
    /**
     * @return list<UserReadModel>
     */
    public function all(): array
    {
        return User::query()
            ->orderBy('name')
            ->get()
            ->map(fn (User $user) => $this->toRead($user))
            ->all();
    }

    public function find(int $id): ?UserReadModel
    {
        $user = User::query()->find($id);

        return $user === null ? null : $this->toRead($user);
    }

    /**
     * @param  array{name: string, email: string, password: string}  $data
     *
     * @throws ValidationException
     */
    public function create(array $data): UserReadModel
    {
        $validated = $this->validate($data, requirePassword: true, ignoreUserId: null);

        $user = User::query()->create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
        ]);

        return $this->toRead($user);
    }

    /**
     * @param  array{name: string, email: string, password?: ?string}  $data
     *
     * @throws ValidationException
     */
    public function update(int $id, array $data): UserReadModel
    {
        $user = User::query()->find($id);

        if ($user === null) {
            throw ValidationException::withMessages(['user' => "User '{$id}' was not found."]);
        }

        $validated = $this->validate($data, requirePassword: false, ignoreUserId: $id);

        $attributes = [
            'name' => $validated['name'],
            'email' => $validated['email'],
        ];

        if ($validated['password'] !== null) {
            $attributes['password'] = $validated['password'];
        }

        $user->update($attributes);

        return $this->toRead($user);
    }

    /**
     * @throws ValidationException
     */
    public function delete(int $id): void
    {
        $user = User::query()->find($id);

        if ($user === null) {
            throw ValidationException::withMessages(['user' => "User '{$id}' was not found."]);
        }

        $user->delete();
    }

    /**
     * @param  array{name?: ?string, email?: ?string, password?: ?string}  $data
     * @return array{name: string, email: string, password: ?string}
     *
     * @throws ValidationException
     */
    private function validate(array $data, bool $requirePassword, ?int $ignoreUserId): array
    {
        $name = trim((string) ($data['name'] ?? ''));
        $email = trim((string) ($data['email'] ?? ''));
        $password = trim((string) ($data['password'] ?? ''));

        $errors = [];

        if ($name === '') {
            $errors['name'] = 'Name is required.';
        }

        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'A valid email address is required.';
        } else {
            $duplicate = User::query()->where('email', $email)
                ->when($ignoreUserId !== null, fn ($query) => $query->whereKeyNot($ignoreUserId))
                ->exists();

            if ($duplicate) {
                $errors['email'] = "A user with email '{$email}' already exists.";
            }
        }

        if ($requirePassword && $password === '') {
            $errors['password'] = 'Password is required.';
        } elseif ($password !== '' && strlen($password) < 8) {
            $errors['password'] = 'Password must be at least 8 characters.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return [
            'name' => $name,
            'email' => $email,
            'password' => $password === '' ? null : $password,
        ];
    }

    private function toRead(User $user): UserReadModel
    {
        return new UserReadModel(
            id: $user->id,
            name: $user->name,
            email: $user->email,
            emailVerifiedAt: $user->email_verified_at?->toImmutable(),
            createdAt: $user->created_at?->toImmutable(),
        );
    }
}
