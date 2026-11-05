# Monthly PKP Acceptance-Criterion Evidence

**Specification:** v0.4, unchanged. **Execution date:** 2026-10-07.

This inventory records realized tests, not the original plan's promised test
names. A passing related test does not close an unasserted compound facet.
`UNVERIFIED` means partial evidence with the stated remaining test seam.
No omitted case is relabeled passed because a suite count is green.

## Gate Evidence

- **PHP:** real Laravel HTTP/service/provider tests and the full suite.
- **SQL:** dedicated PostgreSQL 16 and MySQL 8.0 committed-worker suite.
- **Browser:** authenticated Chromium sessions/CSRF, no response stubs.
- **PDF:** actual qpdf QDF text/coordinates plus independent Poppler QA.
- **Envelope:** actual Nginx/PHP-FPM, 10 MiB and proxy-envelope boundaries.

Final gate commands/results and local logs are recorded in
`docs/pkp-verification.md`. Baseline full rollback initially failed on two
inherited defects. The user
authorized their repair through continuation, and both complete local
up/down/up cycles now pass. Hosted CI and production release remain separate.

## All 90 Criteria

| Criterion | Realized evidence | Status |
| --- | --- | --- |
| AC-001 | T001 | PASS |
| AC-002 | T002,T003 | PASS |
| AC-003 | T004 | PASS |
| AC-004 | T005,T006 | PASS |
| AC-005 | T007 | PASS |
| AC-006 | T008,T009 | PASS |
| AC-007 | T004 | PASS |
| AC-008 | T010 | PASS |
| AC-009 | T011,T012,T013 | PASS |
| AC-010 | T014 | PASS |
| AC-011 | T015,T016 | PASS |
| AC-012 | T017 | PASS |
| AC-013 | T018,T019 | PASS |
| AC-014 | T020 | PASS |
| AC-015 | T021 | PASS |
| AC-016 | T013 | PASS |
| AC-017 | T022,T004 | PASS |
| AC-018 | T023,T014,T024 | PASS |
| AC-019 | T025 | PASS |
| AC-020 | T026,T025 | PASS |
| AC-021 | T007 | PASS |
| AC-022 | T027 | PASS |
| AC-023 | T028 | PASS |
| AC-024 | T027,T029,T030 | PASS |
| AC-025 | T031 | PASS |
| AC-026 | T032,T033 | PASS |
| AC-027 | T034,T035 | PASS |
| AC-028 | T036,T037 | PASS |
| AC-029 | T038,T039,T040 | PASS |
| AC-030 | T037 | PASS |
| AC-031 | T037,T039 | PASS |
| AC-032 | T041 | PASS |
| AC-033 | T041 | PASS |
| AC-034 | T041 | PASS |
| AC-035 | T042,T043 | PASS |
| AC-036 | T044 | PASS |
| AC-037 | T025 | PASS |
| AC-038 | T043,T045 | PASS |
| AC-039 | T027,T046 | PASS |
| AC-040 | T047,T046 | PASS |
| AC-041 | T027,T030 | PASS |
| AC-042 | T029,T030 | PASS |
| AC-043 | T048,T014 | PASS |
| AC-044 | T015 | PASS |
| AC-045 | T049 | PASS |
| AC-046 | T050,T015 | PASS |
| AC-047 | T029,T023 | PASS |
| AC-048 | T051 | PASS |
| AC-049 | T051 | PASS |
| AC-050 | T029,T051 | PASS |
| AC-051 | T052,T053,T054 | PASS |
| AC-052 | T007,T055 | PASS |
| AC-053 | T056 | PASS |
| AC-054 | T057 | PASS |
| AC-055 | T058,T059,T060 | PASS |
| AC-056 | T059,T061 | PASS |
| AC-057 | T062,T054,T063,T064 | PASS |
| AC-058 | T065,T066 | PASS |
| AC-059 | T067 | PASS |
| AC-060 | T068 | PASS |
| AC-061 | T069,T070,T046 | PASS |
| AC-062 | T071,T072 | PASS |
| AC-063 | T046,T073,T074,T075 | PASS |
| AC-064 | T076,T018 | PASS |
| AC-065 | T077,T019 | PASS |
| AC-066 | T078,T079 | PASS |
| AC-067 | T080,T081 | PASS |
| AC-068 | T082,T005,T043 | PASS |
| AC-069 | T082,T083 | PASS |
| AC-070 | T007,T027 | PASS |
| AC-071 | T084 | PASS |
| AC-072 | T004 | PASS |
| AC-073 | T004 | PASS |
| AC-074 | T042,T043,T044 | PASS |
| AC-075 | T013,T012,T085 | PASS |
| AC-076 | T086,T087,T016 | PASS |
| AC-077 | T053,T022 | PASS |
| AC-078 | T029,T073,T030 | PASS |
| AC-079 | T088,T016 | PASS |
| AC-080 | T089 | PASS |
| AC-081 | T004 | PASS |
| AC-082 | T090,T091,T092,T021 | PASS |
| AC-083 | T093,T037,T038 | PASS |
| AC-084 | T094,T095,T096,T050,T004 | PASS |
| AC-085 | T097,T098 | PASS |
| AC-086 | T099 | PASS |
| AC-087 | T100,T003 | PASS |
| AC-088 | T101,T099,T102 | PASS |
| AC-089 | T004 | PASS |
| AC-090 | T006 | PASS |

