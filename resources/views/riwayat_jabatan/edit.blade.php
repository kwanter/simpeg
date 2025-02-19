<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Edit Riwayat Jabatan') }}
        </h2>
    </x-slot>

    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-body">
                        <form action="/riwayat_jabatan/{{ $riwayatJabatan->uuid }}" method="POST">
                            @csrf
                            @method('PUT')
                            <a href="{{ url('riwayat_jabatan/'.$pegawai->uuid) }}" class="btn btn-danger mb-3">Kembali</a>

                            <div class="mb-3">
                                <label for="pegawai_uuid" class="form-label">Pegawai</label>
                                <input type="text" class="form-control" value="{{ $pegawai->nama }}" disabled>
                                <input type="hidden" name="pegawai_uuid" value="{{ $pegawai->uuid }}">
                            </div>

                            <div class="mb-3">
                                <label for="jabatan_uuid" class="form-label">Jabatan</label>
                                <select name="jabatan_uuid" id="jabatan_uuid" class="form-select @error('jabatan_uuid') is-invalid @enderror" required>
                                    <option value="">Pilih Jabatan</option>
                                    @foreach($jabatans as $j)
                                        <option value="{{ $j->uuid }}" {{ old('jabatan_uuid', $riwayatJabatan->jabatan_uuid) == $j->uuid ? 'selected' : '' }}>
                                            {{ $j->nama }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('jabatan_uuid')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label for="satuan_kerja" class="form-label">Satuan Kerja</label>
                                <input type="text" name="satuan_kerja" id="satuan_kerja" class="form-control @error('satuan_kerja') is-invalid @enderror" value="{{ old('satuan_kerja', $riwayatJabatan->satuan_kerja) }}" required>
                                @error('satuan_kerja')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label for="tanggal_mulai" class="form-label">Tanggal Mulai</label>
                                <input type="date" name="tanggal_mulai" id="tanggal_mulai" class="form-control @error('tanggal_mulai') is-invalid @enderror" value="{{ old('tanggal_mulai', $riwayatJabatan->tanggal_mulai ? $riwayatJabatan->tanggal_mulai->format('Y-m-d') : '') }}" required>
                                @error('tanggal_mulai')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label for="keterangan" class="form-label">Keterangan</label>
                                <textarea name="keterangan" id="keterangan" rows="3" class="form-control @error('keterangan') is-invalid @enderror">{{ old('keterangan', $riwayatJabatan->keterangan) }}</textarea>
                                @error('keterangan')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="d-flex justify-content-end gap-2">
                                <a href="{{ url('riwayat_jabatan/'.$pegawai->uuid) }}" class="btn btn-secondary">Batal</a>
                                <button type="submit" class="btn btn-primary">Update Riwayat Jabatan</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
