<?php

namespace Tests\Feature;

use Tests\SimpegTestCase;

class PkpScoreDisplayTest extends SimpegTestCase
{
    public function test_bootstrap_exact_score_detail_http_contract(): void
    {
        $owner = $this->createUserWithRole('user');
        $created = $this->actingAs($owner)->postJson('/pkp', [
            'year' => 2030,
            'month' => 3,
            'source' => 'application',
        ])->assertCreated();
        $pkpUuid = $created->json('data.pkp_uuid');
        $revisionUuid = $created->json('data.revision_uuid');

        $this->actingAs($owner)->putJson('/pkp/'.$pkpUuid.'/revisions/'.$revisionUuid, [
            'expected_version' => 1,
            'indicators' => [[
                'title' => 'Kinerja',
                'activities' => [[
                    'description' => 'Menyelesaikan target kerja',
                    'unit' => 'dokumen',
                    'target_credit' => '1.000000',
                    'target_quantity' => '3.0000',
                    'target_quality' => '100.0000',
                    'realized_quantity' => '2.0000',
                ]],
            ]],
        ])->assertOk();

        $this->actingAs($owner)->getJson('/pkp/'.$pkpUuid)
            ->assertOk()
            ->assertJsonPath('data.score.overall_score.numerator', '200')
            ->assertJsonPath('data.score.overall_score.denominator', '3')
            ->assertJsonPath('data.score.overall_score.display', '66,666666...')
            ->assertJsonPath('data.score.overall_score.repeating', true)
            ->assertJsonPath('data.score.predicate', 'Cukup');

        $this->actingAs($owner)->get('/pkp/'.$pkpUuid)
            ->assertOk()
            ->assertSee('66,666666...')
            ->assertSee('200/3');
    }

    public function test_screen_exposes_distinct_indicator_and_activity_fractions(): void
    {
        $owner = $this->createUserWithRole('user');
        $created = $this->actingAs($owner)->postJson('/pkp', ['year' => 2030, 'month' => 3, 'source' => 'application'])->assertCreated();
        $pkp = $created->json('data.pkp_uuid');
        $revision = $created->json('data.revision_uuid');
        $activity = fn (string $description, string $realized) => [
            'description' => $description, 'unit' => 'dokumen', 'target_credit' => '1',
            'target_quantity' => '3', 'target_quality' => '100', 'realized_quantity' => $realized,
        ];
        $this->actingAs($owner)->putJson('/pkp/'.$pkp.'/revisions/'.$revision, [
            'expected_version' => 1,
            'indicators' => [
                ['title' => 'First', 'activities' => [$activity('Two thirds', '2'), $activity('Full', '3')]],
                ['title' => 'Second', 'activities' => [$activity('One third', '1')]],
            ],
        ])->assertOk();
        $this->actingAs($owner)->get('/pkp/'.$pkp)->assertOk()
            ->assertSee('200/3')->assertSee('250/3')->assertSee('175/3')
            ->assertSee('100/1')->assertSee('Nilai rasional eksak indikator');
    }

    public function test_incomplete_draft_has_no_zero_score_or_predicate(): void
    {
        $owner = $this->createUserWithRole('user');
        $created = $this->actingAs($owner)->postJson('/pkp', [
            'year' => 2030,
            'month' => 4,
            'source' => 'application',
        ])->assertCreated();

        $this->actingAs($owner)->getJson('/pkp/'.$created->json('data.pkp_uuid'))
            ->assertOk()
            ->assertJsonPath('data.score', null);
    }
}
