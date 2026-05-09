<?php

namespace Database\Factories;

use App\Models\Pegawai;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Pegawai>
 */
class PegawaiFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Pegawai::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nip' => fake()->unique()->numerify('##################'),
            'email' => fake()->unique()->safeEmail(),
            'nama' => fake()->name(),
            'tempat_lahir' => fake()->city(),
            'tanggal_lahir' => fake()->date(),
            'alamat' => fake()->address(),
            'no_hp' => fake()->phoneNumber(),
            'foto' => null,
            'agama' => fake()->randomElement(['Islam', 'Kristen', 'Katolik', 'Hindu', 'Buddha', 'Konghucu']),
            'jenis_kelamin' => fake()->randomElement(['Laki-laki', 'Perempuan']),
            'status_perkawinan' => fake()->randomElement(['Kawin', 'Belum Kawin', 'Duda', 'Janda']),
            'status_pegawai' => fake()->randomElement(['CPNS', 'Hakim', 'PNS', 'PPPK', 'PPNPN']),
        ];
    }
}
