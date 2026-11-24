<?php

namespace App\Shared\Support\Users;

use App\Enums\GratitudeReminderFrequency;
use Illuminate\Support\Carbon;

/**
 * A member's Gratitude Journal email reminder preference: On/Off plus how
 * many reminders per week (1–7). Stored inside the existing
 * UserPreference.preferences JSON blob (no new table or column) as:
 *
 *   gratitude_reminders_enabled  bool
 *   gratitude_reminders_per_week int 1..7 — kept while Off, so switching
 *                                back On restores the member's last choice
 *   gratitude_reminder_frequency legacy 'daily'|'weekly'|'none' — still
 *                                written, as a best-effort mirror, so a code
 *                                rollback keeps every member's reminders
 *                                at or below what they chose
 *
 * A blob that predates this (only the legacy key) is read through the
 * legacy mapping — Daily → On/7, Weekly → On/1, None → Off — so a row that
 * the 2026_11_24_100000 data migration has not reached yet behaves exactly
 * the same. No row at all keeps the original client-confirmed default:
 * On, 7 per week (the old Daily default).
 */
final readonly class GratitudeReminderPreference
{
    public const DEFAULT_PER_WEEK = 7;

    public const KEY_ENABLED = 'gratitude_reminders_enabled';

    public const KEY_PER_WEEK = 'gratitude_reminders_per_week';

    public const KEY_LEGACY_FREQUENCY = 'gratitude_reminder_frequency';

    /**
     * Fixed ISO weekdays (1 = Monday … 7 = Sunday) on which each weekly
     * frequency is sent — deterministic, never randomised, spread so no
     * gap is longer than it needs to be. Every schedule includes Monday,
     * which is the day the old Weekly option always used.
     *
     * @var array<int, list<int>>
     */
    public const SCHEDULE = [
        1 => [1],                   // Mon
        2 => [1, 4],                // Mon, Thu
        3 => [1, 3, 5],             // Mon, Wed, Fri
        4 => [1, 3, 5, 7],          // Mon, Wed, Fri, Sun
        5 => [1, 2, 4, 5, 6],       // Mon, Tue, Thu, Fri, Sat
        6 => [1, 2, 3, 4, 5, 6],    // Mon–Sat
        7 => [1, 2, 3, 4, 5, 6, 7], // every day
    ];

    public int $perWeek;

    public function __construct(public bool $enabled, int $perWeek = self::DEFAULT_PER_WEEK)
    {
        $this->perWeek = self::isValidPerWeek($perWeek) ? $perWeek : self::DEFAULT_PER_WEEK;
    }

    public static function isValidPerWeek(mixed $value): bool
    {
        return is_numeric($value) && (int) $value == $value && (int) $value >= 1 && (int) $value <= 7;
    }

    /**
     * @param  array<string, mixed>|null  $stored  the UserPreference.preferences blob
     */
    public static function fromStored(?array $stored): self
    {
        $stored ??= [];

        if (array_key_exists(self::KEY_ENABLED, $stored)) {
            $perWeek = $stored[self::KEY_PER_WEEK] ?? null;

            return new self(
                (bool) $stored[self::KEY_ENABLED],
                self::isValidPerWeek($perWeek) ? (int) $perWeek : self::DEFAULT_PER_WEEK,
            );
        }

        return self::fromLegacy(GratitudeReminderFrequency::tryFrom((string) ($stored[self::KEY_LEGACY_FREQUENCY] ?? '')));
    }

    public static function fromLegacy(?GratitudeReminderFrequency $legacy): self
    {
        return match ($legacy) {
            GratitudeReminderFrequency::Weekly => new self(true, 1),
            GratitudeReminderFrequency::None => new self(false),
            // Daily, or nothing stored yet — the existing Daily default.
            default => new self(true, 7),
        };
    }

    /**
     * The keys to merge into the preferences blob.
     *
     * @return array<string, mixed>
     */
    public function toStored(): array
    {
        return [
            self::KEY_ENABLED => $this->enabled,
            self::KEY_PER_WEEK => $this->perWeek,
            self::KEY_LEGACY_FREQUENCY => $this->legacyEquivalent()->value,
        ];
    }

    /**
     * The closest old-style value that never sends more than this
     * preference does (2–6 per week fall back to Weekly).
     */
    public function legacyEquivalent(): GratitudeReminderFrequency
    {
        return match (true) {
            ! $this->enabled => GratitudeReminderFrequency::None,
            $this->perWeek === 7 => GratitudeReminderFrequency::Daily,
            default => GratitudeReminderFrequency::Weekly,
        };
    }

    /**
     * @return list<int> ISO weekdays
     */
    public function scheduledDays(): array
    {
        return self::SCHEDULE[$this->perWeek];
    }

    public function isScheduledOn(Carbon $day): bool
    {
        return $this->enabled && in_array($day->dayOfWeekIso, $this->scheduledDays(), true);
    }

    public static function optionLabel(int $perWeek): string
    {
        return match ($perWeek) {
            1 => '1 reminder per week',
            7 => '7 reminders per week (Daily)',
            default => "{$perWeek} reminders per week",
        };
    }

    /**
     * Fills the existing template's "This is your {{frequency_label}}
     * reminder" sentence. Daily/Weekly are unchanged from before, so
     * migrated members get exactly the same email.
     */
    public function emailLabel(): string
    {
        return match ($this->perWeek) {
            7 => 'Daily',
            1 => 'Weekly',
            2 => 'Twice-Weekly',
            3 => 'Three-Times-Weekly',
            4 => 'Four-Times-Weekly',
            5 => 'Five-Times-Weekly',
            6 => 'Six-Times-Weekly',
        };
    }
}
