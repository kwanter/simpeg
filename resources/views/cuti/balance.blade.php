<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Saldo Cuti Tahunan') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="card shadow">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">Informasi Saldo Cuti Tahunan {{ date('Y') }}</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <td width="40%">Nama</td>
                                    <td>: {{ $pegawai->nama }}</td>
                                </tr>
                                <tr>
                                    <td>NIP</td>
                                    <td>: {{ $pegawai->nip }}</td>
                                </tr>
                                <tr>
                                    <td>Jabatan</td>
                                    <td>: {{ $pegawai->jabatan }}</td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <h5 class="card-title">Saldo Cuti Tahunan</h5>
                                    <div class="row mt-3">
                                        <div class="col-md-4">
                                            <div class="card bg-primary text-white">
                                                <div class="card-body">
                                                    <h3 class="display-4">{{ $balance->total_days }}</h3>
                                                    <p>Jatah Tahunan</p>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="card bg-info text-white">
                                                <div class="card-body">
                                                    <h3 class="display-4">{{ $balance->carried_over }}</h3>
                                                    <p>Sisa Tahun Lalu</p>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="card bg-success text-white">
                                                <div class="card-body">
                                                    <h3 class="display-4">{{ $balance->remaining_days }}</h3>
                                                    <p>Sisa Cuti</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mt-3">
                                        <p>Total cuti yang sudah digunakan: <strong>{{ $balance->used_days }} hari</strong></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4">
                        <a href="{{ route('cuti.update-balance') }}" class="btn btn-info">
                            <i class="fas fa-sync-alt me-1"></i> Perbarui Saldo Cuti
                        </a>
                        <a href="{{ route('cuti.create') }}" class="btn btn-primary ms-2">
                            <i class="fas fa-plus-circle me-1"></i> Ajukan Cuti Baru
                        </a>
                        <a href="{{ route('cuti.index') }}" class="btn btn-secondary ms-2">
                            <i class="fas fa-list me-1"></i> Daftar Pengajuan Cuti
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>