<?php

use Lunar\ProductImport\Enums\ImportStatus;

/**
 * The full transition table: every status and the statuses it may move to.
 *
 * @return array<string, list<ImportStatus>>
 */
function importStatusTransitions(): array
{
    return [
        'uploaded' => [ImportStatus::Analyzing, ImportStatus::AwaitingMapping, ImportStatus::Failed, ImportStatus::Cancelled],
        'analyzing' => [ImportStatus::AwaitingMapping, ImportStatus::AnalysisFailed, ImportStatus::Cancelled],
        'analysis_failed' => [ImportStatus::Analyzing, ImportStatus::AwaitingMapping, ImportStatus::Cancelled],
        'awaiting_mapping' => [ImportStatus::Analyzing, ImportStatus::AnalysisFailed, ImportStatus::Staging, ImportStatus::Cancelled],
        'staging' => [ImportStatus::ReviewingValues, ImportStatus::PreviewReady, ImportStatus::AwaitingMapping, ImportStatus::Cancelled],
        'reviewing_values' => [ImportStatus::AwaitingMapping, ImportStatus::PreviewReady, ImportStatus::Cancelled],
        'preview_ready' => [ImportStatus::AwaitingMapping, ImportStatus::ReviewingValues, ImportStatus::Importing, ImportStatus::Cancelled],
        'importing' => [ImportStatus::Completed, ImportStatus::CompletedWithErrors, ImportStatus::Failed],
        // A retry of failed images moves a completed import back to importing.
        'completed' => [ImportStatus::Importing],
        'completed_with_errors' => [ImportStatus::Importing],
        'failed' => [ImportStatus::Importing],
        'cancelled' => [],
    ];
}

/**
 * Every (from, to) pair with whether it is allowed.
 *
 * @return array<string, array{ImportStatus, ImportStatus, bool}>
 */
function importStatusPairs(): array
{
    $pairs = [];

    foreach (importStatusTransitions() as $from => $allowed) {
        foreach (ImportStatus::cases() as $to) {
            $isAllowed = in_array($to, $allowed, true);
            $pairs["{$from} → {$to->value} ".($isAllowed ? 'allowed' : 'refused')] = [ImportStatus::from($from), $to, $isAllowed];
        }
    }

    return $pairs;
}

test('there are twelve statuses', function () {
    expect(ImportStatus::cases())->toHaveCount(12)
        ->and(array_keys(importStatusTransitions()))->toBe(array_map(fn (ImportStatus $status) => $status->value, ImportStatus::cases()));
});

test('a transition is allowed or refused', function (ImportStatus $from, ImportStatus $to, bool $allowed) {
    expect($from->canTransitionTo($to))->toBe($allowed);
})->with(importStatusPairs());

test('analyzing, staging, and importing are busy', function () {
    $busy = array_values(array_filter(ImportStatus::cases(), fn (ImportStatus $status) => $status->isBusy()));

    expect($busy)->toBe([ImportStatus::Analyzing, ImportStatus::Staging, ImportStatus::Importing]);
});

test('every status before importing is cancellable', function () {
    $cancellable = array_values(array_filter(ImportStatus::cases(), fn (ImportStatus $status) => $status->isCancellable()));

    expect($cancellable)->toBe([
        ImportStatus::Uploaded,
        ImportStatus::Analyzing,
        ImportStatus::AnalysisFailed,
        ImportStatus::AwaitingMapping,
        ImportStatus::Staging,
        ImportStatus::ReviewingValues,
        ImportStatus::PreviewReady,
    ]);

    foreach (ImportStatus::cases() as $status) {
        expect($status->canTransitionTo(ImportStatus::Cancelled))->toBe($status->isCancellable());
    }
});
