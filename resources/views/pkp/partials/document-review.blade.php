@if ($canDocumentDecide && $documentRows['pending_final'] !== null)
    <section class="card mb-4" aria-labelledby="pkp-document-review-heading">
        <div class="card-body">
            <h2 id="pkp-document-review-heading" class="h4">Pemeriksaan dokumen final</h2>
            <p>Bandingkan dokumen final dengan konten PKP yang telah disetujui sebelum mengambil keputusan.</p>
            @if ($documentRows['final'] !== null)
                <a class="btn btn-outline-secondary" href="{{ route('pkp.documents.download', [$pkp->uuid, $documentRows['final']['uuid']]) }}">Unduh dokumen final sebelumnya</a>
            @endif
            <a class="btn btn-outline-secondary" href="{{ route('pkp.documents.download', [$pkp->uuid, $documentRows['pending_final']['uuid']]) }}">Unduh dokumen yang menunggu pemeriksaan</a>

            <form method="POST" action="{{ route('pkp.documents.decision', [$pkp->uuid, $documentRows['pending_final']['uuid']]) }}" class="mt-3">
                @csrf
                <input type="hidden" name="expected_version" value="{{ $pkp->version }}">
                <input type="hidden" name="revision_uuid" value="{{ $revision->uuid }}">
                <input type="hidden" name="decision" value="accept">
                <div class="form-check mb-2">
                    <input id="matches-approved-content" type="checkbox" name="matches_approved_content" value="1" required class="form-check-input">
                    <label for="matches-approved-content" class="form-check-label">Saya mengonfirmasi dokumen ini sesuai dengan konten PKP yang disetujui.</label>
                </div>
                @error('matches_approved_content')<div class="text-danger">{{ $message }}</div>@enderror
                <button type="submit" class="btn btn-success">Terima dokumen final</button>
            </form>

            <form method="POST" action="{{ route('pkp.documents.decision', [$pkp->uuid, $documentRows['pending_final']['uuid']]) }}" class="mt-3">
                @csrf
                <input type="hidden" name="expected_version" value="{{ $pkp->version }}">
                <input type="hidden" name="revision_uuid" value="{{ $revision->uuid }}">
                <input type="hidden" name="decision" value="return">
                <label for="document-return-reason" class="form-label">Alasan pengembalian</label>
                <textarea id="document-return-reason" name="reason" required maxlength="5000" class="form-control"></textarea>
                @error('reason')<div class="text-danger">{{ $message }}</div>@enderror
                <button type="submit" class="btn btn-warning mt-2">Kembalikan dokumen</button>
            </form>
        </div>
    </section>
@endif
