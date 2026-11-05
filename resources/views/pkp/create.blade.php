<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Buat PKP Bulanan</h2>
    </x-slot>

    <div class="container py-4">
        @if (session('created_pkp_uuid'))
            <div class="alert alert-success" role="status">
                Draf PKP berhasil dibuat. Nomor data: {{ session('created_pkp_uuid') }}
            </div>
        @endif

        <h1 class="h3">Buat PKP Bulanan</h1>

        @if ($formData['assessor_options'] === [])
            <p class="alert alert-info" role="status">
                Belum ada Pejabat Penilai yang tersedia. Draf dapat dibuat, tetapi pengajuan memerlukan pilihan Pejabat Penilai dari administrator.
            </p>
        @endif

        <form method="POST" action="{{ route('pkp.store') }}">
            @csrf
            <div class="mb-3">
                <label for="year" class="form-label">Tahun</label>
                <input id="year" name="year" type="number" min="1900" max="9999" value="{{ old('year', $formData['year']) }}" required class="form-control">
                @error('year')<div class="text-danger">{{ $message }}</div>@enderror
            </div>

            <div class="mb-3">
                <label for="month" class="form-label">Bulan</label>
                <input id="month" name="month" type="number" min="1" max="12" value="{{ old('month', $formData['month']) }}" required class="form-control">
                @error('month')<div class="text-danger">{{ $message }}</div>@enderror
            </div>

            <div class="mb-3">
                <label for="source" class="form-label">Cara membuat PKP</label>
                <select id="source" name="source" required class="form-select">
                    <option value="application" @selected(old('source', 'application') === 'application')>Buat di aplikasi</option>
                    <option value="external" @selected(old('source') === 'external')>Unggah dokumen PKP dari luar aplikasi</option>
                </select>
                @error('source')<div class="text-danger">{{ $message }}</div>@enderror
            </div>

            <div class="mb-3">
                <label for="assessor_pegawai_uuid" class="form-label">Pejabat Penilai</label>
                <select id="assessor_pegawai_uuid" name="assessor_pegawai_uuid" class="form-select">
                    <option value="">Pilih nanti</option>
                    @foreach ($formData['assessor_options'] as $option)
                        <option value="{{ $option['pegawai_uuid'] }}" @selected(old('assessor_pegawai_uuid', $formData['default_assessor_uuid']) === $option['pegawai_uuid'])>
                            {{ $option['name'] }} ({{ $option['nip'] }})
                        </option>
                    @endforeach
                </select>
                @error('assessor_pegawai_uuid')<div class="text-danger">{{ $message }}</div>@enderror
            </div>

            <button type="submit" class="btn btn-primary">Buat draf</button>
        </form>
    </div>
</x-app-layout>
