<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Edit Jabatan') }}
        </h2>
    </x-slot>

    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-body">
                    <form action="{{ route('jabatan.update', $jabatan->uuid) }}" method="POST">
                        @csrf
                        @method('PUT')

                        <div class="mb-3">
                            <label for="nama" class="form-label">Nama Jabatan</label>
                            <input type="text" name="nama" id="nama" class="form-control @error('nama') is-invalid @enderror" value="{{ old('nama', $jabatan->nama) }}" required>
                            @error('nama')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="deskripsi" class="form-label">Deskripsi</label>
                            <textarea name="deskripsi" id="deskripsi" rows="3" class="form-control @error('deskripsi') is-invalid @enderror">{{ old('deskripsi', $jabatan->deskripsi) }}</textarea>
                            @error('deskripsi')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="parent_uuid" class="form-label">Parent Jabatan</label>
                            <select name="parent_uuid" id="parent_uuid" class="form-select @error('parent_uuid') is-invalid @enderror">
                                <option value="">Pilih Parent Jabatan (Opsional)</option>
                                @foreach($jabatans as $j)
                                    @if($j->uuid !== $jabatan->uuid)
                                        <option value="{{ $j->uuid }}" {{ (old('parent_uuid', $jabatan->parent_uuid) == $j->uuid) ? 'selected' : '' }}>
                                            {{ $j->nama }}
                                        </option>
                                    @endif
                                @endforeach
                            </select>
                            @error('parent_uuid')
                                <p class="text-red-500 text-xs italic">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                            <a href="{{ route('jabatan.index') }}" class="btn btn-secondary">
                                Batal
                            </a>
                            <button type="submit" class="btn btn-primary">
                                Update Jabatan
                            </button>
                        </div>
                    </form>
                                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
