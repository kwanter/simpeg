<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Pengaturan Pejabat Penilai</h2>
    </x-slot>

    <div class="container py-4">
        <h1 class="h3">Pengaturan Pejabat Penilai</h1>
        <p>{{ $pegawai->nama }} ({{ $pegawai->nip }})</p>

        <form method="POST" action="{{ route('pkp.assessor-settings.update', $pegawai->uuid) }}">
            @csrf
            @method('PUT')
            <input type="hidden" name="expected_version" value="{{ $settings->version ?? 0 }}">

            <div class="mb-3">
                <label for="default_assessor_uuid" class="form-label">Pejabat Penilai bawaan</label>
                <select id="default_assessor_uuid" name="default_assessor_uuid" class="form-select">
                    <option value="">Pilih Pejabat Penilai</option>
                    @foreach ($assessors as $assessor)
                        <option value="{{ $assessor->uuid }}" @selected(old('default_assessor_uuid', $settings->default_assessor_uuid ?? null) === $assessor->uuid)>
                            {{ $assessor->nama }} ({{ $assessor->nip }})
                        </option>
                    @endforeach
                </select>
                @error('default_assessor_uuid')<div class="text-danger">{{ $message }}</div>@enderror
            </div>

            <fieldset class="mb-3">
                <legend class="fs-6">Pejabat Penilai alternatif yang diizinkan</legend>
                @foreach ($assessors as $assessor)
                    <div class="form-check">
                        <input
                            id="assessor-{{ $assessor->uuid }}"
                            class="form-check-input"
                            type="checkbox"
                            name="allowed_assessor_uuids[]"
                            value="{{ $assessor->uuid }}"
                            @checked(in_array($assessor->uuid, old('allowed_assessor_uuids', $allowedAssessorUuids), true))
                        >
                        <label class="form-check-label" for="assessor-{{ $assessor->uuid }}">
                            {{ $assessor->nama }} ({{ $assessor->nip }})
                        </label>
                    </div>
                @endforeach
                @error('allowed_assessor_uuids')<div class="text-danger">{{ $message }}</div>@enderror
            </fieldset>

            <button type="submit" class="btn btn-primary">Simpan pengaturan</button>
        </form>
    </div>
</x-app-layout>
