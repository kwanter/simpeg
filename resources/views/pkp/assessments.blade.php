<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Penilaian PKP</h2>
    </x-slot>

    <div class="container py-4">
        <h1 class="h3">Antrean penilaian {{ $kind === 'content' ? 'konten' : 'dokumen' }}</h1>
        @if (session('status'))
            <p class="alert alert-success" role="status">{{ session('status') }}</p>
        @endif
        @if (session('decision_pkp_uuid'))
            <p><a href="{{ route('pkp.show', session('decision_pkp_uuid')) }}">Buka riwayat keputusan</a></p>
        @endif

        @forelse ($items as $item)
            <article class="card mb-3">
                <div class="card-body">
                    <h2 class="h5">{{ $item['employee_name'] }}</h2>
                    <p>Periode {{ $item['month'] }}/{{ $item['year'] }} · Revisi {{ $item['revision_number'] }}</p>
                    <p>{{ $kind === 'content' ? 'Dikirim' : 'Diunggah' }} {{ $kind === 'content' ? $item['submitted_at'] : $item['uploaded_at'] }}</p>
                    <a class="btn btn-primary" href="{{ route('pkp.show', ['pkp_uuid' => $item['pkp_uuid'], 'revision_uuid' => $item['revision_uuid']]) }}">Buka PKP untuk dinilai</a>
                </div>
            </article>
        @empty
            <p role="status">Tidak ada PKP yang menunggu penilaian.</p>
        @endforelse
        {{ $items->links() }}
    </div>
</x-app-layout>