## Compound Facet Reconciliation

The initial inventory retained seven unverified compound facets. Direct
numeric resubmit, returned external replacement, signed-R1/R2 completion,
same-draft history recovery, and source-mode correction assertions now
supply their missing proof. No related-test count was used as a substitute.

## Exact Realized Test Names

### T001

- File: `tests/Feature/PkpDraftTest.php`
- Test:
  `test_bootstrap_owned_draft_http_contract`
- Gate: PHP.

### T002

- File: `tests/Feature/PkpDraftTest.php`
- Test:
  `test_create_service_rejects_duplicate_period_without_extra_records`
- Gate: PHP.

### T003

- File: `tests/Integration/Pkp/PkpWorkflowRaceTest.php`
- Test:
  `test_duplicate_month_creation_has_one_database_winner`
- Gate: SQL.

### T004

- File: `tests/Feature/PkpRevisionTest.php`
- Test:
`test_owner_and_unassigned_administrators_cannot_decide_or_access_foreign_evidence`
- Gate: PHP.

### T005

- File: `tests/Feature/PkpActivityTest.php`
- Test:
  `test_activity_service_replaces_tree_atomically_and_preserves_order_and_nulls`
- Gate: PHP.

### T006

- File: `e2e/pkp.spec.js`
- Test:
  `owner reloads and saves all 500 distinct activities without losing order or
values`
- Gate: Browser.

### T007

- File: `tests/Feature/PkpRevisionTest.php`
- Test:
`test_incomplete_drafts_never_capture_first_submission_until_their_fields_are_completed`
- Gate: PHP.

### T008

- File: `tests/Feature/PkpSubmissionTest.php`
- Test:
  `test_bootstrap_submission_http_contract`
- Gate: PHP.

### T009

- File: `tests/Feature/PkpAssessmentTest.php`
- Test:
  `test_assessor_queue_lists_only_current_assigned_content_attempts`
- Gate: PHP.

### T010

- File: `tests/Feature/PkpAssessmentTest.php`
- Test:
  `test_return_requires_reason_and_preserves_the_submitted_attempt`
- Gate: PHP.

### T011

- File: `tests/Feature/PkpPdfTest.php`
- Test:
  `test_bootstrap_approved_application_pdf_http_contract`
- Gate: PHP / PDF.

### T012

- File: `tests/Feature/PkpPdfTest.php`
- Test:
  `test_maximum_description_title_and_unit_stay_inside_actual_pdf_page_bounds`
- Gate: PHP / PDF.

### T013

- File: `tests/Feature/PkpPdfTest.php`
- Test:
`test_multipage_pdf_keeps_all_activity_text_repeated_headers_and_frozen_identity`
- Gate: PHP / PDF.

### T014

- File: `tests/Feature/PkpDocumentInspectionTest.php`
- Test:
  `test_bootstrap_document_inspection_http_contract`
- Gate: PHP.

### T015

- File: `tests/Feature/PkpRevisionTest.php`
- Test:
  `test_bootstrap_content_correction_http_contract`
- Gate: PHP.

