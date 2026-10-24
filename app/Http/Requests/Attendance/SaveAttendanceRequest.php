<?php

declare(strict_types=1);

namespace App\Http\Requests\Attendance;

use App\Enums\AttendanceStatus;
use App\Models\AttendanceRecord;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Support\AttendanceDates;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * PUT /attendance/{schoolClass}/{date}: the whole grid at once.
 */
class SaveAttendanceRequest extends FormRequest
{
    /**
     * More than any class has; a guard against oversized requests.
     */
    public const MAX_STUDENTS = 200;

    public function authorize(): bool
    {
        return $this->user()?->can('take', [AttendanceRecord::class, $this->schoolClass()]) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'records' => ['required', 'array', 'min:1', 'max:'.self::MAX_STUDENTS],
            'records.*.student_id' => ['required', 'integer', 'distinct'],
            'records.*.status' => ['required', Rule::enum(AttendanceStatus::class)],
            'records.*.note' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * The date must be one attendance can be taken for, and every student must be in the
     * class on that day: one query for all of them, and ids of another school are not found.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $problem = AttendanceDates::problem($this->schoolClass(), $this->attendanceDate());

                if ($problem !== null) {
                    $validator->errors()->add('date', $problem);
                }

                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $ids = array_column($this->entries(), 'student_id');
                $enrolled = Enrollment::query()
                    ->where('class_id', $this->schoolClass()->id)
                    ->activeOn($this->attendanceDate())
                    ->whereIn('student_id', $ids)
                    ->count();

                if ($enrolled !== count($ids)) {
                    $validator->errors()->add('records', 'Some students are not in this class on that day. Reload the page and try again.');
                }
            },
        ];
    }

    public function schoolClass(): SchoolClass
    {
        /** @var SchoolClass */
        return $this->route('schoolClass');
    }

    /**
     * The route only matches dddd-dd-dd, so this fails only for impossible dates (02-31).
     */
    public function attendanceDate(): CarbonImmutable
    {
        return AttendanceDates::parse((string) $this->route('date')) ?? abort(404);
    }

    /**
     * @return list<array{student_id: int, status: AttendanceStatus, note: string|null}>
     */
    public function entries(): array
    {
        /** @var array<int, array{student_id: int|string, status: string, note?: string|null}> $records */
        $records = (array) $this->input('records', []);

        return array_values(array_map(fn (array $record): array => [
            'student_id' => (int) $record['student_id'],
            'status' => AttendanceStatus::from($record['status']),
            'note' => isset($record['note']) && trim($record['note']) !== '' ? trim($record['note']) : null,
        ], $records));
    }
}
