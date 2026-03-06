<?php

namespace Tests\Fixtures;

use App\Models\UserModel;

class UserFixture
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function admin(array $attributes = []): UserModel
    {
        return UserModel::factory()->admin()->create($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function customer(array $attributes = []): UserModel
    {
        return UserModel::factory()->create($attributes);
    }

    /**
     * Create an admin and a customer pair, useful for most E2E tests.
     *
     * @param  array<string, mixed>  $adminAttrs
     * @param  array<string, mixed>  $customerAttrs
     * @return array{admin: UserModel, customer: UserModel}
     */
    public static function adminAndCustomer(array $adminAttrs = [], array $customerAttrs = []): array
    {
        return [
            'admin' => self::admin($adminAttrs),
            'customer' => self::customer($customerAttrs),
        ];
    }
}