### T016

- File: `tests/Feature/PkpHistoryTest.php`
- Test:
`test_archived_signed_source_and_approved_attempt_remain_authorized_and_private`
- Gate: PHP.

### T017

- File: `tests/Integration/Pkp/PkpWorkflowRaceTest.php`
- Test:
  `test_duplicate_assessor_decisions_have_one_winner`
- Gate: SQL.

### T018

- File: `tests/Feature/PkpDocumentUploadTest.php`
- Test:
  `test_signed_pdf_over_ten_mib_is_rejected_at_the_http_boundary`
- Gate: PHP.

### T019

- File: `tests/Feature/PkpDocumentUploadTest.php`
- Test:
  `test_corrupt_pdf_is_rejected_and_staged_private_file_is_removed`
- Gate: PHP.

### T020

- File: `tests/Feature/PkpDocumentFailureTest.php`
- Test:
  `test_storage_write_failure_returns_503_without_losing_accepted_evidence`
- Gate: PHP.

### T021

- File: `tests/Feature/PkpDraftTest.php`
- Test:
  `test_unlinked_user_cannot_create_a_personal_draft`
- Gate: PHP.

### T022

- File: `tests/Feature/PkpActivityTest.php`
- Test:
  `test_manual_score_overrides_and_source_mode_changes_are_rejected`
- Gate: PHP.

### T023

- File: `tests/Feature/PkpRevisionTest.php`
- Test:
`test_returned_notes_edits_and_resubmission_preserve_the_original_attempt_snapshot`
- Gate: PHP.

### T024

- File: `tests/Feature/PkpRevisionTest.php`
- Test:
  `test_returned_numeric_changes_recalculate_only_the_new_submission`
- Gate: PHP.

### T025

- File: `tests/Feature/PkpActivityTest.php`
- Test:
  `test_exact_rating_boundaries_and_equal_indicator_weighting_through_http`
- Gate: PHP.

### T026

- File: `tests/Unit/PkpCalculatorTest.php`
- Test:
  `test_all_activities_contribute_and_indicators_are_equally_weighted`
- Gate: PHP.

### T027

- File: `tests/Feature/PkpExternalTest.php`
- Test:
`test_declared_signed_external_pdf_can_be_submitted_without_activity_or_score_data`
- Gate: PHP.

### T028

- File: `tests/Feature/PkpExternalTest.php`
- Test:
  `test_external_submission_requires_source_pdf`
- Gate: PHP.

### T029

- File: `tests/Feature/PkpHistoryTest.php`
- Test:
`test_returned_assessor_reads_only_its_frozen_attempt_and_source_not_live_owner_edits`
- Gate: PHP.

### T030

- File: `tests/Feature/PkpRevisionTest.php`
- Test:
`test_returned_external_replacement_resets_declaration_and_never_inherits_completeness`
- Gate: PHP.

### T031

- File: `tests/Feature/PkpExternalTest.php`
- Test:
  `test_approved_external_pdf_streams_the_exact_source_bytes_without_scores`
- Gate: PHP.

### T032

- File: `tests/Feature/PkpExternalTest.php`
- Test:
  `test_external_create_rejects_structured_activity_and_score_fields`
- Gate: PHP.

### T033

- File: `tests/Feature/PkpScoreDisplayTest.php`
- Test:
  `test_incomplete_draft_has_no_zero_score_or_predicate`
- Gate: PHP.

### T034

- File: `tests/Feature/PkpDraftTest.php`
- Test:
  `test_create_http_preselects_configured_assessor_and_returns_duplicate_link`
- Gate: PHP.

### T035

- File: `tests/Feature/PkpDraftTest.php`
- Test:
  `test_owner_can_complete_unset_assessor_and_notes_in_each_draft_source`
- Gate: PHP.

### T036

- File: `tests/Feature/PkpDraftTest.php`
- Test:
`test_draft_assessor_selection_rechecks_revoked_and_ineligible_options_without_partial_save`
- Gate: PHP.

### T037

- File: `tests/Feature/PkpRevisionTest.php`
- Test:
`test_changed_settings_preserve_submitted_assignment_and_reject_revoked_draft_selection`
- Gate: PHP.

### T038

