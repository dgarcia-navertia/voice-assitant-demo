<?php

namespace Tests\Unit;

use App\Services\ScheduleFormInput;
use PHPUnit\Framework\TestCase;

class ScheduleFormInputTest extends TestCase
{
    // --- parseRotationLength ------------------------------------------------

    public function test_parses_numeric_string(): void
    {
        $this->assertSame(3, ScheduleFormInput::parseRotationLength('3'));
    }

    public function test_non_numeric_input_falls_back_to_one(): void
    {
        $this->assertSame(1, ScheduleFormInput::parseRotationLength('abc'));
    }

    public function test_null_input_falls_back_to_one(): void
    {
        $this->assertSame(1, ScheduleFormInput::parseRotationLength(null));
    }

    // --- validate ------------------------------------------------------------

    public function test_length_one_without_anchor_is_valid(): void
    {
        $this->assertNull(ScheduleFormInput::validate(1, null));
    }

    public function test_length_above_max_is_invalid(): void
    {
        $this->assertNotNull(ScheduleFormInput::validate(7, '2026-07-13'));
    }

    public function test_length_below_min_is_invalid(): void
    {
        $this->assertNotNull(ScheduleFormInput::validate(0, null));
    }

    public function test_length_greater_than_one_requires_anchor(): void
    {
        $error = ScheduleFormInput::validate(3, null);
        $this->assertNotNull($error);
        $this->assertIsString($error);
    }

    public function test_length_greater_than_one_with_blank_anchor_is_invalid(): void
    {
        $this->assertNotNull(ScheduleFormInput::validate(3, '   '));
    }

    public function test_length_greater_than_one_with_anchor_is_valid(): void
    {
        $this->assertNull(ScheduleFormInput::validate(3, '2026-07-13'));
    }

    public function test_malformed_anchor_date_is_invalid(): void
    {
        $this->assertNotNull(ScheduleFormInput::validate(3, '13-07-2026'));
    }

    // --- parseWeeks ------------------------------------------------------------

    public function test_parses_weeks_within_rotation_length(): void
    {
        $input = [
            0 => ['monday' => '09:00-14:00', 'tuesday' => ''],
            1 => ['monday' => '10:00-13:00'],
        ];

        $weeks = ScheduleFormInput::parseWeeks($input, 2);

        $this->assertSame('09:00-14:00', $weeks[0]['monday']);
        $this->assertNull($weeks[0]['tuesday']);
        $this->assertSame('10:00-13:00', $weeks[1]['monday']);
        $this->assertNull($weeks[1]['sunday']);
    }

    public function test_drops_weeks_beyond_rotation_length(): void
    {
        $input = [
            0 => ['monday' => '09:00-14:00'],
            1 => ['monday' => '10:00-13:00'],
            2 => ['monday' => '11:00-12:00'],
        ];

        $weeks = ScheduleFormInput::parseWeeks($input, 2);

        $this->assertCount(2, $weeks);
        $this->assertArrayNotHasKey(2, $weeks);
    }

    public function test_missing_week_defaults_to_all_days_off(): void
    {
        $weeks = ScheduleFormInput::parseWeeks([], 1);

        $this->assertCount(1, $weeks);
        foreach ($weeks[0] as $value) {
            $this->assertNull($value);
        }
    }

    public function test_whitespace_only_day_value_becomes_null(): void
    {
        $input = [0 => ['monday' => '   ']];

        $weeks = ScheduleFormInput::parseWeeks($input, 1);

        $this->assertNull($weeks[0]['monday']);
    }
}
