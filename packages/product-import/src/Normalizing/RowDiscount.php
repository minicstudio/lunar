<?php

namespace Lunar\ProductImport\Normalizing;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;
use Lunar\ProductImport\Data\NormalizedRow;
use Lunar\ProductImport\Enums\TargetField;

/**
 * A row's discount: a percent, or an amount in the default currency's minor
 * units, off the row's price, with its start and end (`Y-m-d H:i:s`, an
 * empty bound is open). Its dates are already whole days when the column's
 * date format has no time (`DateParser`). Rows with the same kind, value,
 * start, and end share one discount value in the review.
 */
final readonly class RowDiscount
{
    public const PERCENT = 'percent';

    public const AMOUNT = 'amount';

    /**
     * @param  string  $kind  `percent` or `amount`.
     * @param  int|float  $value  The percent, or the amount in minor units.
     */
    public function __construct(
        public string $kind,
        public int|float $value,
        public ?string $startsAt = null,
        public ?string $endsAt = null,
    ) {}

    /**
     * The row's discount, or null when it gives none, or one that cannot be
     * imported: both an amount and a percent, a percent above 100, or a
     * start after its end. The validator reports those.
     */
    public static function for(NormalizedRow $row): ?self
    {
        $amount = $row->get(TargetField::VariantDiscountAmount->value);
        $percent = $row->get(TargetField::VariantDiscountPercent->value);
        $hasAmount = is_int($amount) && $amount > 0;
        $hasPercent = is_numeric($percent) && (float) $percent > 0;

        if ($hasAmount === $hasPercent) {
            return null;
        }

        $starts = $row->get(TargetField::VariantSaleStarts->value);
        $ends = $row->get(TargetField::VariantSaleEnds->value);

        $discount = new self(
            $hasPercent ? self::PERCENT : self::AMOUNT,
            $hasPercent ? round((float) $percent, 4) : (int) $amount,
            is_string($starts) && $starts !== '' ? $starts : null,
            is_string($ends) && $ends !== '' ? $ends : null,
        );

        return $discount->isValid() ? $discount : null;
    }

    /**
     * Rebuild a discount from its stored details.
     *
     * @param  ?array<string, mixed>  $details
     */
    public static function fromArray(?array $details): ?self
    {
        $kind = $details['kind'] ?? null;
        $value = $details['value'] ?? null;

        if (! in_array($kind, [self::PERCENT, self::AMOUNT], true) || ! is_numeric($value)) {
            return null;
        }

        return new self(
            $kind,
            $kind === self::AMOUNT ? (int) $value : round((float) $value, 4),
            filled($details['starts_at'] ?? null) ? (string) $details['starts_at'] : null,
            filled($details['ends_at'] ?? null) ? (string) $details['ends_at'] : null,
        );
    }

    /**
     * The stored details.
     *
     * @return array{kind: string, value: int|float, starts_at: ?string, ends_at: ?string}
     */
    public function toArray(): array
    {
        return [
            'kind' => $this->kind,
            'value' => $this->kind === self::AMOUNT ? (int) $this->value : (float) $this->value,
            'starts_at' => $this->startsAt,
            'ends_at' => $this->endsAt,
        ];
    }

    /**
     * Whether the discount can be imported: a percent up to 100, an amount
     * or percent above zero, and a start no later than its end.
     */
    public function isValid(): bool
    {
        return $this->value > 0
            && ($this->kind === self::AMOUNT || $this->value <= 100)
            && ($this->startsAt === null || $this->endsAt === null || $this->startsAt <= $this->endsAt);
    }

    /**
     * Whether the discount ended before the given moment (now by default).
     */
    public function hasEnded(?CarbonInterface $now = null): bool
    {
        return $this->endsAt !== null && $this->ends()->lessThan($now ?? CarbonImmutable::now());
    }

    /**
     * Whether a fixed amount leaves less of the price than the shop's
     * minimum remaining share, so the shop does not apply it.
     *
     * @param  int  $price  The price in minor units.
     */
    public function leavesTooLittleOf(int $price): bool
    {
        if ($this->kind !== self::AMOUNT) {
            return false;
        }

        $remaining = $price - (int) $this->value;
        $minimum = (int) round($price * self::minimumRemainingPercentage() / 100);

        return $remaining < 0 || $remaining < $minimum;
    }

    /**
     * The share of a price (in percent) that a fixed amount must leave.
     */
    public static function minimumRemainingPercentage(): float
    {
        return (float) config('lunar.discounts.fixed_value_minimum_remaining_percentage', 10);
    }

    /**
     * The key rows with the same discount share: `percent:30|<start>|<end>`,
     * an empty bound staying empty.
     */
    public function key(): string
    {
        return $this->kind.':'.$this->valueText().'|'.($this->startsAt ?? '').'|'.($this->endsAt ?? '');
    }

    /**
     * The value without trailing zeros: the percent, or the amount in minor units.
     */
    public function valueText(): string
    {
        if ($this->kind === self::AMOUNT) {
            return (string) (int) $this->value;
        }

        return Str::rtrim(Str::rtrim(number_format((float) $this->value, 4, '.', ''), '0'), '.');
    }

    /**
     * The discount in words, for example "30% · until 31 Dec 2026".
     *
     * @param  int  $currencyFactor  The default currency's minor units per unit.
     */
    public function describe(int $currencyFactor = 100, ?string $currencyCode = null): string
    {
        $value = $this->kind === self::PERCENT
            ? __('lunarpanel.product-import::ui.discounts.percent', ['value' => $this->valueText()])
            : Str::trim(__('lunarpanel.product-import::ui.discounts.amount', [
                'value' => number_format((int) $this->value / max(1, $currencyFactor), (int) round(log10(max(1, $currencyFactor))), '.', ''),
                'currency' => (string) $currencyCode,
            ]));

        $dates = match (true) {
            $this->startsAt !== null && $this->endsAt !== null => __('lunarpanel.product-import::ui.discounts.from_until', ['start' => $this->date($this->starts(), true), 'end' => $this->date($this->ends(), false)]),
            $this->startsAt !== null => __('lunarpanel.product-import::ui.discounts.from', ['date' => $this->date($this->starts(), true)]),
            $this->endsAt !== null => __('lunarpanel.product-import::ui.discounts.until', ['date' => $this->date($this->ends(), false)]),
            default => null,
        };

        return $dates === null ? $value : $value.' · '.$dates;
    }

    /**
     * The start, when the discount has one.
     */
    public function starts(): ?CarbonImmutable
    {
        return $this->startsAt !== null ? CarbonImmutable::parse($this->startsAt) : null;
    }

    /**
     * The end, when the discount has one.
     */
    public function ends(): ?CarbonImmutable
    {
        return $this->endsAt !== null ? CarbonImmutable::parse($this->endsAt) : null;
    }

    /**
     * A bound as a day, with its time only when it is not the start of the
     * day for a start or the end of the day for an end.
     */
    protected function date(CarbonImmutable $date, bool $isStart): string
    {
        $wholeDay = $date->format('H:i:s') === ($isStart ? '00:00:00' : '23:59:59');

        return $date->locale(app()->getLocale())->translatedFormat($wholeDay ? 'j M Y' : 'j M Y H:i');
    }
}