- File: `tests/Feature/PkpAssessorSettingsTest.php`
- Test:
  `test_assessor_settings_http_routes_deny_missing_permission_and_foreign_owner`
- Gate: PHP.

### T039

- File: `tests/Feature/PkpAssessorSettingsTest.php`
- Test:
  `test_assessor_settings_rejects_default_outside_allowed_set`
- Gate: PHP.

### T040

- File: `tests/Feature/PkpAssessorSettingsTest.php`
- Test:
`test_assessor_settings_rejects_self_selection_duplicates_and_inactive_accounts`
- Gate: PHP.

### T041

- File: `tests/Unit/PkpCalculatorTest.php`
- Test:
  `test_activity_score_is_capped_but_realized_credit_uses_actual_quantity`
- Gate: PHP.

### T042

- File: `tests/Feature/PkpScoreDisplayTest.php`
- Test:
  `test_bootstrap_exact_score_detail_http_contract`
- Gate: PHP.

### T043

- File: `tests/Feature/PkpPdfTest.php`
- Test:
  `test_approved_pdf_contains_frozen_notes_and_distinct_exact_calculations`
- Gate: PHP / PDF.

### T044

- File: `tests/Unit/PkpCalculatorTest.php`
- Test:
  `test_raw_unrounded_score_selects_the_confirmed_rating_thresholds`
- Gate: PHP.

### T045

- File: `tests/Feature/PkpScoreDisplayTest.php`
- Test:
  `test_screen_exposes_distinct_indicator_and_activity_fractions`
- Gate: PHP.

### T046

- File: `tests/Feature/PkpFinalReplacementTest.php`
- Test:
  `test_replacement_preserves_accepted_a_until_b_is_inspected`
- Gate: PHP.

### T047

- File: `tests/Feature/PkpExternalTest.php`
- Test:
`test_unsigned_external_source_does_not_become_accepted_final_at_content_approval`
- Gate: PHP.

### T048

- File: `tests/Feature/PkpDocumentUploadTest.php`
- Test:
  `test_signed_final_service_persists_inspected_private_candidate`
- Gate: PHP.

### T049

- File: `tests/Integration/Pkp/PkpWorkflowRaceTest.php`
- Test:
  `test_duplicate_correction_creation_has_one_pending_revision`
- Gate: SQL.

### T050

- File: `e2e/pkp.spec.js`
- Test:
  `owner can edit metadata and start a correction from approved details`
- Gate: Browser.

### T051

- File: `tests/Feature/PkpDeadlineTest.php`
- Test:
  `test_deadline_is_inclusive_and_later_edits_do_not_reclassify_submissions`
- Gate: PHP.

### T052

- File: `tests/Feature/PkpRevisionTest.php`
- Test:
  `test_resubmission_preserves_exact_first_submission_and_deadline_bytes`
- Gate: PHP.

### T053

- File: `tests/Feature/PkpRevisionTest.php`
- Test:
  `test_source_mode_correction_starts_empty_and_preserves_base_evidence`
- Gate: PHP.

### T054

- File: `e2e/pkp.spec.js`
- Test:
  `identity refresh resubmits a frozen late record through real owner
  and admin sessions`
- Gate: Browser.

### T055

- File: `tests/Feature/PkpRevisionTest.php`
- Test:
  `test_missing_source_storage_prevents_first_submission_and_allows_recovery`
- Gate: PHP.

### T056

- File: `tests/Feature/PkpRevisionTest.php`
- Test:
  `test_future_period_returns_the_specified_business_code_and_field_feedback`
- Gate: PHP.

### T057

- File: `tests/Feature/PkpRevisionTest.php`
- Test:
  `test_exact_month_end_approval_boundary_applies_to_both_sources`
- Gate: PHP.

### T058

- File: `tests/Feature/PkpSubmissionTest.php`
- Test:
  `test_missing_identity_history_keeps_draft_unsubmitted`
- Gate: PHP.

### T059

- File: `tests/Unit/Services/PkpIdentityTest.php`
- Test:
  `test_resolves_history_effective_at_month_end_and_retains_source_ids`
- Gate: PHP.

### T060

- File: `tests/Feature/PkpRevisionTest.php`
- Test:
`test_missing_history_recovers_on_the_same_draft_after_effective_rows_are_added`
- Gate: PHP.

