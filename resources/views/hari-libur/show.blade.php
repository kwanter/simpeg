<x-app-layout>
    <div class="container mt-5">
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h4>Detail Hari Libur
                            <a href="{{ route('hari-libur.index') }}" class="btn btn-danger float-end">Kembali</a>
                        </h4>
                    </div>
                    <div class="card-body">
                        <table class="table table-bordered">
                            <tr>
                                <th width="200px">Tanggal</th>
                                <td>{{ $hariLibur->tanggal->format('d-m-Y') }}</td>
                            </tr>
                            <tr>
                                <th>Nama</th>
                                <td>{{ $hariLibur->nama }}</td>
                            </tr>
                            <tr>
                                <th>Jenis</th>
                                <td>{{ $hariLibur->jenis }}</td>
                            </tr>
                            <tr>
                                <th>Keterangan</th>
                                <td>{{ $hariLibur->keterangan ?? '-' }}</td>
                            </tr>
                        </table>

                        <div class="mt-3">
                            @can('update hari libur')
                            <a href="{{ route('hari-libur.edit', $hariLibur->uuid) }}" class="btn btn-primary">Edit</a>
                            @endcan

                            @can('delete hari libur')
                            <form action="{{ route('hari-libur.destroy', $hariLibur->uuid) }}" method="POST" class="d-inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger" onclick="return confirm('Apakah Anda yakin ingin menghapus data ini?')">Hapus</button>
                            </form>
                            @endcan
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>