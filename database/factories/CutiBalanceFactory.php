<?php

namespace Database\Factories;

use App\Models\CutiBalance;
use App\Models\Pegawai;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CutiBalance>
 */
class CutiBalanceFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = CutiBalance::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'pegawai_uuid' => Pegawai::factory(),
            'year' => (int) date('Y'),
            'total_days' => 12,
            'used_days' => 0,
            'carried_over' => 0,
        ];
    }
}
