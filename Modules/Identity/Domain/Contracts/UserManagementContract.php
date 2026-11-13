<?php

declare(strict_types=1);

namespace Modules\Identity\Domain\Contracts;

use Illuminate\Validation\ValidationException;
use Modules\Identity\Domain\ReadModels\UserReadModel;

/**
 * Public write-side user management contract.
 *
 * `UserQueryContract` stays read-only by design; this is the separate
 * boundary a permission-gated admin UI (RBAC's staff-user management
 * screen, ADR-0042) writes through instead of importing Identity's
 * Eloquent `User` model. No Eloquent model, password, or token ever
 * crosses the module boundary.
 */
interface UserManagementContract
{
    /**
     * @return list<UserReadModel>
     */
    public function all(): array;

    public function find(int $id): ?UserReadModel;

    /**
     * @param  array{name: string, email: string, password: string}  $data
     *
     * @throws ValidationException
     */
    public function create(array $data): UserReadModel;

    /**
     * A blank/missing `password` keeps the existing one unchanged.
     *
     * @param  array{name: string, email: string, password?: ?string}  $data
     *
     * @throws ValidationException
     */
    public function update(int $id, array $data): UserReadModel;

    /**
     * @throws ValidationException
     */
    public function delete(int $id): void;
}
