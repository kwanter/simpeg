<?php

namespace App\Services;

use App\Models\User;
use App\Support\ConfigurationResult;
use App\Support\PkpContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PkpDeadlineService
{
    public function __construct(private readonly PkpAuthorization $authorization) {}

    public function forPeriod(User $actor, int $year, int $month): ConfigurationResult
    {
        $this->authorizeAndValidate($actor, $year, $month);
        $row = DB::table('pkp_deadlines')->where('year', $year)->where('month', $month)->first();
        $deadlineAt = $row?->deadline_at === null
            ? null
            : CarbonImmutable::parse($row->deadline_at, 'UTC')->toDateTimeImmutable();

        return new ConfigurationResult(
            $row->uuid ?? null,
            (int) ($row->version ?? 0),
            null,
            [],
            $deadlineAt,
            $year,
            $month,
        );
    }

    public function configure(
        User $actor,
        int $year,
        int $month,
        int $expectedVersion,
        ?string $deadlineLocal,
    ): ConfigurationResult {
        $this->authorizeAndValidate($actor, $year, $month);
        $deadlineUtc = $this->parseDeadlineLocal($deadlineLocal);

        return DB::transaction(function () use ($actor, $year, $month, $expectedVersion, $deadlineLocal, $deadlineUtc): ConfigurationResult {
            $row = DB::table('pkp_deadlines')
                ->where('year', $year)
                ->where('month', $month)
                ->lockForUpdate()
                ->first();

            if ($row === null) {
                $now = now('UTC');
                DB::table('pkp_deadlines')->insertOrIgnore([
                    'uuid' => (string) Str::uuid(),
                    'year' => $year,
                    'month' => $month,
                    'deadline_at' => null,
                    'version' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $row = DB::table('pkp_deadlines')
                    ->where('year', $year)
                    ->where('month', $month)
                    ->lockForUpdate()
                    ->first();
            }

            $this->authorization->authorize($actor, 'manageDeadlines', new PkpContext);
            if ($expectedVersion !== (int) $row->version) {
                abort(409, 'stale_configuration');
            }

            $now = now('UTC');
            $version = $expectedVersion + 1;
            $before = ['deadline_at' => $row->deadline_at];
            $after = [
                'deadline_local' => $deadlineLocal,
                'deadline_at' => $deadlineUtc?->format('Y-m-d H:i:s.u'),
                'version' => $version,
            ];

            DB::table('pkp_deadlines')->where('uuid', $row->uuid)->update([
                'deadline_at' => $after['deadline_at'],
                'version' => $version,
                'updated_at' => $now,
            ]);
            DB::table('pkp_configuration_events')->insert([
                'uuid' => (string) Str::uuid(),
                'kind' => 'deadline',
                'configuration_uuid' => $row->uuid,
                'actor_user_uuid' => $actor->uuid,
                'before_json' => json_encode($before, JSON_THROW_ON_ERROR),
                'after_json' => json_encode($after, JSON_THROW_ON_ERROR),
                'created_at' => $now,
            ]);

            return new ConfigurationResult($row->uuid, $version, null, [], $deadlineUtc, $year, $month);
        });
    }

    private function authorizeAndValidate(User $actor, int $year, int $month): void
    {
        $this->authorization->authorize($actor, 'manageDeadlines', new PkpContext);
        if ($year < 1900 || $year > 9999 || $month < 1 || $month > 12) {
            throw ValidationException::withMessages(['period' => 'The deadline period is invalid.']);
        }
    }

    private function parseDeadlineLocal(?string $deadlineLocal): ?CarbonImmutable
    {
        if ($deadlineLocal === null) {
            return null;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}$/', $deadlineLocal) !== 1) {
            throw ValidationException::withMessages(['deadline_local' => 'Use YYYY-MM-DDTHH:mm:ss in Asia/Makassar.']);
        }

        try {
            $local = CarbonImmutable::createFromFormat('!Y-m-d\TH:i:s', $deadlineLocal, 'Asia/Makassar');
        } catch (\InvalidArgumentException) {
            $local = null;
        }

        if ($local === null || $local->format('Y-m-d\TH:i:s') !== $deadlineLocal) {
            throw ValidationException::withMessages(['deadline_local' => 'The local deadline is not a valid calendar instant.']);
        }

        return $local->utc();
    }
}
