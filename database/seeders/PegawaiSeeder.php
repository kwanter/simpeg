<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Pegawai;
use Illuminate\Support\Str;
use Faker\Factory as Faker;

class PegawaiSeeder extends Seeder
{
    public function run()
    {
        $faker = Faker::create('id_ID');

        for ($i = 0; $i < 50; $i++) {
            Pegawai::create([
                'uuid' => Str::uuid(),
                'nama' => $faker->name,
                'nip' => $faker->unique()->numerify('##################'),
                'tempat_lahir' => $faker->city,
                'tanggal_lahir' => $faker->date('Y-m-d', '-30 years'),
                'jenis_kelamin' => $faker->randomElement(['Laki-laki', 'Perempuan']),
                'agama' => $faker->randomElement(['Islam', 'Kristen', 'Katolik', 'Buddha', 'Hindu', 'Konghucu']),
                'alamat' => $faker->address,
                'no_hp' => $faker->phoneNumber,
                'status_pegawai' => $faker->randomElement(['CPNS', 'Hakim', 'PNS' ,'PPPK', 'PPNPN']),
                'status_perkawinan' => $faker->randomElement(['Kawin', 'Belum Kawin', 'Duda', 'Janda']),
            ]);
        }
    }
}
