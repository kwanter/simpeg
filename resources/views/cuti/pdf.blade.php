<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Surat Cuti - {{ $cuti->no_surat_cuti }}</title>
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
        <h2>SURAT IZIN CUTI</h2>
        <p>Nomor: {{ $cuti->no_surat_cuti }}</p>
    </div>

    <div class="content">
        <p>Yang bertanda tangan di bawah ini:</p>

        <table class="data">
            <tr>
                <td>Nama</td>
                <td>: {{ $cuti->pimpinan->nama ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td>NIP</td>
                <td>: {{ $cuti->pimpinan->nip ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td>Jabatan</td>
                <td>: {{ $cuti->pimpinan->jabatan ?? 'N/A' }}</td>
            </tr>
        </table>

        <p>Dengan ini memberikan izin cuti kepada:</p>

        <table class="data">
            <tr>
                <td>Nama</td>
                <td>: {{ $cuti->pegawai->nama ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td>NIP</td>
                <td>: {{ $cuti->pegawai->nip ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td>Jabatan</td>
                <td>: {{ $cuti->pegawai->jabatan ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td>Jenis Cuti</td>
                <td>: {{ $cuti->jenis_cuti }}</td>
            </tr>
            <tr>
                <td>Lama Cuti</td>
                <td>: {{ $cuti->lama_cuti }} hari</td>
            </tr>
            <tr>
                <td>Tanggal Cuti</td>
                <td>: {{ $cuti->getIndonesianDate($cuti->tanggal_mulai) }} s/d {{ $cuti->getIndonesianDate($cuti->tanggal_selesai) }}</td>
            </tr>
            <tr>
                <td>Alasan Cuti</td>
                <td>: {{ $cuti->alasan }}</td>
            </tr>
            <tr>
                <td>Alamat Selama Cuti</td>
                <td>: {{ $cuti->alamat_selama_cuti }}</td>
            </tr>
        </table>
    </div>

    <div class="signature">
        <div class="signature-content">
            <p>{{ $cuti->pimpinan->tempat_tugas ?? 'Jakarta' }}, {{ $cuti->getApprovalDate() }}</p>
            <p>{{ $cuti->pimpinan->jabatan ?? 'Pimpinan' }}</p>

            <div class="signature-space"></div>

            <p><strong>{{ $cuti->pimpinan->nama ?? 'N/A' }}</strong></p>
            <p>NIP. {{ $cuti->pimpinan->nip ?? 'N/A' }}</p>
        </div>
    </div>
</body>
</html>
