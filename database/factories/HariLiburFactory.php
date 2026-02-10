<?php

namespace Database\Factories;

use App\Models\HariLibur;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\HariLibur>
 */
class HariLiburFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = HariLibur::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'tanggal' => fake()->dateTimeBetween('now', '+1 year'),
            'nama' => fake()->sentence(3),
            'jenis' => fake()->randomElement(['Libur Nasional', 'Cuti Bersama']),
            'keterangan' => fake()->text(),
        ];
    }
}