### T061

- File: `tests/Unit/Services/PkpIdentityTest.php`
- Test:
  `test_missing_or_conflicting_effective_histories_are_rejected`
- Gate: PHP.

### T062

- File: `tests/Feature/PkpRevisionTest.php`
- Test:
`test_changed_identity_withdraws_attempt_and_preserves_first_submission_classification`
- Gate: PHP.

### T063

- File: `tests/Integration/Pkp/PkpWorkflowRaceTest.php`
- Test:
  `test_identity_update_after_history_snapshot_does_not_mix_identity_versions`
- Gate: SQL.

### T064

- File: `tests/Integration/Pkp/PkpWorkflowRaceTest.php`
- Test:
`test_identity_read_includes_employee_update_committed_before_the_history_statement`
- Gate: SQL.

### T065

- File: `tests/Feature/PkpReassignmentTest.php`
- Test:
`test_content_reassignment_changes_current_authority_without_rewriting_attempt_history`
- Gate: PHP.

### T066

- File: `tests/Feature/PkpReassignmentTest.php`
- Test:
  `test_reassignment_rejects_unallowed_assessors_and_preserves_authority`
- Gate: PHP.

### T067

- File: `tests/Integration/Pkp/PkpWorkflowRaceTest.php`
- Test:
  `test_concurrent_decision_and_reassignment_revoke_old_authority_atomically`
- Gate: SQL.

### T068

- File: `tests/Feature/PkpReassignmentTest.php`
- Test:
`test_inspector_reassignment_preserves_content_approval_and_moves_document_authority`
- Gate: PHP.

### T069

- File: `tests/Feature/PkpDocumentInspectionTest.php`
- Test:
`test_acceptance_requires_content_match_confirmation_and_preserves_pending_candidate`
- Gate: PHP.

### T070

- File: `tests/Feature/PkpDocumentInspectionTest.php`
- Test:
`test_inspector_can_return_candidate_with_reason_without_changing_content_approval`
- Gate: PHP.

### T071

- File: `tests/Integration/Pkp/PkpWorkflowRaceTest.php`
- Test:
  `test_inspector_reassignment_races_with_old_inspector_decision`
- Gate: SQL.

### T072

- File: `tests/Integration/Pkp/PkpWorkflowRaceTest.php`
- Test:
  `test_document_acceptance_races_with_replacement_upload_without_losing_a`
- Gate: SQL.

### T073

- File: `tests/Feature/PkpFinalReplacementTest.php`
- Test:
  `test_returned_replacement_keeps_accepted_a`
- Gate: PHP.

### T074

- File: `tests/Feature/PkpFinalReplacementTest.php`
- Test:
  `test_replacement_declarations_are_required_and_rejection_preserves_a`
- Gate: PHP.

### T075

- File: `tests/Feature/PkpFinalReplacementTest.php`
- Test:
  `test_inspector_must_confirm_replacement_and_false_confirmation_keeps_a`
- Gate: PHP.

### T076

- File: `tests/Feature/PkpDocumentUploadTest.php`
- Test:
  `test_readable_pdf_at_exact_ten_mib_is_accepted`
- Gate: PHP.

### T077

- File: `tests/Unit/PkpPdfValidatorTest.php`
- Test:
  `test_rejects_corrupt_empty_zero_page_password_required_and_non_pdf_files`
- Gate: PHP.

### T078

- File: `tests/Feature/PkpDocumentUploadTest.php`
- Test:
  `test_parser_limits_return_422_and_preserve_the_accepted_document`
- Gate: PHP.

### T079

- File: `tests/Feature/PkpDocumentUploadTest.php`
- Test:
  `test_unavailable_parser_returns_503_without_binding_a_file`
- Gate: PHP.

### T080

- File: `tests/Unit/PkpPdfValidatorTest.php`
- Test:
  `test_inspects_real_pdf_structure_and_returns_actual_metadata`
- Gate: PHP.

### T081

- File: `tests/Unit/PkpPdfValidatorTest.php`
- Test:
  `test_supported_validator_versions_have_no_local_major_assumption`
- Gate: PHP.

### T082

