<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Tambah Riwayat Jabatan') }}
        </h2>
    </x-slot>
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">
                    <form action="{{ route('riwayat_jabatan.store') }}" method="POST">
                        @csrf
                        <a href="{{ url('riwayat_jabatan/'.$pegawai->uuid) }}" class="btn btn-primary mb-3">Kembali</a>
                        <div class="mb-4">
                            <label for="pegawai_uuid" class="block text-gray-700 text-sm font-bold mb-2">Pegawai:</label>
                            <input type="text" name="pegawai_uuid" id="pegawai_uuid" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline @error('pegawai_uuid') border-red-500 @enderror" value="{{ $pegawai->uuid }}" hidden>
                            @error('pegawai_uuid')
                                <p class="text-red-500 text-xs italic">{{ $message }}</p>
                            @enderror
                            <input type="text" name="pegawai_nama" id="pegawai_nama" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline @error('pegawai_nama') border-red-500 @enderror" value="{{ $pegawai->nama }}" readonly>
                        </div>


                        <div class="mb-4">
                            <label for="jabatan_uuid" class="block text-gray-700 text-sm font-bold mb-2">Jabatan:</label>
                            <select name="jabatan_uuid" id="jabatan_uuid" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline @error('jabatan_uuid') border-red-500 @enderror" required>
                                <option value="">Pilih Jabatan</option>
                                @foreach($jabatan as $j)
                                    <option value="{{ $j->uuid }}" {{ old('jabatan_uuid') == $j->uuid ? 'selected' : '' }}>
                                        {{ $j->nama }}
                                    </option>
                                @endforeach
                            </select>
                            @error('jabatan_uuid')
                                <p class="text-red-500 text-xs italic">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <label for="satuan_kerja" class="block text-gray-700 text-sm font-bold mb-2">Satuan Kerja:</label>
                            <input type="text" name="satuan_kerja" id="satuan_kerja" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline @error('satuan_kerja') border-red-500 @enderror" value="{{ old('satuan_kerja') }}" required>
                            @error('satuan_kerja')
                                <p class="text-red-500 text-xs italic">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <label for="tanggal_mulai" class="block text-gray-700 text-sm font-bold mb-2">Tanggal Mulai:</label>
                            <input type="date" name="tanggal_mulai" id="tanggal_mulai" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline @error('tanggal_mulai') border-red-500 @enderror" value="{{ old('tanggal_mulai') }}" required>
                            @error('tanggal_mulai')
                                <p class="text-red-500 text-xs italic">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <label for="tanggal_selesai" class="block text-gray-700 text-sm font-bold mb-2">Tanggal Selesai:</label>
                            <input type="date" name="tanggal_selesai" id="tanggal_selesai" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline @error('tanggal_selesai') border-red-500 @enderror" value="{{ old('tanggal_selesai') }}">
                            @error('tanggal_selesai')
                                <p class="text-red-500 text-xs italic">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <label for="keterangan" class="block text-gray-700 text-sm font-bold mb-2">Keterangan:</label>
                            <textarea name="keterangan" id="keterangan" rows="3" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline @error('keterangan') border-red-500 @enderror">{{ old('keterangan') }}</textarea>
                            @error('keterangan')
                                <p class="text-red-500 text-xs italic">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="flex justify-end">
                            <button type="submit" class="btn btn-primary">Simpan</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

