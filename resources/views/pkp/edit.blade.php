<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $readOnly ? 'PKP Bulanan' : 'Ubah draf PKP' }}</h2>
    </x-slot>

    <div class="container py-4" role="region" aria-label="Konten revisi PKP" @if (! $readOnly) x-data="pkpActivityEditor()" @endif>
        <h1 class="h3">{{ $readOnly ? 'PKP Bulanan' : 'Ubah draf PKP' }}</h1>
        <p>Periode {{ $pkp->month }}/{{ $pkp->year }} · Revisi {{ $revision->number }}</p>
        @if (isset($historicalView) && $historicalView)
            <p class="alert alert-info">Snapshot pengajuan historis · baca saja.</p>
        @endif

        @if (isset($officialData) || isset($pendingData))
            <nav class="mb-3" aria-label="Konteks revisi PKP">
                @if ($officialData !== null)
                    <section class="border rounded p-3 mb-2" aria-label="PKP resmi">
                        <h2 class="h5">PKP resmi · Revisi {{ $officialData['revision_number'] }}</h2>
                        <p>{{ match ($officialData['completeness']) { 'complete' => 'Dokumen resmi lengkap', 'awaiting_inspection' => 'Dokumen resmi menunggu pemeriksaan', default => 'Dokumen resmi menunggu unggahan bertanda tangan' } }}</p>
                        <a href="{{ route('pkp.show', ['pkp_uuid' => $pkp->uuid, 'revision_uuid' => $officialData['revision_uuid']]) }}">Buka PKP resmi</a>
                        @if ($officialData['capabilities']['print'] && (! $canPrint || $officialData['revision_uuid'] !== $revision->uuid))
                            <a class="ms-3" href="{{ route('pkp.revisions.pdf', [$pkp->uuid, $officialData['revision_uuid']]) }}" target="_blank" rel="noopener">Cetak PKP resmi</a>
                        @endif
                        @if ($officialData['documents']['final'] !== null)
                            <a class="ms-3" href="{{ route('pkp.documents.download', [$pkp->uuid, $officialData['documents']['final']['uuid']]) }}">Unduh final PKP resmi</a>
                        @endif
                    </section>
                @endif
                @if ($pendingData !== null)
                    <section class="border rounded p-3" aria-label="Revisi pending">
                        <h2 class="h5">Revisi pending · Revisi {{ $pendingData['revision_number'] }}</h2>
                        <p>{{ $pendingData['completeness'] === 'awaiting_content_approval' ? 'Menunggu penilaian konten' : 'Draf belum disetujui' }}</p>
                        <a href="{{ route('pkp.show', ['pkp_uuid' => $pkp->uuid, 'revision_uuid' => $pendingData['revision_uuid']]) }}">Buka revisi pending</a>
                    </section>
                @endif
            </nav>
        @endif

        @if ($identitySnapshot !== null)
            <dl class="row">
                <dt class="col-sm-3">Nama Pegawai</dt><dd class="col-sm-9">{{ $identitySnapshot['employee']['name'] }}</dd>
                <dt class="col-sm-3">NIP</dt><dd class="col-sm-9">{{ $identitySnapshot['employee']['nip'] }}</dd>
                <dt class="col-sm-3">Pangkat/Golongan</dt><dd class="col-sm-9">{{ $identitySnapshot['rank']['value'] }}</dd>
                <dt class="col-sm-3">Jabatan</dt><dd class="col-sm-9">{{ $identitySnapshot['position']['value'] }}</dd>
                <dt class="col-sm-3">Satuan Kerja</dt><dd class="col-sm-9">{{ $identitySnapshot['work_unit']['value'] }}</dd>
            </dl>
        @endif

        @if ($canPrint)
            <a class="btn btn-outline-primary mb-3" href="{{ route('pkp.revisions.pdf', [$pkp->uuid, $revision->uuid]) }}" target="_blank" rel="noopener">Cetak PKP</a>
        @endif

        @if ($canRevise)
            <section class="border rounded p-3 mb-3" aria-labelledby="pkp-correction-heading">
                <h2 id="pkp-correction-heading" class="h5">Koreksi PKP yang disetujui</h2>
                <p>Revisi baru memerlukan penilaian ulang. PKP yang telah disetujui tetap resmi sampai koreksi disetujui.</p>
                <form method="POST" action="{{ route('pkp.revisions.store', $pkp->uuid) }}">
                    @csrf
                    <input type="hidden" name="expected_version" value="{{ $pkp->version }}">
                    <input type="hidden" name="base_revision_uuid" value="{{ $revision->uuid }}">
                    <label for="pkp-correction-source" class="form-label">Cara membuat revisi</label>
                    <select id="pkp-correction-source" name="source" class="form-select mb-2">
                        <option value="application" @selected($revision->source === 'application')>Buat di aplikasi</option>
                        <option value="external" @selected($revision->source === 'external')>Unggah dokumen dari luar aplikasi</option>
                    </select>
                    @error('source')<div class="text-danger">{{ $message }}</div>@enderror
                    <button type="submit" class="btn btn-outline-primary">Buat revisi koreksi</button>
                </form>
            </section>
        @endif

        @if (! $readOnly)
            <div class="mb-3">
                <label for="pkp-assessor" class="form-label">Pejabat Penilai</label>
                <select id="pkp-assessor" class="form-select mb-3" x-model="assessorUuid">
                    <option value="" disabled>Pilih Pejabat Penilai</option>
                    @foreach ($assessorOptions as $option)
                        <option value="{{ $option['pegawai_uuid'] }}">{{ $option['name'] }} ({{ $option['nip'] }})</option>
                    @endforeach
                </select>
                <label for="pkp-notes" class="form-label">Catatan</label>
                <textarea id="pkp-notes" class="form-control" maxlength="5000" x-model="notes"></textarea>
            </div>
            <ul class="text-danger" aria-live="polite">
                <template x-for="(error, index) in errors" :key="index"><li x-text="error"></li></template>
            </ul>
            <p role="status" aria-live="polite" x-text="status"></p>
        @elseif ($notes !== null)
            <p><strong>Catatan:</strong> {{ $notes }}</p>
        @endif

        @include('pkp.partials.scores', ['scoreData' => $scoreData])

        @include('pkp.partials.documents', ['documentRows' => $documentRows, 'canUpload' => $canUpload, 'canUploadSource' => $canUploadSource])
        @include('pkp.partials.document-review', ['canDocumentDecide' => $canDocumentDecide, 'documentRows' => $documentRows])
        @include('pkp.partials.reassignment', [
            'canReassignContent' => $canReassignContent,
            'canReassignInspector' => $canReassignInspector,
            'assessorReassignmentOptions' => $assessorReassignmentOptions,
            'inspectorReassignmentOptions' => $inspectorReassignmentOptions,
            'pkp' => $pkp,
            'revision' => $revision,
        ])

        @if (session('status') || $saved)
            <p class="alert alert-success" role="status">{{ session('status') ?? 'Perubahan tersimpan.' }}</p>
        @endif

        @if ($canDecide)
            <p class="alert alert-info">Penilaian ini telah dikirim oleh Pegawai dan menunggu keputusan Pejabat Penilai.</p>
            <form method="POST" action="{{ route('pkp.decision', $pkp->uuid) }}" class="mb-3">
                @csrf
                <input type="hidden" name="expected_version" value="{{ $pkp->version }}">
                <input type="hidden" name="revision_uuid" value="{{ $revision->uuid }}">
                <input type="hidden" name="submission_uuid" value="{{ $currentSubmission->uuid }}">
                <input type="hidden" name="decision" value="approve">
                <button type="submit" class="btn btn-success">Setujui PKP</button>
            </form>
            <form method="POST" action="{{ route('pkp.decision', $pkp->uuid) }}">
                @csrf
                <input type="hidden" name="expected_version" value="{{ $pkp->version }}">
                <input type="hidden" name="revision_uuid" value="{{ $revision->uuid }}">
                <input type="hidden" name="submission_uuid" value="{{ $currentSubmission->uuid }}">
                <input type="hidden" name="decision" value="return">
                <label for="decision-reason" class="form-label">Alasan pengembalian</label>
                <textarea id="decision-reason" name="reason" required maxlength="5000" class="form-control"></textarea>
                @error('reason')<div class="text-danger">{{ $message }}</div>@enderror
                <button type="submit" class="btn btn-warning mt-2">Kembalikan untuk perbaikan</button>
            </form>
        @elseif ($canRefreshIdentity)
            <p class="alert alert-info">Jika riwayat pangkat, jabatan, atau satuan kerja berubah untuk periode ini, refresh identitas akan menutup attempt lama dan mengembalikan draf untuk dikirim ulang.</p>
            <form method="POST" action="{{ route('pkp.refresh-identity', $pkp->uuid) }}">
                @csrf
                <input type="hidden" name="expected_version" value="{{ $pkp->version }}">
                <input type="hidden" name="revision_uuid" value="{{ $revision->uuid }}">
                <label for="identity-refresh-reason" class="form-label">Alasan refresh identitas</label>
                <textarea id="identity-refresh-reason" name="reason" required maxlength="5000" class="form-control"></textarea>
                @error('reason')<div class="text-danger">{{ $message }}</div>@enderror
                <button type="submit" class="btn btn-warning mt-2">Refresh identitas dan kembalikan draf</button>
            </form>
        @elseif ($revision->source === 'external')
            <p class="alert alert-info">PKP eksternal menggunakan dokumen PDF dan tidak memiliki aktivitas terstruktur.</p>
            @if (! $readOnly)
                <button type="button" class="btn btn-primary" @click="save">Simpan draf</button>
            @endif
            @if ($canSubmit)
                <form method="POST" action="{{ route('pkp.submit', $pkp->uuid) }}" class="mt-3">
                    @csrf
                    <input type="hidden" name="expected_version" value="{{ $pkp->version }}">
                    <input type="hidden" name="revision_uuid" value="{{ $revision->uuid }}">
                    <button type="submit" class="btn btn-success">Ajukan ke Pejabat Penilai</button>
                </form>
            @endif
        @elseif ($readOnly)
            <p class="alert alert-secondary">Konten PKP ini sudah dikirim untuk penilaian dan tidak dapat diubah.</p>
        @else
            <div class="mb-3">
                <button type="button" class="btn btn-outline-primary" @click="addIndicator" :disabled="indicators.length >= 50">Tambah indikator</button>
            </div>

            <template x-for="(indicator, indicatorIndex) in indicators" :key="indicator.key">
                <fieldset class="card mb-4">
                    <legend class="card-header fs-5">
                        <span x-text="'Indikator ' + (indicatorIndex + 1)"></span>
                        <button type="button" class="btn btn-sm btn-outline-secondary" :disabled="indicatorIndex === 0" :aria-label="'Pindahkan indikator ' + (indicatorIndex + 1) + ' ke atas'" @click="moveIndicator(indicatorIndex, -1)">↑</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" :disabled="indicatorIndex === indicators.length - 1" :aria-label="'Pindahkan indikator ' + (indicatorIndex + 1) + ' ke bawah'" @click="moveIndicator(indicatorIndex, 1)">↓</button>
                        <button type="button" class="btn btn-sm btn-outline-danger" :aria-label="'Hapus indikator ' + (indicatorIndex + 1)" @click="indicators.splice(indicatorIndex, 1)">Hapus indikator</button>
                    </legend>
                    <div class="card-body">
                        <label class="form-label" :for="'indicator-title-' + indicatorIndex">Judul indikator</label>
                        <input :id="'indicator-title-' + indicatorIndex" class="form-control mb-3" maxlength="500" x-model="indicator.title">

                        <button type="button" class="btn btn-sm btn-outline-primary mb-3" @click="addActivity(indicatorIndex)" :disabled="activityCount >= 500">Tambah aktivitas</button>

                        <template x-for="(activity, activityIndex) in indicator.activities" :key="activity.key">
                            <div class="border rounded p-3 mb-3">
                                <div class="d-flex justify-content-between mb-2">
                                    <strong x-text="'Aktivitas ' + (activityIndex + 1)"></strong>
                                    <div>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" :disabled="activityIndex === 0" :aria-label="'Pindahkan aktivitas ' + (activityIndex + 1) + ' ke atas'" @click="moveActivity(indicatorIndex, activityIndex, -1)">↑</button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" :disabled="activityIndex === indicator.activities.length - 1" :aria-label="'Pindahkan aktivitas ' + (activityIndex + 1) + ' ke bawah'" @click="moveActivity(indicatorIndex, activityIndex, 1)">↓</button>
                                        <button type="button" class="btn btn-sm btn-outline-danger" :aria-label="'Hapus aktivitas ' + (activityIndex + 1)" @click="indicator.activities.splice(activityIndex, 1)">Hapus aktivitas</button>
                                    </div>
                                </div>

                                <label class="form-label" :for="'activity-description-' + indicatorIndex + '-' + activityIndex">Uraian aktivitas</label>
                                <textarea :id="'activity-description-' + indicatorIndex + '-' + activityIndex" class="form-control mb-2" maxlength="2000" x-model="activity.description"></textarea>

                                <label class="form-label" :for="'activity-unit-' + indicatorIndex + '-' + activityIndex">Satuan target dan realisasi</label>
                                <input :id="'activity-unit-' + indicatorIndex + '-' + activityIndex" class="form-control mb-2" maxlength="100" x-model="activity.unit">

                                <label class="form-label" :for="'activity-credit-' + indicatorIndex + '-' + activityIndex">Target kredit</label>
                                <input :id="'activity-credit-' + indicatorIndex + '-' + activityIndex" class="form-control mb-2" inputmode="decimal" x-model="activity.target_credit">

                                <label class="form-label" :for="'activity-target-' + indicatorIndex + '-' + activityIndex">Target kuantitas</label>
                                <input :id="'activity-target-' + indicatorIndex + '-' + activityIndex" class="form-control mb-2" inputmode="decimal" x-model="activity.target_quantity">

                                <label class="form-label" :for="'activity-quality-' + indicatorIndex + '-' + activityIndex">Target kualitas</label>
                                <input :id="'activity-quality-' + indicatorIndex + '-' + activityIndex" class="form-control mb-2" inputmode="decimal" x-model="activity.target_quality">

                                <label class="form-label" :for="'activity-realized-' + indicatorIndex + '-' + activityIndex">Realisasi kuantitas</label>
                                <input :id="'activity-realized-' + indicatorIndex + '-' + activityIndex" class="form-control" inputmode="decimal" x-model="activity.realized_quantity">
                            </div>
                        </template>
                    </div>
                </fieldset>
            </template>

            <button type="button" class="btn btn-primary" @click="save">Simpan draf</button>
            @if ($canSubmit)
                <form method="POST" action="{{ route('pkp.submit', $pkp->uuid) }}" class="d-inline ms-2">
                @csrf
                <input type="hidden" name="expected_version" value="{{ $pkp->version }}">
                <input type="hidden" name="revision_uuid" value="{{ $revision->uuid }}">
                <button type="submit" class="btn btn-success">Ajukan ke Pejabat Penilai</button>
                @error('period')<div class="text-danger">{{ $message }}</div>@enderror
                @error('assessor_pegawai_uuid')<div class="text-danger">{{ $message }}</div>@enderror
                @error('indicators')<div class="text-danger">{{ $message }}</div>@enderror
                </form>
            @endif
        @endif
    </div>

    @if (isset($history))
        @include('pkp.partials.history', ['history' => $history])
    @endif

    @if (! $readOnly)
        <script>
            function pkpActivityEditor() {
                return {
                    expectedVersion: @js($pkp->version),
                    source: @js($revision->source),
                    notes: @js($notes ?? ''),
                    assessorUuid: @js($assessorUuid),
                    indicators: @js($indicators).map(indicator => ({
                        ...indicator,
                        key: crypto.randomUUID(),
                        activities: indicator.activities.map(activity => ({ ...activity, key: crypto.randomUUID() })),
                    })),
                    errors: [],
                    status: '',
                    get activityCount() {
                        return this.indicators.reduce((total, indicator) => total + indicator.activities.length, 0);
                    },
                    addIndicator() {
                        if (this.indicators.length < 50) {
                            this.indicators.push({ key: crypto.randomUUID(), title: '', activities: [] });
                        }
                    },
                    addActivity(indicatorIndex) {
                        if (this.activityCount < 500) {
                            this.indicators[indicatorIndex].activities.push({
                                key: crypto.randomUUID(),
                                description: null,
                                unit: null,
                                target_credit: null,
                                target_quantity: null,
                                target_quality: null,
                                realized_quantity: null,
                            });
                        }
                    },
                    moveIndicator(index, offset) {
                        const target = index + offset;
                        if (target >= 0 && target < this.indicators.length) {
                            this.indicators.splice(target, 0, this.indicators.splice(index, 1)[0]);
                        }
                    },
                    moveActivity(indicatorIndex, index, offset) {
                        const activities = this.indicators[indicatorIndex].activities;
                        const target = index + offset;
                        if (target >= 0 && target < activities.length) {
                            activities.splice(target, 0, activities.splice(index, 1)[0]);
                        }
                    },
                    async save() {
                        this.errors = [];
                        this.status = '';
                        try {
                            const draft = { expected_version: this.expectedVersion, notes: this.notes };
                            if (this.assessorUuid) draft.assessor_pegawai_uuid = this.assessorUuid;
                            if (this.source === 'application') {
                                draft.indicators = this.indicators.map(({ key, ...indicator }) => ({
                                    ...indicator,
                                    activities: indicator.activities.map(({ key: activityKey, ...activity }) => activity),
                                }));
                            }
                            const response = await window.axios.put(@js(route('pkp.revisions.update', [$pkp->uuid, $revision->uuid])), draft, {
                                headers: {
                                    Accept: 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                },
                            });
                            this.expectedVersion = response.data.data.version;
                            this.status = 'Perubahan tersimpan.';
                            window.location.assign(response.data.data.redirect_url);
                        } catch (error) {
                            const fields = error.response?.data?.errors ?? {};
                            this.errors = Object.values(fields).flat();
                            if (this.errors.length === 0) {
                                this.errors = ['Draf tidak tersimpan. Muat ulang untuk melihat versi terbaru.'];
                            }
                        }
                    },
                };
            }
        </script>
    @endif
</x-app-layout>
