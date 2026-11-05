<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Batas Waktu PKP</h2>
    </x-slot>

    <div class="container py-4">
        <h1 class="h3">Batas Waktu PKP {{ sprintf('%02d', $data['month']) }}/{{ $data['year'] }}</h1>
        <p>Waktu lokal menggunakan Asia/Makassar. Batas berlaku untuk semua Pegawai dan kedua sumber PKP.</p>
        @if (session('status'))
            <p class="alert alert-success" role="status">{{ session('status') }}</p>
        @endif

        <form method="POST" action="{{ route('pkp.deadline.update', [$data['year'], $data['month']]) }}">
            @csrf
            @method('PUT')
            <input type="hidden" name="expected_version" value="{{ $data['version'] }}">
            <div class="mb-3">
                <label for="deadline_local" class="form-label">Batas waktu lokal</label>
                <input id="deadline_local" name="deadline_local" type="datetime-local" step="1" value="{{ old('deadline_local', $data['deadline_local']) }}" class="form-control">
                @error('deadline_local')<div class="text-danger">{{ $message }}</div>@enderror
            </div>
            <button type="submit" class="btn btn-primary">Simpan batas waktu</button>
        </form>
    </div>
</x-app-layout>
