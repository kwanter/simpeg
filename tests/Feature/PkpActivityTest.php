<?php

namespace Tests\Feature;

use App\Services\PkpSubmissionService;
use Illuminate\Support\Facades\Schema;
use Tests\SimpegTestCase;

class PkpActivityTest extends SimpegTestCase
{
    /** @dataProvider forbiddenBounds */
    public function test_invalid_bounds_and_unsupported_numeric_forms_preserve_the_original_tree(string $field, mixed $value): void
    {
        $owner = $this->createUserWithRole('user');
        $created = app(PkpSubmissionService::class)->create($owner, 2030, 2, 'application');
        $activity = ['description' => 'Preserved activity', 'unit' => 'doc', 'target_credit' => '1', 'target_quantity' => '3', 'target_quality' => '100', 'realized_quantity' => '2'];
        $tree = [['title' => 'Bounds evidence', 'activities' => [$activity]]];
        $url = '/pkp/'.$created->recordUuid.'/revisions/'.$created->pendingRevisionUuid;
        $this->actingAs($owner)->putJson($url, ['expected_version' => 1, 'indicators' => $tree])->assertOk();
        if ($field === 'title') {
            $tree[0]['title'] = $value;
        } elseif ($field === 'indicators') {
            $tree = array_fill(0, $value, $tree[0]);
        } elseif ($field === 'activities') {
            $tree[0]['activities'] = array_fill(0, $value, $activity);
        } else {
            $tree[0]['activities'][0][$field] = $value;
        }
        $this->actingAs($owner)->putJson($url, ['expected_version' => 2, 'indicators' => $tree])->assertUnprocessable();
        $this->assertDatabaseHas('pkp', ['uuid' => $created->recordUuid, 'version' => 2]);
        $this->assertDatabaseCount('pkp_activities', 1);
        $this->assertDatabaseHas('pkp_activities', ['description' => 'Preserved activity']);
    }

    public static function forbiddenBounds(): array
    {
        $cases = [
            'indicator text' => ['title', str_repeat('x', 501)],
            'activity text' => ['description', str_repeat('x', 2001)],
            'unit text' => ['unit', str_repeat('x', 101)],
            'indicator count' => ['indicators', 51], 'activity count' => ['activities', 501],
            'credit range' => ['target_credit', '100000000'],
            'quantity range' => ['target_quantity', '10000000000'],
            'quality range' => ['target_quality', '100.0001'],
            'realization range' => ['realized_quantity', '10000000000'],
            'credit precision' => ['target_credit', '0.0000001'],
            'quantity precision' => ['target_quantity', '1.00001'],
            'quality precision' => ['target_quality', '1.00001'],
            'realization precision' => ['realized_quantity', '1.00001'],
            'different target unit' => ['target_unit', 'page'],
            'different realization unit' => ['realized_unit', 'hour'],
        ];
        foreach (['target_credit', 'target_quantity', 'target_quality', 'realized_quantity'] as $field) {
            foreach (['1e2', 'NaN', 'Infinity', '-1'] as $value) {
                $cases[$field.' '.$value] = [$field, $value];
            }
        }

        return $cases;
    }

