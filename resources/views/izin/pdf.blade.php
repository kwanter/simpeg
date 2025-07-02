<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Surat Izin - {{ $izin->no_surat_izin }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12pt;
            line-height: 1.5;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
        }
        .header h2 {
            margin: 0;
            font-size: 16pt;
            text-transform: uppercase;
        }
        .header p {
            margin: 0;
        }
        .content {
            margin-bottom: 20px;
        }
        table.data {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        table.data td {
            padding: 5px;
            vertical-align: top;
        }
        table.data td:first-child {
            width: 30%;
        }
        .signature {
            margin-top: 40px;
            text-align: right;
        }
        .signature-content {
            display: inline-block;
            text-align: center;
            margin-right: 50px;
        }
        .signature-space {
            height: 60px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h2>SURAT IZIN</h2>
        <p>Nomor: {{ $izin->no_surat_izin }}</p>
    </div>

    <div class="content">
        <p>Yang bertanda tangan di bawah ini:</p>

        <table class="data">
            <tr>
                <td>Nama</td>
                <td>: {{ $izin->pimpinan->nama ?? $izin->atasan->nama ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td>NIP</td>
                <td>: {{ $izin->pimpinan->nip ?? $izin->atasan->nip ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td>Jabatan</td>
                <td>: {{ $izin->pimpinan->jabatan->nama_jabatan ?? $izin->atasan->jabatan->nama_jabatan ?? 'N/A' }}</td>
            </tr>
        </table>

        <p>Dengan ini memberikan izin kepada:</p>

        <table class="data">
            <tr>
                <td>Nama</td>
                <td>: {{ $izin->pegawai->nama ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td>NIP</td>
                <td>: {{ $izin->pegawai->nip ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td>Jabatan</td>
                <td>: {{ $izin->pegawai->jabatan->nama_jabatan ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td>Jenis Izin</td>
                <td>: {{ $izin->jenis_izin }}</td>
            </tr>
            <tr>
                <td>Tanggal</td>
                <td>: {{ \Carbon\Carbon::parse($izin->tanggal)->format('d F Y') }}</td>
            </tr>
            <tr>
                <td>Waktu</td>
                <td>: {{ \Carbon\Carbon::parse($izin->waktu_mulai)->format('H:i') }} - {{ \Carbon\Carbon::parse($izin->waktu_selesai)->format('H:i') }}</td>
            </tr>
            <tr>
                <td>Alasan</td>
                <td>: {{ $izin->alasan }}</td>
            </tr>
        </table>
    </div>

    <div class="signature">
        <div class="signature-content">
            <p>{{ $izin->pimpinan->tempat_tugas ?? $izin->atasan->tempat_tugas ?? 'Tanah Grogot' }}, {{ \Carbon\Carbon::now()->format('d F Y') }}</p>
            <p>{{ $izin->pimpinan->jabatan->nama_jabatan ?? $izin->atasan->jabatan->nama_jabatan ?? 'Pimpinan' }}</p>

            <div class="signature-space"></div>

            <p><strong>{{ $izin->pimpinan->nama ?? $izin->atasan->nama ?? 'N/A' }}</strong></p>
            <p>NIP. {{ $izin->pimpinan->nip ?? $izin->atasan->nip ?? 'N/A' }}</p>
        </div>
    </div>
</body>
</html>
