<?php

namespace Lunar\ProductImport\Enums;

/**
 * The lifecycle of one import, with the allowed transitions in one place.
 */
enum ImportStatus: string
{
    case Uploaded = 'uploaded';
    case Analyzing = 'analyzing';
    case AnalysisFailed = 'analysis_failed';
    case AwaitingMapping = 'awaiting_mapping';
    case Staging = 'staging';
    case ReviewingValues = 'reviewing_values';
    case PreviewReady = 'preview_ready';
    case Importing = 'importing';
    case Completed = 'completed';
    case CompletedWithErrors = 'completed_with_errors';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    /**
     * The statuses this status may move to.
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Uploaded => [self::Analyzing, self::AwaitingMapping, self::Failed, self::Cancelled],
            self::Analyzing => [self::AwaitingMapping, self::AnalysisFailed, self::Cancelled],
            self::AnalysisFailed => [self::Analyzing, self::AwaitingMapping, self::Staging, self::Cancelled],
            self::AwaitingMapping => [self::Analyzing, self::AnalysisFailed, self::Staging, self::Cancelled],
            self::Staging => [self::ReviewingValues, self::PreviewReady, self::AwaitingMapping, self::Cancelled],
            self::ReviewingValues => [self::AwaitingMapping, self::PreviewReady, self::Cancelled],
            self::PreviewReady => [self::AwaitingMapping, self::ReviewingValues, self::Importing, self::Cancelled],
            self::Importing => [self::Completed, self::CompletedWithErrors, self::Failed],
            // Back to importing for a retry of failed rows or failed images.
            self::Completed, self::CompletedWithErrors, self::Failed => [self::Importing],
            self::Cancelled => [],
        };
    }

    /**
     * Whether this status may move to the given one.
     */
    public function canTransitionTo(self $status): bool
    {
        return in_array($status, $this->allowedTransitions(), true);
    }

    /**
     * Whether background work runs, so changes from staff are refused.
     */
    public function isBusy(): bool
    {
        return in_array($this, [self::Analyzing, self::Staging, self::Importing], true);
    }

    /**
     * Whether staff may still cancel: every status before importing.
     */
    public function isCancellable(): bool
    {
        return in_array($this, self::cancellable(), true);
    }

    /**
     * The statuses that can still be cancelled.
     *
     * @return list<self>
     */
    public static function cancellable(): array
    {
        return [
            self::Uploaded,
            self::Analyzing,
            self::AnalysisFailed,
            self::AwaitingMapping,
            self::Staging,
            self::ReviewingValues,
            self::PreviewReady,
        ];
    }

    /**
     * The translated label.
     */
    public function label(): string
    {
        return __('lunarpanel.product-import::import.status.'.$this->value);
    }

    /**
     * The Filament badge color.
     */
    public function color(): string
    {
        return match ($this) {
            self::Completed => 'success',
            self::CompletedWithErrors => 'warning',
            self::Failed, self::AnalysisFailed => 'danger',
            self::Cancelled => 'gray',
            self::Analyzing, self::Staging, self::Importing, self::Uploaded => 'info',
            default => 'primary',
        };
    }
}
