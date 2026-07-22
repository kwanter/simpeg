<?php

namespace App\Models;

use App\Traits\UserTrackingTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Venturecraft\Revisionable\RevisionableTrait;

class Cuti extends Model
{
    use HasFactory, RevisionableTrait, UserTrackingTrait;

    protected $table = 'cuti';

    protected $primaryKey = 'uuid';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $casts = [
        'tanggal_mulai' => 'date',
        'tanggal_selesai' => 'date',
        // 'lama_cuti' => 'integer',
        'tanggal_verifikasi' => 'datetime',
        'tanggal_verifikasi_pimpinan' => 'datetime',
        'tanggal_verifikasi_atasan_pimpinan' => 'datetime', // Add this line
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            $model->uuid = (string) \Illuminate\Support\Str::uuid();
        });
    }

    public function getRouteKeyName()
    {
        return 'uuid';
    }

    public function scopeFilter($query, array $filters)
    {
        $query->when($filters['search'] ?? false, function ($query, $search) {
            return $query->where('pegawai_uuid', 'like', '%'.$search.'%')
                ->orWhere('jenis_cuti', 'like', '%'.$search.'%')
                ->orWhere('status', 'like', '%'.$search.'%');
        });
    }

    // Request boundaries whitelist fields; approval metadata remains non-fillable.
    protected $fillable = [
        'uuid',
        'pegawai_uuid',
        'no_surat_cuti',
        'jenis_cuti',
        'tanggal_mulai',
        'tanggal_selesai',
        'lama_cuti',
        'alasan',
        'alamat_selama_cuti',
        'no_hp_selama_cuti',
        'dokumen',
        'status',
        'pimpinan_uuid',
        'atasan_pimpinan_uuid',
    ];

    public function pegawai()
    {
        return $this->belongsTo(Pegawai::class, 'pegawai_uuid', 'uuid');
    }

    public function verifikator()
    {
        return $this->belongsTo(Pegawai::class, 'verifikator_uuid', 'uuid');
    }

    public function pimpinan()
    {
        return $this->belongsTo(Pegawai::class, 'pimpinan_uuid', 'uuid');
    }

    // Add this relationship method to the Cuti model
    public function atasanPimpinan()
    {
        return $this->belongsTo(Pegawai::class, 'atasan_pimpinan_uuid', 'uuid');
    }

    // Add this method to generate PDF
    public function generatePdf()
    {
        $pdf = \PDF::loadView('cuti.pdf', ['cuti' => $this]);

        return $pdf;
    }

    // Helper method to get formatted dates
    public function getFormattedStartDate()
    {
        return $this->tanggal_mulai->format('d F Y');
    }

    public function getFormattedEndDate()
    {
        return $this->tanggal_selesai->format('d F Y');
    }

    // Helper method to get Indonesian date format
    public function getIndonesianDate($date)
    {
        $months = [
            'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
            'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
        ];

        return $date->format('d').' '.$months[$date->format('n') - 1].' '.$date->format('Y');
    }

    // Get approval date in Indonesian format
    public function getApprovalDate()
    {
        if ($this->tanggal_verifikasi_atasan_pimpinan) {
            return $this->getIndonesianDate($this->tanggal_verifikasi_atasan_pimpinan);
        } elseif ($this->tanggal_verifikasi_pimpinan) {
            return $this->getIndonesianDate($this->tanggal_verifikasi_pimpinan);
        } elseif ($this->tanggal_verifikasi) {
            return $this->getIndonesianDate($this->tanggal_verifikasi);
        }

        return date('d F Y');
    }
}