- File: `tests/Feature/PkpActivityTest.php`
- Test:
  `test_invalid_bounds_and_unsupported_numeric_forms_preserve_the_original_tree`
- Gate: PHP.

### T083

- File: `tests/Integration/Pkp/PkpWorkflowRaceTest.php`
- Test:
`test_maximum_tree_text_and_numbers_round_trip_exactly_on_the_deployment_engine`
- Gate: SQL.

### T084

- File: `tests/Feature/PkpActivityTest.php`
- Test:
  `test_ineligible_actor_cannot_mutate_an_owned_pkp`
- Gate: PHP.

### T085

- File: `tests/Feature/PkpPdfTest.php`
- Test:
  `test_maximum_unbroken_notes_wrap_without_losing_characters`
- Gate: PHP / PDF.

### T086

- File: `tests/Feature/PkpPdfTest.php`
- Test:
  `test_draft_cannot_be_printed`
- Gate: PHP / PDF.

### T087

- File: `tests/Feature/PkpPdfTest.php`
- Test:
`test_archived_approved_application_remains_printable_only_by_authorized_actors`
- Gate: PHP / PDF.

### T088

- File: `tests/Feature/PkpDocumentFailureTest.php`
- Test:
`test_failed_replacement_metadata_insert_preserves_accepted_a_and_removes_staging_b`
- Gate: PHP.

### T089

- File: `tests/Integration/Pkp/PkpWorkflowRaceTest.php`
- Test:
  `test_deadline_and_first_submission_commit_one_coherent_snapshot`
- Gate: SQL.

### T090

- File: `tests/Feature/PkpAssessorSettingsTest.php`
- Test:
  `test_unlinked_admin_can_read_and_save_assessor_settings_over_http`
- Gate: PHP.

### T091

- File: `tests/Feature/PkpDeadlineTest.php`
- Test:
`test_unlinked_admin_can_configure_and_read_back_deadline_through_http_and_blade`
- Gate: PHP.

### T092

- File: `tests/Feature/PkpDraftTest.php`
- Test:
  `test_monthly_list_defaults_to_owner_and_all_scope_requires_view_permission`
- Gate: PHP.

### T093

- File: `tests/Feature/PkpDraftTest.php`
- Test:
  `test_owner_assessor_options_returns_only_the_owners_eligible_allowlist`
- Gate: PHP.

### T094

- File: `e2e/pkp.spec.js`
- Test:
  `ordinary owner can open the monthly PKP form on desktop`
- Gate: Browser.

### T095

- File: `e2e/pkp.spec.js`
- Test:
  `ordinary owner can open the monthly PKP form from mobile navigation`
- Gate: Browser.

### T096

- File: `e2e/pkp.spec.js`
- Test:
  `owner can create and edit an ordered activity tree`
- Gate: Browser.

### T097

- File: `tests/Feature/PkpAssessorSettingsTest.php`
- Test:
`test_assessor_settings_rejects_a_stale_zero_version_without_changing_configuration`
- Gate: PHP.

### T098

- File: `tests/Feature/PkpRevisionTest.php`
- Test:
  `test_all_mutation_routes_reject_nonpositive_versions_without_side_effects`
- Gate: PHP.

### T099

- File: `tests/Integration/Pkp/PkpUploadEnvelopeTest.php`
- Test:
  `test_authenticated_source_upload_crosses_nginx_php_and_application_limits`
- Gate: Envelope.

### T100

- File: `tests/Integration/Pkp/PkpWorkflowRaceTest.php`
- Test:
  `test_two_connections_use_the_selected_supported_engine`
- Gate: SQL.

### T101

- File: `tests/Unit/PkpPdfValidatorTest.php`
- Test:
  `test_missing_or_unsupported_qpdf_is_a_runtime_readiness_failure`
- Gate: PHP.

### T102

- File: `e2e/pkp.spec.js`
- Test:
  `owner can submit an externally signed PKP and print its original PDF after
approval`
- Gate: Browser.

## Readiness

All 90 criteria are mapped with direct realized functional evidence;
the authorized baseline rollback repair passes both local engine gates.
Readiness: **98/100** (Completeness 40/40, Clarity 29/30, Alignment 29/30).
The score retains original RED-provenance/hosted-CI limits; it does not
authorize production migration, release, staging, or commits.
