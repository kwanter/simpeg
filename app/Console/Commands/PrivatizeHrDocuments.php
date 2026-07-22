<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PrivatizeHrDocuments extends Command
{
    protected $signature = 'documents:privatize';

    protected $description = 'Move legacy public cuti/izin documents to private storage';

    public function handle(): int
    {
        $moved = 0;

        foreach (['cuti', 'izin'] as $type) {
            $directory = 'dokumen/'.$type;
            foreach (Storage::disk('public')->files($directory) as $source) {
                $filename = basename($source);
                $target = $directory.'/'.$filename;

                $stream = Storage::disk('public')->readStream($source);
                if ($stream === false) {
                    $this->error("Cannot read {$source}");

                    return self::FAILURE;
                }

                $written = Storage::disk('local')->writeStream($target, $stream);
                if (is_resource($stream)) {
                    fclose($stream);
                }
                $sourceSize = Storage::disk('public')->size($source);
                $targetExists = Storage::disk('local')->exists($target);
                if (! $written || ! $targetExists || Storage::disk('local')->size($target) !== $sourceSize) {
                    Storage::disk('local')->delete($target);
                    $this->error("Cannot verify {$target}");

                    return self::FAILURE;
                }

                Storage::disk('public')->delete($source);
                $moved++;
            }
        }

        $this->info("Privatized {$moved} HR document(s).");

        return self::SUCCESS;
    }
}
