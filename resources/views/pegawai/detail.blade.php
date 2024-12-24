<x-app-layout>
    <div class="container mt-5">
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h4>Detail Pegawai
                            <a href="{{ url('pegawai') }}" class="btn btn-primary float-end">Kembali</a>
                        </h4>
                    </div>
                    <div class="card-body">
                        <table class="table table-bordered">
                            <tr>
                                <th width="200px">NIP</th>
                                <td>{{ $pegawai->nip }}</td>
                            </tr>
                            <tr>
                                <th>Nama</th>
                                <td>{{ $pegawai->nama }}</td>
                            </tr>
                            <tr>
                                <th>Tempat Lahir</th>
                                <td>{{ $pegawai->tempat_lahir }}</td>
                            </tr>
                            <tr>
                                <th>Tanggal Lahir</th>
                                <td>{{ $pegawai->tanggal_lahir }}</td>
                            </tr>
                            <tr>
                                <th>Jenis Kelamin</th>
                                <td>{{ $pegawai->jenis_kelamin }}</td>
                            </tr>
                            <tr>
                                <th>Alamat</th>
                                <td>{{ $pegawai->alamat }}</td>
                            </tr>
                            <tr>
                                <th>No. Telepon</th>
                                <td>{{ $pegawai->no_hp }}</td>
                            </tr>
                            <tr>
                                <th>Agama</th>
                                <td>{{ $pegawai->agama }}</td>
                            </tr>
                            <tr>
                                <th>Email</th>
                                <td>{{ $user }}</td>
                            </tr>
                            <tr>
                                <th>Jabatan</th>
                                <td>{{ $pegawai->jabatan }}</td>
                            </tr>
                            <tr>
                                <th>Status Pegawai</th>
                                <td>
                                    @if($pegawai->status_pegawai == "CPNS")
                                        <span class="badge bg-info">CPNS (Calon Pegawai Negeri Sipil)</span>
                                    @elseif($pegawai->status_pegawai == "Hakim")
                                        <span class="badge bg-info">Hakim</span>
                                    @elseif($pegawai->status_pegawai == "PNS")
                                        <span class="badge bg-info">PNS (Pegawai Negeri Sipil)</span>
                                    @elseif($pegawai->status_pegawai == "PPPK")
                                        <span class="badge bg-info">PPPK (Pegawai Pemerintah Dengan Perjanjian Kerja)</span>
                                    @else
                                        <span class="badge bg-info">PPNPN (Pegawai Pemerintah Non Pegawai Negeri)</span>
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <th>Foto</th>
                                <td>
                                    <img src="{{ asset('storage/pic/pegawai/' . $pegawai->foto) }}" alt="Foto Pegawai" class="img-fluid">
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>