<?php

declare(strict_types=1);

namespace App\Services\Workflow;

use Illuminate\Support\Carbon;

class CronExpression
{
    public function isDue(string $expression, Carbon $time): bool
    {
        $parts = preg_split('/\s+/', trim($expression)) ?: [];

        if (count($parts) === 6) {
            array_shift($parts);
        }

        if (count($parts) !== 5) {
            return false;
        }

        [$minute, $hour, $day, $month, $weekday] = $parts;

        return $this->matches($minute, (int) $time->format('i'), 0, 59)
            && $this->matches($hour, (int) $time->format('G'), 0, 23)
            && $this->matches($day, (int) $time->format('j'), 1, 31)
            && $this->matches($month, (int) $time->format('n'), 1, 12)
            && $this->matches($weekday, (int) $time->format('w'), 0, 7);
    }

    private function matches(string $field, int $value, int $min, int $max): bool
    {
        foreach (explode(',', $field) as $segment) {
            if ($this->segmentMatches($segment, $value, $min, $max)) {
                return true;
            }
        }

        return false;
    }

    private function segmentMatches(string $segment, int $value, int $min, int $max): bool
    {
        [$range, $step] = array_pad(explode('/', $segment, 2), 2, '1');
        $stepValue = max(1, (int) $step);

        if ($range === '*') {
            return (($value - $min) % $stepValue) === 0;
        }

        if (str_contains($range, '-')) {
            [$start, $end] = array_map('intval', explode('-', $range, 2));

            return $value >= max($min, $start)
                && $value <= min($max, $end)
                && (($value - $start) % $stepValue) === 0;
        }

        $target = (int) $range;
        if ($max === 7 && $target === 7) {
            $target = 0;
        }

        return $value === $target;
    }
}
