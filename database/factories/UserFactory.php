<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'phone' => '0912'.fake()->unique()->numerify('#######'),
            'password' => static::$password ??= Hash::make('password'),
        ];
    }
}
