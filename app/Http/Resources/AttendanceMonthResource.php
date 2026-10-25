<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Actions\Family\BuildAttendanceMonth;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A child's attendance in one month (BuildAttendanceMonth). `status` per day is present,
 * absent, late, excused, or null where no attendance was taken.
 *
 * @phpstan-import-type AttendanceMonth from BuildAttendanceMonth
 *
 * @property AttendanceMonth $resource
 */
class AttendanceMonthResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'month' => $this->resource['month'],
            'previous_month' => $this->resource['previous'],
            'next_month' => $this->resource['next'],
            'days' => $this->resource['days'],
            'totals' => $this->resource['totals'],
            'recorded_days' => $this->resource['recorded'],
            'attendance_rate' => $this->resource['rate'],
        ];
    }
}