    public function test_exact_rating_boundaries_and_equal_indicator_weighting_through_http(): void
    {
        $owner = $this->createUserWithRole('user');
        foreach ([50 => 'Buruk', 60 => 'Sedang', 75 => 'Cukup', 100 => 'Sangat Baik'] as $value => $predicate) {
            $created = app(PkpSubmissionService::class)->create($owner, 2030, array_search($value, [50, 60, 75, 100], true) + 1, 'application');
            $tree = [['title' => 'Rating boundary', 'activities' => [['description' => 'Independent boundary', 'unit' => 'doc', 'target_credit' => '1', 'target_quantity' => '100', 'target_quality' => '100', 'realized_quantity' => (string) $value]]]];
            $this->actingAs($owner)->putJson('/pkp/'.$created->recordUuid.'/revisions/'.$created->pendingRevisionUuid, ['expected_version' => 1, 'indicators' => $tree])->assertOk();
            $this->actingAs($owner)->getJson('/pkp/'.$created->recordUuid)->assertOk()
                ->assertJsonPath('data.score.overall_score.numerator', (string) $value)->assertJsonPath('data.score.overall_score.denominator', '1')->assertJsonPath('data.score.predicate', $predicate);
        }
        $created = app(PkpSubmissionService::class)->create($owner, 2030, 5, 'application');
        $activity = fn (string $value) => ['description' => 'Equal weighting '.$value, 'unit' => 'doc', 'target_credit' => '1', 'target_quantity' => '100', 'target_quality' => '100', 'realized_quantity' => $value];
        $this->actingAs($owner)->putJson('/pkp/'.$created->recordUuid.'/revisions/'.$created->pendingRevisionUuid, ['expected_version' => 1, 'indicators' => [
            ['title' => 'A', 'activities' => [$activity('60'), $activity('100')]], ['title' => 'B', 'activities' => [$activity('100')]],
        ]])->assertOk();
        $this->actingAs($owner)->getJson('/pkp/'.$created->recordUuid)->assertOk()
            ->assertJsonPath('data.score.indicators.0.score.numerator', '80')->assertJsonPath('data.score.indicators.1.score.numerator', '100')->assertJsonPath('data.score.overall_score.numerator', '90');
    }

    /** @dataProvider ineligibleActors */
    public function test_ineligible_actor_cannot_mutate_an_owned_pkp(string $kind): void
    {
        $owner = $this->createUserWithRole('user');
        $created = app(PkpSubmissionService::class)->create($owner, 2030, 2, 'application');
        match ($kind) {
            'inactive' => $owner->forceFill(['status' => '0'])->save(),
            'unverified' => $owner->forceFill(['email_verified_at' => null])->save(),
            'deleted user' => $owner->delete(),
            'deleted employee' => $owner->pegawai->delete(),
        };
        $this->actingAs($owner)->putJson('/pkp/'.$created->recordUuid.'/revisions/'.$created->pendingRevisionUuid, ['expected_version' => 1, 'notes' => 'Forbidden mutation'])->assertForbidden();
        $this->assertDatabaseHas('pkp', ['uuid' => $created->recordUuid, 'version' => 1]);
        $this->assertDatabaseHas('pkp_revisions', ['uuid' => $created->pendingRevisionUuid, 'notes' => null]);
        foreach ([
            'submit' => ['revision_uuid' => $created->pendingRevisionUuid],
            'refresh-identity' => ['revision_uuid' => $created->pendingRevisionUuid, 'reason' => 'Forbidden actor action.'],
            'revisions' => ['base_revision_uuid' => $created->pendingRevisionUuid, 'source' => 'application'],
        ] as $action => $payload) {
            $this->actingAs($owner)->postJson('/pkp/'.$created->recordUuid.'/'.$action, ['expected_version' => 1] + $payload)->assertForbidden();
        }
    }

    public static function ineligibleActors(): array
    {
        return array_map(fn (string $kind): array => [$kind], ['inactive', 'unverified', 'deleted user', 'deleted employee']);
    }

    public function test_manual_score_overrides_and_source_mode_changes_are_rejected(): void
    {
        $owner = $this->createUserWithRole('user');
        $created = app(PkpSubmissionService::class)->create($owner, 2030, 2, 'application');
        foreach (['score' => '100', 'score_snapshot' => ['overall_score' => '100'], 'source' => 'external'] as $field => $value) {
            $this->actingAs($owner)->putJson('/pkp/'.$created->recordUuid.'/revisions/'.$created->pendingRevisionUuid, ['expected_version' => 1, $field => $value])
                ->assertUnprocessable()->assertJsonValidationErrors($field);
            $this->assertDatabaseHas('pkp', ['uuid' => $created->recordUuid, 'version' => 1]);
            $this->assertDatabaseCount('pkp_activities', 0);
        }
    }

