<section class="container py-4" aria-labelledby="pkp-history-heading">
    <h2 id="pkp-history-heading" class="h4">Riwayat PKP</h2>
    <dl>
        <dt>Pengajuan pertama (UTC)</dt><dd>{{ $pkp->getRawOriginal('first_submitted_at') ?? 'Belum diajukan' }}</dd>
        <dt>Batas waktu saat pengajuan (UTC)</dt><dd>{{ $pkp->getRawOriginal('deadline_snapshot_at') ?? 'Belum ditetapkan' }}</dd>
        <dt>Ketepatan waktu</dt><dd>{{ match ($pkp->lateness) { 'on_time' => 'Tepat waktu', 'late' => 'Terlambat', 'deadline_not_set' => 'Batas waktu belum ditetapkan', default => 'Belum diajukan' } }}</dd>
    </dl>
    @forelse ($history['attempts'] as $attempt)
        <article class="border rounded p-3 mb-3">
            <h3 class="h5">Revisi {{ $attempt['revision_number'] }} · Pengajuan {{ $attempt['number'] }} · {{ match ($attempt['state']) { 'approved' => 'Disetujui', 'returned' => 'Dikembalikan', 'submitted' => 'Diajukan', 'withdrawn_identity_changed' => 'Ditutup karena perubahan identitas', default => 'Pengajuan' } }}</h3>
            <p>Dikirim {{ $attempt['submitted_at'] }} UTC</p>
            <a href="{{ route('pkp.show', ['pkp_uuid' => $pkp->uuid, 'revision_uuid' => $attempt['revision_uuid'], 'submission_uuid' => $attempt['uuid']]) }}">Buka snapshot pengajuan {{ $attempt['number'] }}</a>
            @foreach ($history['decisions'] as $decision)
                @if ($decision['submission_uuid'] === $attempt['uuid'])
                    <p>Keputusan: {{ $decision['decision'] === 'approve' ? 'Disetujui' : 'Dikembalikan' }} · {{ $decision['decided_at'] }} UTC</p>
                    @if ($decision['reason'] !== null)<p>Alasan: {{ $decision['reason'] }}</p>@endif
                @endif
            @endforeach
            <details><summary>Identitas dan catatan saat pengajuan</summary>
                <dl>
                    <dt>Nama Pegawai</dt><dd>{{ $attempt['identity_snapshot']['employee']['name'] }}</dd>
                    <dt>Penilai saat pengajuan</dt><dd>{{ $attempt['assessor_snapshot']['name'] }}</dd>
                    <dt>Catatan</dt><dd>{{ $attempt['input_snapshot']['notes'] ?? 'Tidak ada catatan' }}</dd>
                </dl>
            </details>
        </article>
    @empty
        <p>Belum ada pengajuan yang dapat ditampilkan.</p>
    @endforelse
    @if ($history['documents'] !== [])
        <h3 class="h5">Dokumen tersimpan</h3>
        <ul>
            @foreach ($history['documents'] as $document)
                <li><a href="{{ route('pkp.documents.download', [$pkp->uuid, $document['uuid']]) }}">{{ $document['original_name'] }}</a></li>
            @endforeach
        </ul>
    @endif
    @foreach ($history['document_reviews'] as $review)
        <p>Pemeriksaan dokumen: {{ $review['decision'] === 'accept' ? 'Diterima' : 'Dikembalikan' }} · {{ $review['basis'] === 'content_approval_reuse' ? 'Bersama persetujuan konten' : 'Pemeriksaan setelah persetujuan' }} · {{ $review['decided_at'] }} UTC</p>
        @if ($review['reason'] !== null)<p>Alasan: {{ $review['reason'] }}</p>@endif
    @endforeach
    @if ($history['events'] !== [])
        <details><summary>Jejak perubahan dan penugasan</summary>
            @foreach ($history['events'] as $event)
                <p>{{ match ($event['event_type']) {
                    'submitted' => 'PKP diajukan', 'content_approved' => 'Konten disetujui', 'content_returned' => 'Konten dikembalikan',
                    'assessor_reassigned' => 'Penilai konten dialihkan', 'inspector_reassigned' => 'Pemeriksa dokumen dialihkan',
                    'source_document_uploaded' => 'PDF sumber diunggah', 'signed_final_uploaded' => 'Dokumen final diunggah',
                    'signed_final_accepted' => 'Dokumen final diterima', 'signed_final_returned' => 'Dokumen final dikembalikan',
                    'correction_created' => 'Revisi koreksi dibuat', 'identity_refreshed' => 'Identitas disesuaikan', default => 'Perubahan PKP'
                } }} · {{ $event['occurred_at'] }} UTC</p>
                @if (isset($event['after']['reason']))<p>Alasan: {{ $event['after']['reason'] }}</p>@endif
            @endforeach
        </details>
    @endif
</section>
