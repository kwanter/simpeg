{{-- Lampiran III PERMA No. 7 Tahun 2016 --}}
{{-- Formulir Izin Tidak Masuk Kerja --}}
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; font-size: 12pt; line-height: 1.6; margin: 2cm; }
        .header { text-align: center; margin-bottom: 30px; }
        .title { font-weight: bold; font-size: 14pt; text-decoration: underline; }
        .field-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .field-table td { padding: 6px 8px; vertical-align: top; }
        .field-table .label { width: 35%; font-weight: bold; }
        .field-table .colon { width: 5%; }
        .field-table .value { border-bottom: 1px solid #000; width: 60%; }
        .signature { margin-top: 50px; display: flex; justify-content: space-between; }
        .signature-box { text-align: center; width: 45%; }
        .signature-line { margin-top: 80px; border-top: 1px solid #000; padding-top: 5px; }
    </style>
</head>
<body>
    <div class="header">
        <p class="title">IZIN TIDAK MASUK KERJA</p>
        <p>Lampiran III Peraturan Mahkamah Agung Nomor 7 Tahun 2016</p>
        <p>Pasal 8 — Maksimal 2 (dua) hari kerja</p>
    </div>

    <table class="field-table">
        <tr><td class="label">Nama Pejabat Pemberi Izin</td><td class="colon">:</td><td class="value">{{ $izin->atasan_pimpinan->nama ?? '-' }}</td></tr>
        <tr><td class="label">Jabatan Pejabat Pemberi Izin</td><td class="colon">:</td><td class="value">{{ $izin->atasan_pimpinan->jabatan->nama_jabatan ?? '-' }}</td></tr>
        <tr><td class="label">Nama Pegawai</td><td class="colon">:</td><td class="value">{{ $izin->pegawai->nama ?? '-' }}</td></tr>
        <tr><td class="label">NIP</td><td class="colon">:</td><td class="value">{{ $izin->pegawai->nip ?? '-' }}</td></tr>
        <tr><td class="label">Tanggal-tanggal Izin Diberikan</td><td class="colon">:</td><td class="value">{{ \Carbon\Carbon::parse($izin->tanggal_mulai)->locale('id')->translatedFormat('d F Y') }} s.d. {{ \Carbon\Carbon::parse($izin->tanggal_selesai)->locale('id')->translatedFormat('d F Y') }} ({{ $izin->jumlah_hari ?? 0 }} hari kerja)</td></tr>
        <tr><td class="label">Alasan/Kepentingan</td><td class="colon">:</td><td class="value">{{ $izin->alasan ?? '-' }}</td></tr>
        <tr><td class="label">Kota Tempat Kedudukan Satuan Kerja</td><td class="colon">:</td><td class="value">{{ $izin->pegawai->kota_ttd ?? '-' }}</td></tr>
        <tr><td class="label">Tanggal Izin Ditandatangani</td><td class="colon">:</td><td class="value">{{ \Carbon\Carbon::parse($izin->created_at)->locale('id')->translatedFormat('d F Y') }}</td></tr>
    </table>

    <div class="signature">
        <div class="signature-box"><p>Pejabat Pemberi Izin</p><div class="signature-line">{{ $izin->atasan_pimpinan->nama ?? '-' }}</div></div>
        <div class="signature-box"><p>Yang Bersangkutan</p><div class="signature-line">{{ $izin->pegawai->nama ?? '-' }}</div></div>
    </div>
</body>
</html>
