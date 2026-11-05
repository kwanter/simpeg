<?php

namespace App\Support;

use App\Models\Pegawai;

class PkpContext
{
    public function __construct(
        public readonly ?object $pkp = null,
        public readonly ?object $revision = null,
        public readonly ?object $candidate = null,
        public readonly ?object $submission = null,
        public readonly ?Pegawai $targetPegawai = null,
    ) {}
}