    public function test_loaded_editor_decimals_round_trip_as_invariant_strings(): void
    {
        $owner = $this->createUserWithRole('user');
        $created = app(PkpSubmissionService::class)->create($owner, 2030, 2, 'application');
        $url = '/pkp/'.$created->recordUuid.'/revisions/'.$created->pendingRevisionUuid;
        $tree = [['title' => 'Kinerja', 'activities' => [[
            'description' => 'Small credit', 'unit' => 'dokumen', 'target_credit' => '0.000001',
            'target_quantity' => '3.0000', 'target_quality' => '100.0000', 'realized_quantity' => '2.0000',
        ]]]];
        $this->actingAs($owner)->putJson($url, ['expected_version' => 1, 'indicators' => $tree])->assertOk();
        $edit = $this->actingAs($owner)->getJson($url.'/edit')->assertOk()
            ->assertJsonPath('data.indicators.0.activities.0.target_credit', '0.000001')
            ->assertJsonPath('data.indicators.0.activities.0.target_quantity', '3');
        $this->actingAs($owner)->putJson($url, ['expected_version' => 2, 'notes' => 'Metadata-only edit', 'indicators' => $edit->json('data.indicators')])->assertOk();
        $this->assertDatabaseHas('pkp', ['uuid' => $created->recordUuid, 'version' => 3]);
        $this->assertDatabaseCount('pkp_activities', 1);
    }

    /** @dataProvider associativeActivityIndexes */
    public function test_associative_activity_indexes_cannot_bypass_raw_decimal_validation(string $indicatorKey, string $activityKey): void
    {
        $owner = $this->createUserWithRole('user');
        $created = app(PkpSubmissionService::class)->create($owner, 2030, 2, 'application');

        $this->actingAs($owner)->putJson('/pkp/'.$created->recordUuid.'/revisions/'.$created->pendingRevisionUuid, [
            'expected_version' => 1, 'indicators' => [$indicatorKey => ['title' => 'Kinerja', 'activities' => [$activityKey => [
                'description' => 'Do not store', 'unit' => 'dokumen', 'target_credit' => '1.000000',
                'target_quantity' => ' 2.0000 ', 'target_quality' => '100.0000', 'realized_quantity' => '1.0000',
            ]]]],
        ])->assertUnprocessable()->assertJsonValidationErrors('indicators.'.$indicatorKey.'.activities.'.$activityKey.'.target_quantity');

        $this->assertDatabaseHas('pkp', ['uuid' => $created->recordUuid, 'version' => 1]);
        $this->assertDatabaseCount('pkp_activities', 0);
    }

    public static function associativeActivityIndexes(): array
    {
        return [['indicator', 'activity'], ['indicator.with.dot', 'activity.with.dot']];
    }

