<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Daftar PKP Bulanan</h2>
    </x-slot>

    <div class="container py-4">
        <h1 class="h3">Daftar PKP Bulanan</h1>

        @if ($canCreate)
            <p><a href="{{ route('pkp.create') }}" class="btn btn-primary">Buat PKP Bulanan</a></p>
        @endif

        <form method="GET" action="{{ route('pkp.index') }}" class="row g-3 mb-4">
            <div class="col-auto">
                <label for="year" class="form-label">Tahun</label>
                <input id="year" name="year" type="number" min="1900" max="9999" value="{{ $year }}" required class="form-control">
            </div>
            @if ($canViewAll)
                <div class="col-auto">
                    <label for="scope" class="form-label">Cakupan</label>
                    <select id="scope" name="scope" class="form-select">
                        <option value="own" @selected($scope === 'own')>PKP saya</option>
                        <option value="all" @selected($scope === 'all')>Semua pegawai</option>
                    </select>
                </div>
            @endif
            <div class="col-auto align-self-end">
                <button type="submit" class="btn btn-secondary">Tampilkan</button>
            </div>
        </form>

        @if ($items->isEmpty())
            <p role="status" class="alert alert-info">Belum ada PKP untuk tahun {{ $year }}.</p>
        @else
            <div class="table-responsive">
                <table class="table table-striped align-middle">
                    <thead>
                        <tr>
                            @if ($scope === 'all')
                                <th scope="col">Pegawai</th>
                            @endif
                            <th scope="col">Periode</th>
                            <th scope="col">Persetujuan konten</th>
                            <th scope="col">Dokumen final</th>
                            <th scope="col">Tindakan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($items as $item)
                            <tr>
                                @if ($scope === 'all')
                                    <td>{{ $item['pegawai_name'] }} ({{ $item['nip'] }})</td>
                                @endif
                                <td>{{ sprintf('%02d/%04d', $item['month'], $item['year']) }}</td>
                                <td>{{ $item['content_approved'] ? 'Disetujui' : 'Belum disetujui' }}</td>
                                <td>{{ $item['document_complete'] ? 'Lengkap' : 'Belum lengkap' }}</td>
                                <td><a href="{{ route('pkp.show', $item['pkp_uuid']) }}" class="btn btn-sm btn-primary">Buka PKP</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $items->appends(['year' => $year, 'scope' => $scope])->links() }}
        @endif
    </div>
</x-app-layout>
