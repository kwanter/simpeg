<?php

namespace Database\Factories;

use App\Models\Cuti;
use App\Models\Pegawai;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Cuti>
 */
class CutiFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Cuti::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'pegawai_uuid' => Pegawai::factory(),
            'jenis_cuti' => fake()->randomElement([
                'Cuti Tahunan',
                'Cuti Sakit',
                'Cuti Melahirkan',
                'Cuti Besar',
                'Cuti Karena Alasan Penting',
                'Cuti di Luar Tanggungan Negara',
            ]),
            'tanggal_mulai' => fake()->date(),
            'tanggal_selesai' => fake()->date(),
            'lama_cuti' => fake()->numberBetween(1, 12),
            'alasan' => fake()->sentence(),
            'alamat_selama_cuti' => fake()->address(),
            'no_hp_selama_cuti' => fake()->phoneNumber(),
            'status' => 'Pending',
        ];
    }
}
