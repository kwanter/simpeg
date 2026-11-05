<section class="card mb-4" aria-labelledby="pkp-documents-heading">
    <div class="card-body">
        <h2 id="pkp-documents-heading" class="h4">Dokumen PKP</h2>

        @if ($documentRows['source'] !== null)
            <p>Dokumen sumber: {{ $documentRows['source']['original_name'] }}</p>
            <a class="btn btn-outline-secondary" href="{{ route('pkp.documents.download', [$pkp->uuid, $documentRows['source']['uuid']]) }}">Unduh dokumen sumber</a>
        @endif

        @if ($canUploadSource)
            <form method="POST" enctype="multipart/form-data" action="{{ route('pkp.revisions.documents.store', [$pkp->uuid, $revision->uuid]) }}" class="mt-3">
                @csrf
                <input type="hidden" name="expected_version" value="{{ $pkp->version }}">
                <input type="hidden" name="kind" value="source">
                <label for="source-pdf-file" class="form-label">PDF PKP eksternal (maksimum 10 MiB)</label>
                <input id="source-pdf-file" name="file" type="file" accept="application/pdf,.pdf" required class="form-control">
                <div class="form-check mt-2">
                    <input id="declared-signed" name="declared_signed" type="checkbox" value="1" class="form-check-input">
                    <label for="declared-signed" class="form-check-label">Dokumen sumber ini telah ditandatangani</label>
                </div>
                @error('file')<div class="text-danger">{{ $message }}</div>@enderror
                <button type="submit" class="btn btn-primary mt-2">Unggah PDF sumber</button>
            </form>
        @endif

        @if ($documentRows['final'] !== null)
            <p class="mt-3">Dokumen final diterima: {{ $documentRows['final']['original_name'] }}</p>
            <a class="btn btn-outline-primary" href="{{ route('pkp.documents.download', [$pkp->uuid, $documentRows['final']['uuid']]) }}">Unduh dokumen final</a>
        @endif

        @if ($documentRows['pending_final'] !== null)
            <p class="alert alert-info mt-3" role="status">
                Dokumen {{ $documentRows['pending_final']['original_name'] }} menunggu pemeriksaan Pejabat Penilai.
            </p>
        @elseif ($canUpload)
            <form method="POST" enctype="multipart/form-data" action="{{ route('pkp.revisions.documents.store', [$pkp->uuid, $revision->uuid]) }}" class="mt-3">
                @csrf
                <input type="hidden" name="expected_version" value="{{ $pkp->version }}">
                <input type="hidden" name="kind" value="signed_final">
                <label for="signed-final-file" class="form-label">Dokumen PKP yang telah ditandatangani (PDF, maksimum 10 MiB)</label>
                <input id="signed-final-file" name="file" type="file" accept="application/pdf,.pdf" required class="form-control">
                @if ($documentRows['final'] !== null)
                    <div class="form-check mt-2">
                        <input id="content-unchanged" name="content_unchanged" type="checkbox" value="1" required class="form-check-input">
                        <label for="content-unchanged" class="form-check-label">Saya mengonfirmasi konten PKP tidak berubah.</label>
                    </div>
                @endif
                @error('file')<div class="text-danger">{{ $message }}</div>@enderror
                @error('content_unchanged')<div class="text-danger">{{ $message }}</div>@enderror
                <button type="submit" class="btn btn-primary mt-2">Unggah dokumen</button>
            </form>
        @endif
    </div>
</section>
