<?php

namespace Tests\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Process\Process;

class PkpConcurrencyHarness
{
    public function assertRuntime(TestCase $test): void
    {
        $driver = config('database.default');
        $test->assertContains($driver, ['pgsql', 'mysql'], 'Concurrency tests require a real PostgreSQL or MySQL database.');
        $connection = DB::connection();
        $test->assertSame($driver, $connection->getDriverName());
        $test->assertSame(0, $connection->transactionLevel(), 'Race fixtures must not run inside the test connection transaction.');
        $connection->getPdo();
    }

    public function assertIndependentCommittedConnections(TestCase $test): void
    {
        $primary = DB::connection();
        $secondaryName = 'pkp_race_secondary_'.Str::lower(Str::random(8));
        config(['database.connections.'.$secondaryName => config('database.connections.'.config('database.default'))]);
        DB::purge($secondaryName);
        $secondary = DB::connection($secondaryName);
        $test->assertNotSame($primary->getPdo(), $secondary->getPdo());
        $test->assertSame($primary->getDriverName(), $secondary->getDriverName());

        $year = random_int(1900, 9999);
        $month = random_int(1, 12);
        $uuid = (string) Str::uuid();
        $now = now('UTC');
        $secondary->table('pkp_deadlines')->insert([
            'uuid' => $uuid,
            'year' => $year,
            'month' => $month,
            'deadline_at' => null,
            'version' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $test->assertSame(1, $primary->table('pkp_deadlines')->where('uuid', $uuid)->where('version', 0)->count());
        $primary->table('pkp_deadlines')->where('uuid', $uuid)->delete();
        DB::purge($secondaryName);
    }

    /**
     * @param  list<array<string, mixed>>  $operations
     * @return list<array{status: int, code: ?string, version: ?int}>
     */
    public function runTogether(array $operations): array
    {
        if (count($operations) !== 2) {
            throw new RuntimeException('The PKP race harness requires exactly two independent workers.');
        }
        if (DB::connection()->transactionLevel() !== 0) {
            throw new RuntimeException('Concurrent workers cannot observe fixtures hidden by an outer transaction.');
        }

        $directory = sys_get_temp_dir().'/pkp-race-'.Str::uuid();
        if (! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new RuntimeException('Unable to create isolated PKP race workspace.');
        }
        $workerPath = $directory.'/worker.php';
        $goPath = $directory.'/go';
        file_put_contents($workerPath, $this->workerSource());
        $workers = [];

        try {
            foreach ($operations as $index => $operation) {
                if (isset($operations[0]['identity_barrier'])) {
                    $operation['identity_reached_path'] = $directory.'/identity-read';
                    $operation['identity_updated_path'] = $directory.'/identity-updated';
                }
                $readyPath = $directory.'/ready-'.$index;
                $inputPath = $directory.'/input-'.$index.'.json';
                file_put_contents($inputPath, json_encode([
                    'operation' => $operation,
                    'ready_path' => $readyPath,
                    'go_path' => $goPath,
                ], JSON_THROW_ON_ERROR));

                $database = config('database.connections.'.config('database.default'));
                $processEnvironment = [
                    'APP_BASE_PATH' => base_path(),
                    'APP_ENV' => 'testing',
                    'APP_KEY' => (string) config('app.key'),
                    'DB_CONNECTION' => config('database.default'),
                    'DB_HOST' => $database['host'] ?? '',
                    'DB_PORT' => (string) ($database['port'] ?? ''),
                    'DB_DATABASE' => (string) ($database['database'] ?? ''),
                    'DB_USERNAME' => (string) ($database['username'] ?? ''),
                    'DB_PASSWORD' => (string) ($database['password'] ?? ''),
                    'CACHE_STORE' => 'array',
                    'CACHE_DRIVER' => 'array',
                    'SESSION_DRIVER' => 'array',
                    'QUEUE_CONNECTION' => 'sync',
                    'BCRYPT_ROUNDS' => '4',
                    'PKP_QPDF_BINARY' => (string) config('pkp.qpdf_binary'),
                ];
                $process = new Process([PHP_BINARY, $workerPath, $inputPath], base_path(), $processEnvironment);
                $process->setTimeout(30);
                $process->start();
                $workers[] = ['process' => $process, 'ready_path' => $readyPath];
            }

            $readyDeadline = microtime(true) + 20;
            while (microtime(true) < $readyDeadline) {
                $ready = true;
                foreach ($workers as $worker) {
                    if (! file_exists($worker['ready_path'])) {
                        $ready = false;
                        if (! $worker['process']->isRunning()) {
                            throw new RuntimeException('A PKP race worker stopped before its barrier: '.$worker['process']->getErrorOutput().$worker['process']->getOutput());
                        }
                    }
                }
                if ($ready) {
                    break;
                }
                usleep(10000);
            }
            if (! $ready) {
                throw new RuntimeException('Independent PKP workers did not reach the start barrier.');
            }

            file_put_contents($goPath, 'start');
            $results = [];
            foreach ($workers as $worker) {
                $process = $worker['process'];
                $process->wait();
                if (! $process->isSuccessful()) {
                    throw new RuntimeException('PKP race worker failed: '.$process->getErrorOutput().$process->getOutput());
                }
                $results[] = json_decode(trim($process->getOutput()), true, 512, JSON_THROW_ON_ERROR);
            }

            return $results;
        } finally {
            foreach ($workers as $worker) {
                if ($worker['process']->isRunning()) {
                    $worker['process']->stop(1);
                }
            }
            $this->removeDirectory($directory);
        }
    }

    private function workerSource(): string
    {
        return <<<'PHP'
<?php
$root = getenv('APP_BASE_PATH');
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$input = json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR);
file_put_contents($input['ready_path'], 'ready');
$deadline = microtime(true) + 20;
while (! file_exists($input['go_path'])) {
    if (microtime(true) > $deadline) {
        fwrite(STDERR, 'Race start barrier timed out.');
        exit(2);
    }
    usleep(10000);
}
$data = $input['operation'];
if (isset($data['identity_barrier'])) {
    $reached = false;
    \Illuminate\Support\Facades\DB::listen(function ($query) use ($data, &$reached) {
        $table = match ($data['identity_barrier']) {
            'before_read' => 'pkp_submissions',
            'before_history' => 'pegawai',
            default => 'riwayat_pangkat',
        };
        if ($reached || ! str_contains($query->sql, $table)) {
            return;
        }
        $reached = true;
        file_put_contents($data['identity_reached_path'], 'read');
        $deadline = microtime(true) + 20;
        while (! file_exists($data['identity_updated_path'])) {
            if (microtime(true) > $deadline) {
                throw new \RuntimeException('Identity update barrier timed out.');
            }
            usleep(10000);
        }
    });
}
try {
    $actor = \App\Models\User::query()->findOrFail($data['actor_uuid']);
    if ($data['operation'] === 'read_identity') {
        $identity = app(\App\Services\PkpIdentityService::class)->resolve($actor->pegawai, $data['year'], $data['month']);
        echo json_encode(['status' => 200, 'code' => null, 'version' => null, 'identity' => $identity], JSON_THROW_ON_ERROR);
        exit(0);
    }
    if ($data['operation'] === 'update_identity') {
        $deadline = microtime(true) + 20;
        while (! file_exists($data['identity_reached_path'])) {
            if (microtime(true) > $deadline) {
                throw new \RuntimeException('Identity read barrier timed out.');
            }
            usleep(10000);
        }
        \Illuminate\Support\Facades\DB::transaction(function () use ($actor) {
            \Illuminate\Support\Facades\DB::table('pegawai')->where('uuid', $actor->pegawai->uuid)->update(['nama' => 'Committed Updated Employee']);
            \Illuminate\Support\Facades\DB::table('riwayat_pangkat')->where('pegawai_uuid', $actor->pegawai->uuid)->update(['pangkat_golongan' => 'IV/b']);
            \Illuminate\Support\Facades\DB::table('riwayat_jabatan')->where('pegawai_uuid', $actor->pegawai->uuid)->update(['satuan_kerja' => 'Committed Updated Unit']);
        });
        file_put_contents($data['identity_updated_path'], 'committed');
        echo json_encode(['status' => 200, 'code' => null, 'version' => null], JSON_THROW_ON_ERROR);
        exit(0);
    }
    $result = match ($data['operation']) {
        'create' => app(\App\Services\PkpSubmissionService::class)->create($actor, $data['year'], $data['month'], $data['source']),
        'submit' => app(\App\Services\PkpSubmissionService::class)->submit($actor, $data['pkp_uuid'], $data['revision_uuid'], $data['expected_version'], new \DateTimeImmutable('now', new \DateTimeZone('UTC'))),
        'revise' => app(\App\Services\PkpSubmissionService::class)->revise($actor, $data['pkp_uuid'], $data['expected_version'], $data['base_revision_uuid'], $data['source']),
        'decision' => app(\App\Services\PkpAssessmentService::class)->decide($actor, $data['pkp_uuid'], $data['revision_uuid'], $data['submission_uuid'], $data['expected_version'], $data['decision'], $data['reason'] ?? null, new \DateTimeImmutable('now', new \DateTimeZone('UTC'))),
        'decide_document' => app(\App\Services\PkpDocumentService::class)->decide($actor, $data['pkp_uuid'], $data['document_uuid'], $data['revision_uuid'], $data['expected_version'], 'accept', null, true),
        'reassign_content' => app(\App\Services\PkpAssignmentService::class)->reassignContent($actor, $data['pkp_uuid'], $data['revision_uuid'], $data['expected_version'], $data['new_assessor_uuid'], $data['reason']),
        'reassign_inspector' => app(\App\Services\PkpAssignmentService::class)->reassignInspector($actor, $data['pkp_uuid'], $data['revision_uuid'], $data['expected_version'], $data['new_inspector_uuid'], $data['reason']),
        'upload_final' => app(\App\Services\PkpDocumentService::class)->store($actor, $data['pkp_uuid'], $data['revision_uuid'], $data['expected_version'], 'signed_final', new \Illuminate\Http\UploadedFile($data['file_path'], basename($data['file_path']), 'application/pdf', null, true), false, true),
        'configure_deadline' => app(\App\Services\PkpDeadlineService::class)->configure($actor, $data['year'], $data['month'], $data['expected_version'], $data['deadline_local']),
        default => throw new \RuntimeException('Unsupported PKP race operation.'),
    };
    echo json_encode(['status' => in_array($data['operation'], ['create', 'upload_final'], true) ? 201 : 200, 'code' => null, 'version' => $result->version], JSON_THROW_ON_ERROR);
} catch (\Illuminate\Auth\Access\AuthorizationException $exception) {
    echo json_encode(['status' => 403, 'code' => 'forbidden', 'version' => null], JSON_THROW_ON_ERROR);
} catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $exception) {
    echo json_encode(['status' => $exception->getStatusCode(), 'code' => $exception->getMessage(), 'version' => null], JSON_THROW_ON_ERROR);
} catch (\Illuminate\Validation\ValidationException $exception) {
    echo json_encode(['status' => 422, 'code' => 'validation_failed', 'version' => null], JSON_THROW_ON_ERROR);
} catch (\Throwable $exception) {
    fwrite(STDERR, get_class($exception).': '.$exception->getMessage());
    exit(1);
}
PHP;
    }

    private function removeDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }
        foreach (array_diff(scandir($directory) ?: [], ['.', '..']) as $entry) {
            $path = $directory.'/'.$entry;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($directory);
    }
}
