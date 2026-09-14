<?php

namespace Database\Factories;

use App\Models\ApiKey;
use App\Models\Merchant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApiKey>
 */
class ApiKeyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'merchant_id' => Merchant::factory(),
            'name' => 'Primary integration',
            'key_hash' => hash('sha256', fake()->unique()->uuid()),
            'last_used_at' => null,
            'revoked_at' => null,
        ];
    }
}
