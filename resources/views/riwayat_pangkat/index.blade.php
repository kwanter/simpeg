<x-app-layout>
    @if (Auth::user()->hasRole('super-admin') || Auth::user()->hasRole('admin') || Auth::user()->hasRole('pimpinan') || Auth::user()->hasRole('verifikator'))
    <div class="container mt-5">
        <a href="{{ url('jabatan') }}" class="btn btn-primary mx-1">Jabatan</a>
        <a href="{{ url('pegawai') }}" class="btn btn-warning mx-1">Data Pegawai</a>
    </div>
    @endif
    <div class="container mt-2">
        <div class="row">
            <div class="col-md-12">

                @if (session('success'))
                    <div class="alert alert-success">{{ session('success') }}</div>
                @endif

                @if (session('error'))
                    <div class="alert alert-danger">{{ session('error') }}</div>
                @endif

                @if ($errors->any())
                    <div class="alert alert-danger">
                        <ul>
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="card mt-3">
                    <div class="card-header">
                        <h4>Riwayat Pangkat
                            <a href="{{ url('pegawai') }}" class="btn btn-danger float-end ms-2">Kembali</a>
                            @can('create riwayat pangkat')
                                <a href="{{ url('riwayat_pangkat/create/'.$pegawai->uuid) }}" class="btn btn-primary float-end">Tambah Riwayat Pangkat</a>
                            @endcan
                        </h4>
                    </div>
                    <div class="card-body">
                        <h2 class="fw-bold mb-4">Nama Pegawai : {{ $pegawai->nama }}</h2>

                        <table class="table table-bordered table-striped">
                            <thead>
                                <tr class="text-center">
                                    <th class="align-middle">No</th>
                                    <th class="align-middle">Pangkat</th>
                                    <th class="align-middle">Golongan</th>
                                    <th class="align-middle">TMT</th>
                                    <th class="align-middle">Nomor SK</th>
                                    <th class="align-middle">Tanggal SK</th>
                                    <th class="align-middle">Keterangan</th>
                                    <th class="align-middle">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($riwayatPangkat as $index => $riwayat)
                                    <tr class="text-center">
                                        <td class="align-middle">{{ ($riwayatPangkat->currentPage() - 1) * $riwayatPangkat->perPage() + $index + 1 }}</td>
                                        <td class="align-middle">{{ $riwayat->pangkat_display }}</td>
                                        <td class="align-middle">{{ $riwayat->golongan_display }}</td>
                                        <td class="align-middle">{{ $riwayat->tmt->format('d-m-Y') }}</td>
                                        <td class="align-middle">{{ $riwayat->nomor_sk }}</td>
                                        <td class="align-middle">{{ $riwayat->tanggal_sk->format('d-m-Y') }}</td>
                                        <td class="align-middle">{{ $riwayat->keterangan }}</td>
                                        <td class="align-middle">
                                            @can('update riwayat pangkat')
                                                <a href="{{ url('riwayat_pangkat/'.$riwayat->uuid.'/edit') }}" class="btn btn-sm btn-primary">Edit</a>
                                            @endcan
                                            @can('delete riwayat pangkat')
                                                <form action="{{ url('riwayat_pangkat/'.$riwayat->uuid) }}" method="POST" class="d-inline" onsubmit="return confirm('Yakin ingin menghapus data ini?')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-danger">Hapus</button>
                                                </form>
                                            @endcan
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="text-center">Tidak ada data riwayat pangkat</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
