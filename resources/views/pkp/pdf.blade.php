<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>PKP {{ $pkp->year }}-{{ $pkp->month }}</title>
    <style>
        @page { size: A4 landscape; margin: 16mm 14mm; }
        body { color: #111827; font-family: DejaVu Sans, sans-serif; font-size: 9pt; }
        h1, h2, h3 { margin: 0 0 8px; }
        h1 { text-align: center; font-size: 15pt; }
        h2 { font-size: 11pt; }
        .identity, .signature { margin: 14px 0; }
        .identity td { padding: 2px 10px 2px 0; }
        table.activities { border-collapse: collapse; width: 100%; table-layout: fixed; }
        .activities th, .activities td { border: 1px solid #475569; padding: 5px; vertical-align: top; overflow-wrap: break-word; word-wrap: break-word; }
        .activities thead { display: table-header-group; }
        .activities tr { page-break-inside: avoid; }
        .notes { word-wrap: break-word; overflow-wrap: break-word; }
        .number { text-align: right; }
        .predicate { font-size: 11pt; font-weight: bold; }
        .signature { page-break-inside: avoid; margin-top: 26px; width: 42%; margin-left: auto; }
        .signature-space { height: 54px; }
        .appendix-heading { page-break-before: always; }
        .exact { font-family: DejaVu Sans Mono, monospace; font-size: 8pt; }
    </style>
</head>
<body>
    <h1>Penilaian Kinerja Pegawai</h1>
    <h2>Periode {{ sprintf('%02d', $pkp->month) }}/{{ $pkp->year }}</h2>

    <table class="identity">
        <tr><td>Nama Pegawai</td><td>: {{ $identity['employee']['name'] }}</td></tr>
        <tr><td>NIP</td><td>: {{ $identity['employee']['nip'] }}</td></tr>
        <tr><td>Pangkat/Golongan</td><td>: {{ $identity['rank']['value'] }}</td></tr>
        <tr><td>Jabatan</td><td>: {{ $identity['position']['value'] }}</td></tr>
        <tr><td>Satuan Kerja</td><td>: {{ $identity['work_unit']['value'] }}</td></tr>
    </table>

    <table class="activities">
        <thead>
            <tr>
                <th style="width: 12%">Indikator</th>
                <th style="width: 4%">No.</th>
                <th style="width: 24%">Uraian</th>
                <th style="width: 8%">Satuan</th>
                <th style="width: 8%">Target Kredit</th>
                <th style="width: 9%">Target Kuantitas</th>
                <th style="width: 7%">Target Kualitas</th>
                <th style="width: 9%">Realisasi Kuantitas</th>
                <th style="width: 8%">Realisasi Kredit</th>
                <th style="width: 11%">Nilai Aktivitas</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($input['indicators'] as $indicatorIndex => $indicator)
                @foreach ($score['indicators'][$indicatorIndex]['activities'] as $activityIndex => $activity)
                    @php
                        $inputActivity = $indicator['activities'][$activityIndex];
                        $descriptionParts = mb_str_split($inputActivity['description'], 250);
                        $titleParts = mb_str_split($indicator['title'], 125);
                    @endphp
                    @for ($partIndex = 0; $partIndex < max(count($descriptionParts), count($titleParts)); $partIndex++)
                    <tr>
                        <td>{{ $titleParts[$partIndex] ?? '' }}</td>
                        <td>{{ $activityIndex + 1 }}{{ $partIndex > 0 ? ' (lanjutan)' : '' }}</td>
                        <td>{{ $descriptionParts[$partIndex] ?? '' }}</td>
                        <td>{{ $partIndex === 0 ? $inputActivity['unit'] : '' }}</td>
                        <td class="number">{{ $partIndex === 0 ? $inputActivity['target_credit'] : '' }}</td>
                        <td class="number">{{ $partIndex === 0 ? $inputActivity['target_quantity'] : '' }}</td>
                        <td class="number">{{ $partIndex === 0 ? $inputActivity['target_quality'] : '' }}</td>
                        <td class="number">{{ $partIndex === 0 ? $inputActivity['realized_quantity'] : '' }}</td>
                        <td class="number">{{ $partIndex === 0 ? $activity['realized_credit']['display'] : '' }}</td>
                        <td class="number">{{ $partIndex === 0 ? $activity['achievement']['display'] : '' }}</td>
                    </tr>
                    @endfor
                @endforeach
                <tr>
                    <th colspan="9" class="number">Nilai Indikator: {{ $score['indicators'][$indicatorIndex]['score']['display'] }}</th>
                    <td class="number">{{ $score['indicators'][$indicatorIndex]['score']['display'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <p class="predicate">Nilai Keseluruhan: {{ $score['overall_score']['display'] }} · Predikat: {{ $score['predicate'] }}</p>
    <p class="exact">Nilai eksak: {{ $score['overall_score']['numerator'] }}/{{ $score['overall_score']['denominator'] }} · Versi kalkulasi: {{ $score['version'] }}</p>

    @if (filled($input['notes'] ?? null))
        <h2>Catatan</h2>
        <p class="notes">{!! nl2br(e($input['notes'])) !!}</p>
    @endif

    <h2 class="appendix-heading">Lampiran perhitungan eksak</h2>
    <table class="activities exact">
        <thead>
            <tr><th>Indikator / Aktivitas</th><th>Nilai rasional eksak</th><th>Realisasi kredit eksak</th></tr>
        </thead>
        <tbody>
            @foreach ($score['indicators'] as $indicator)
                <tr><th>{{ $indicator['title'] }} — Nilai indikator</th><td>{{ $indicator['score']['numerator'] }}/{{ $indicator['score']['denominator'] }}</td><td></td></tr>
                @foreach ($indicator['activities'] as $activity)
                    <tr>
                        <td>{{ $activity['description'] }}</td>
                        <td>{{ $activity['achievement']['numerator'] }}/{{ $activity['achievement']['denominator'] }}</td>
                        <td>{{ $activity['realized_credit']['numerator'] }}/{{ $activity['realized_credit']['denominator'] }}</td>
                    </tr>
                @endforeach
            @endforeach
            <tr><th>Nilai keseluruhan</th><td>{{ $score['overall_score']['numerator'] }}/{{ $score['overall_score']['denominator'] }}</td><td></td></tr>
        </tbody>
    </table>

    <table class="signature">
        <tr><td>Pejabat Penilai</td></tr>
        <tr><td>{{ $assessor['name'] }} ({{ $assessor['nip'] }})</td></tr>
        <tr><td class="signature-space"></td></tr>
        <tr><td>Revisi {{ $revision->number }}</td></tr>
    </table>
</body>
</html>
