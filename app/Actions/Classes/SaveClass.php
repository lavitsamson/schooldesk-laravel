<?php

declare(strict_types=1);

namespace App\Actions\Classes;

use App\Models\SchoolClass;

/**
 * Creates a class in an academic year, or updates an existing one. A class never moves
 * between years: promotion enrols students in next year's classes instead.
 */
final class SaveClass
{
    /**
     * @param  array{academic_year_id?: int, name: string, section?: string|null, class_teacher_id?: int|null}  $data
     */
    public function handle(?SchoolClass $class, array $data): SchoolClass
    {
        $class ??= new SchoolClass(['academic_year_id' => $data['academic_year_id'] ?? null]);

        $class->fill([
            'name' => trim($data['name']),
            'section' => trim((string) ($data['section'] ?? '')) ?: null,
            'class_teacher_id' => $data['class_teacher_id'] ?? null,
        ])->save();

        return $class;
    }
}
