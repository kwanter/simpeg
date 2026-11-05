@if ($canReassignContent && $assessorReassignmentOptions !== [])
    <section class="card mb-4" aria-labelledby="pkp-content-reassignment-heading">
        <div class="card-body">
            <h2 id="pkp-content-reassignment-heading" class="h4">Alihkan penilaian konten</h2>
            <form method="POST" action="{{ route('pkp.reassign-assessor', $pkp->uuid) }}">
                @csrf
                <input type="hidden" name="expected_version" value="{{ $pkp->version }}">
                <input type="hidden" name="revision_uuid" value="{{ $revision->uuid }}">
                <label for="assessor-pegawai-uuid" class="form-label">Pejabat Penilai pengganti</label>
                <select id="assessor-pegawai-uuid" name="assessor_pegawai_uuid" required class="form-select">
                    @foreach ($assessorReassignmentOptions as $option)
                        <option value="{{ $option['pegawai_uuid'] }}">{{ $option['name'] }} ({{ $option['nip'] }})</option>
                    @endforeach
                </select>
                @error('assessor_pegawai_uuid')<div class="text-danger">{{ $message }}</div>@enderror
                <label for="assessor-reassignment-reason" class="form-label mt-3">Alasan reassignment</label>
                <textarea id="assessor-reassignment-reason" name="reason" required maxlength="5000" class="form-control">{{ old('reason') }}</textarea>
                @error('reason')<div class="text-danger">{{ $message }}</div>@enderror
                <button type="submit" class="btn btn-warning mt-3">Alihkan Pejabat Penilai</button>
            </form>
        </div>
    </section>
@endif

@if ($canReassignInspector && $inspectorReassignmentOptions !== [])
    <section class="card mb-4" aria-labelledby="pkp-inspector-reassignment-heading">
        <div class="card-body">
            <h2 id="pkp-inspector-reassignment-heading" class="h4">Alihkan pemeriksaan dokumen</h2>
            <p>Alasan reassignment mencatat keterangan administrator bahwa pemeriksa saat ini tidak tersedia.</p>
            <form method="POST" action="{{ route('pkp.reassign-inspector', $pkp->uuid) }}">
                @csrf
                <input type="hidden" name="expected_version" value="{{ $pkp->version }}">
                <input type="hidden" name="revision_uuid" value="{{ $revision->uuid }}">
                <label for="inspector-pegawai-uuid" class="form-label">Pemeriksa dokumen pengganti</label>
                <select id="inspector-pegawai-uuid" name="inspector_pegawai_uuid" required class="form-select">
                    @foreach ($inspectorReassignmentOptions as $option)
                        <option value="{{ $option['pegawai_uuid'] }}">{{ $option['name'] }} ({{ $option['nip'] }})</option>
                    @endforeach
                </select>
                @error('inspector_pegawai_uuid')<div class="text-danger">{{ $message }}</div>@enderror
                <label for="inspector-reassignment-reason" class="form-label mt-3">Alasan reassignment</label>
                <textarea id="inspector-reassignment-reason" name="reason" required maxlength="5000" class="form-control">{{ old('reason') }}</textarea>
                @error('reason')<div class="text-danger">{{ $message }}</div>@enderror
                <button type="submit" class="btn btn-warning mt-3">Alihkan Pemeriksa Dokumen</button>
            </form>
        </div>
    </section>
@endif
