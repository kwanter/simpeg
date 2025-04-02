<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Halaman Tidak Ditemukan') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="card shadow">
                <div class="card-body text-center py-5">
                    <div class="mb-4">
                        <i class="fas fa-exclamation-triangle text-warning" style="font-size: 5rem;"></i>
                    </div>
                    <h3 class="text-xl font-semibold mb-3">Error 404 - Halaman Tidak Ditemukan</h3>
                    <p class="mb-4">Maaf, halaman yang Anda cari tidak ditemukan atau tidak tersedia.</p>
                    <div class="mt-5">
                        <a href="{{ route('dashboard') }}" class="btn btn-primary">
                            <i class="fas fa-home me-1"></i> Kembali ke Dashboard
                        </a>
                        <a href="javascript:history.back()" class="btn btn-secondary ms-2">
                            <i class="fas fa-arrow-left me-1"></i> Kembali ke Halaman Sebelumnya
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>