    /** @dataProvider invalidDraftMetadata */
    public function test_public_draft_service_rejects_invalid_metadata_without_mutation(array $draft, string $field): void
    {
        $owner = $this->createUserWithRole('user');
        $created = app(PkpSubmissionService::class)->create($owner, 2030, 2, 'application');
        try {
            app(PkpSubmissionService::class)->save($owner, $created->recordUuid, $created->pendingRevisionUuid, 1, $draft);
            $this->fail('Invalid draft metadata must be rejected at the public service boundary.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertArrayHasKey($field, $exception->errors());
        }
        $this->assertDatabaseHas('pkp', ['uuid' => $created->recordUuid, 'version' => 1]);
        $this->assertDatabaseHas('pkp_revisions', ['uuid' => $created->pendingRevisionUuid, 'notes' => null, 'assessor_pegawai_uuid' => null]);
    }

    public static function invalidDraftMetadata(): array
    {
        return [
            'long notes' => [['notes' => str_repeat('x', 5001)], 'notes'],
            'numeric notes' => [['notes' => 42], 'notes'],
            'null assessor' => [['assessor_pegawai_uuid' => null], 'assessor_pegawai_uuid'],
            'numeric assessor' => [['assessor_pegawai_uuid' => 42], 'assessor_pegawai_uuid'],
        ];
    }

    /** @dataProvider decimalWhitespace */
    public function test_raw_decimal_whitespace_is_rejected_without_replacing_the_activity_tree(string $field, string $value): void
    {
        $owner = $this->createUserWithRole('user');
        $created = app(PkpSubmissionService::class)->create($owner, 2030, 2, 'application');
        $url = '/pkp/'.$created->recordUuid.'/revisions/'.$created->pendingRevisionUuid;
        $tree = [['title' => 'Indikator', 'activities' => [[
            'description' => 'Original activity', 'unit' => 'dokumen', 'target_credit' => '1.000000',
            'target_quantity' => '2.0000', 'target_quality' => '100.0000', 'realized_quantity' => '1.0000',
        ]]]];
        $this->actingAs($owner)->putJson($url, ['expected_version' => 1, 'indicators' => $tree])->assertOk();
        $tree[0]['activities'][0][$field] = $value;
        $tree[0]['activities'][0]['description'] = 'must not replace the original';

        $this->actingAs($owner)->putJson($url, ['expected_version' => 2, 'indicators' => $tree])
            ->assertUnprocessable()->assertJsonValidationErrors('indicators.0.activities.0.'.$field);

        $this->assertDatabaseHas('pkp', ['uuid' => $created->recordUuid, 'version' => 2]);
        $this->assertDatabaseCount('pkp_activities', 1);
        $this->assertDatabaseHas('pkp_activities', ['description' => 'Original activity']);
    }

    public static function decimalWhitespace(): array
    {
        $cases = [];
        foreach (['target_credit', 'target_quantity', 'target_quality', 'realized_quantity'] as $field) {
            foreach (['leading' => ' 1.0000', 'trailing' => '1.0000 ', 'tabs' => "\t1.0000\n", 'blank' => ' '] as $label => $value) {
                $cases[$field.' '.$label] = [$field, $value];
            }
        }

        return $cases;
    }

    public function test_decimal_normalization_outside_pkp_is_unchanged(): void
    {
        \Illuminate\Support\Facades\Route::put('/normalization-probe', fn (\Illuminate\Http\Request $request) => response()->json($request->all()));

        $this->putJson('/normalization-probe', ['indicators' => [['activities' => [['target_quantity' => ' 1.0000 ']]]]])
            ->assertOk()->assertJsonPath('indicators.0.activities.0.target_quantity', '1.0000');
    }

    public function test_activity_storage_tables_exist(): void
    {
        $this->assertTrue(Schema::hasTable('pkp_indicators'));
        $this->assertTrue(Schema::hasTable('pkp_activities'));
    }

    public function test_bootstrap_activity_edit_http_contract(): void
    {
        $owner = $this->createUserWithRole('user');
        $created = $this->actingAs($owner)->postJson('/pkp', [
            'year' => 2030,
            'month' => 2,
            'source' => 'application',
        ])->assertCreated();
        $pkpUuid = $created->json('data.pkp_uuid');
        $revisionUuid = $created->json('data.revision_uuid');

        $this->actingAs($owner)
            ->putJson('/pkp/'.$pkpUuid.'/revisions/'.$revisionUuid, [
                'expected_version' => 1,
                'indicators' => [
                    [
                        'title' => 'Kualitas pelayanan',
                        'activities' => [
                            [
                                'description' => 'Menyelesaikan target kerja',
                                'unit' => 'dokumen',
                                'target_credit' => '1.250000',
                                'target_quantity' => '4.0000',
                                'target_quality' => '95.0000',
                                'realized_quantity' => '3.0000',
                            ],
                            [
                                'description' => null,
                                'unit' => null,
                                'target_credit' => null,
                                'target_quantity' => null,
                                'target_quality' => null,
                                'realized_quantity' => null,
                            ],
                        ],
                    ],
                    [
                        'title' => 'Ketepatan waktu',
                        'activities' => [
                            [
                                'description' => 'Menyusun laporan',
                                'unit' => 'laporan',
                                'target_credit' => '0.250000',
                                'target_quantity' => '2.0000',
                                'target_quality' => '100.0000',
                                'realized_quantity' => '2.0000',
                            ],
                        ],
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.version', 2)
            ->assertJsonPath('data.pending_revision_uuid', $revisionUuid);
    }

    public function test_activity_service_replaces_tree_atomically_and_preserves_order_and_nulls(): void
    {
        $owner = $this->createUserWithRole('user');
        $created = app(PkpSubmissionService::class)->create($owner, 2030, 2, 'application');
        $tree = [
            [
                'title' => 'Kualitas pelayanan',
                'activities' => [
                    ['description' => null, 'unit' => null, 'target_credit' => null, 'target_quantity' => null, 'target_quality' => null, 'realized_quantity' => null],
                    ['description' => 'Menyusun laporan', 'unit' => 'laporan', 'target_credit' => '0.250000', 'target_quantity' => '2.0000', 'target_quality' => '100.0000', 'realized_quantity' => '2.0000'],
                ],
            ],
            [
                'title' => 'Ketepatan waktu',
                'activities' => [
                    ['description' => 'Menyelesaikan target kerja', 'unit' => 'dokumen', 'target_credit' => '1.250000', 'target_quantity' => '4.0000', 'target_quality' => '95.0000', 'realized_quantity' => '3.0000'],
                ],
            ],
        ];

        $result = app(PkpSubmissionService::class)->save(
            $owner,
            $created->recordUuid,
            $created->pendingRevisionUuid,
            1,
            ['indicators' => $tree]
        );

        $this->assertSame(2, $result->version);
        $indicators = \Illuminate\Support\Facades\DB::table('pkp_indicators')
            ->where('revision_uuid', $created->pendingRevisionUuid)
            ->orderBy('position')
            ->get();
        $this->assertSame(['Kualitas pelayanan', 'Ketepatan waktu'], $indicators->pluck('title')->all());
        $activities = \Illuminate\Support\Facades\DB::table('pkp_activities')
            ->join('pkp_indicators', 'pkp_indicators.uuid', '=', 'pkp_activities.indicator_uuid')
            ->whereIn('pkp_activities.indicator_uuid', $indicators->pluck('uuid'))
            ->orderBy('pkp_indicators.position')
            ->orderBy('pkp_activities.position')
            ->select('pkp_activities.*')
            ->get();
        $this->assertCount(3, $activities);
        $this->assertNull($activities[0]->description);
        $this->assertNull($activities[0]->unit);
        $this->assertSame('0.25', (string) $activities[1]->target_credit);
    }

    public function test_activity_http_rejects_excess_scale_without_partial_tree_save(): void
    {
        $owner = $this->createUserWithRole('user');
        $created = $this->actingAs($owner)->postJson('/pkp', [
            'year' => 2030,
            'month' => 2,
            'source' => 'application',
        ])->assertCreated();
        $pkpUuid = $created->json('data.pkp_uuid');
        $revisionUuid = $created->json('data.revision_uuid');
        $activityTree = [[
            'title' => 'Indikator',
            'activities' => [[
                'description' => 'Kegiatan',
                'unit' => 'laporan',
                'target_credit' => '0.250000',
                'target_quantity' => '2.0000',
                'target_quality' => '100.0000',
                'realized_quantity' => '2.0000',
            ]],
        ]];

        $this->actingAs($owner)->putJson('/pkp/'.$pkpUuid.'/revisions/'.$revisionUuid, [
            'expected_version' => 1,
            'indicators' => $activityTree,
        ])->assertOk();

        $activityTree[0]['activities'][0]['target_credit'] = '0.2500000';
        $this->actingAs($owner)->putJson('/pkp/'.$pkpUuid.'/revisions/'.$revisionUuid, [
            'expected_version' => 2,
            'indicators' => $activityTree,
        ])->assertUnprocessable();

        $this->assertDatabaseHas('pkp', ['uuid' => $pkpUuid, 'version' => 2]);
        $this->assertSame(1, \Illuminate\Support\Facades\DB::table('pkp_activities')
            ->join('pkp_indicators', 'pkp_indicators.uuid', '=', 'pkp_activities.indicator_uuid')
            ->where('pkp_indicators.revision_uuid', $revisionUuid)
            ->count());
        $this->assertSame('0.25', (string) \Illuminate\Support\Facades\DB::table('pkp_activities')
            ->join('pkp_indicators', 'pkp_indicators.uuid', '=', 'pkp_activities.indicator_uuid')
            ->where('pkp_indicators.revision_uuid', $revisionUuid)
            ->value('target_credit'));
    }
}
