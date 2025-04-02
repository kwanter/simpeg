<x-app-layout>
    <div class="container mt-5">
        <div class="row">
            <div class="col-md-12">
                @if (session('success'))
                    <div class="alert alert-success">{{ session('success') }}</div>
                @endif

                @if (session('error'))
                    <div class="alert alert-danger">{{ session('error') }}</div>
                @endif

                <div class="card">
                    <div class="card-header">
                        <h4>Data Hari Libur dan Cuti Bersama
                            @can('create hari libur')
                            <a href="{{ route('hari-libur.create') }}" class="btn btn-primary float-end">Tambah Hari Libur</a>
                            @endcan
                        </h4>
                    </div>
                    <div class="card-body">
                        <table class="table table-bordered table-striped">
                            <thead>
                                <tr class="text-center">
                                    <th>No</th>
                                    <th>Tanggal</th>
                                    <th>Nama</th>
                                    <th>Jenis</th>
                                    <th>Keterangan</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($hariLibur as $index => $item)
                                <tr>
                                    <td class="text-center">{{ ($hariLibur->currentPage() - 1) * $hariLibur->perPage() + $index + 1 }}</td>
                                    <td>{{ $item->tanggal->format('d-m-Y') }}</td>
                                    <td>{{ $item->nama }}</td>
                                    <td>{{ $item->jenis }}</td>
                                    <td>{{ $item->keterangan }}</td>
                                    <td class="text-center">
                                        @can('view hari libur')
                                        <a href="{{ route('hari-libur.show', $item->uuid) }}" class="btn btn-info btn-sm">Detail</a>
                                        @endcan

                                        @can('update hari libur')
                                        <a href="{{ route('hari-libur.edit', $item->uuid) }}" class="btn btn-primary btn-sm">Edit</a>
                                        @endcan

                                        @can('delete hari libur')
                                        <form action="{{ route('hari-libur.destroy', $item->uuid) }}" method="POST" class="d-inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Apakah Anda yakin ingin menghapus data ini?')">Hapus</button>
                                        </form>
                                        @endcan
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="6" class="text-center">Tidak ada data hari libur</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>

                        <div class="d-flex justify-content-center mt-3">
                            {{ $hariLibur->links() }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>