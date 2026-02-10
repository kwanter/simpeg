<?php

namespace Database\Factories;

use App\Models\Izin;
use App\Models\Pegawai;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Izin>
 */
class IzinFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Izin::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'pegawai_uuid' => Pegawai::factory(),
            'jenis_izin' => fake()->randomElement([
                'Izin Sakit',
                'Izin Keperluan Keluarga',
                'Izin Keperluan Pribadi',
                'Izin Dinas Luar',
                'Izin Setengah Hari',
                'Izin Terlambat',
                'Izin Pulang Cepat',
                'Izin Lainnya',
            ]),
            'tanggal_mulai' => fake()->date(),
            'tanggal_selesai' => fake()->date(),
            'alasan' => fake()->sentence(),
            'status' => 'Diajukan',
            'verifikasi_atasan' => 'Belum Diverifikasi',
            'verifikasi_pimpinan' => 'Belum Diverifikasi',
            'atasan_pimpinan_uuid' => null,
            'pimpinan_uuid' => null,
        ];
    }
}
