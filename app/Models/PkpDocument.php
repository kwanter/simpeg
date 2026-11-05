<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class PkpDocument extends Model
{
    use HasUuids;

    protected $table = 'pkp_documents';

    protected $primaryKey = 'uuid';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'uuid',
        'revision_uuid',
        'kind',
        'storage_path',
        'original_name',
        'detected_mime',
        'byte_size',
        'sha256',
        'page_count',
        'validator_version',
        'declared_signed',
        'uploaded_by_user_uuid',
        'uploaded_at',
        'replacement_of_document_uuid',
        'content_unchanged_declared',
    ];

    protected $casts = [
        'byte_size' => 'integer',
        'page_count' => 'integer',
        'declared_signed' => 'boolean',
        'content_unchanged_declared' => 'boolean',
        'uploaded_at' => 'immutable_datetime',
    ];
}
