<?php

declare(strict_types=1);

namespace App\Enums;

enum AssessmentType: string
{
    case Test = 'test';
    case Exam = 'exam';
    case Assignment = 'assignment';

    public function label(): string
    {
        return match ($this) {
            self::Test => 'Test',
            self::Exam => 'Exam',
            self::Assignment => 'Assignment',
        };
    }

    /**
     * The weight a new assessment of this type starts with; teachers can change it.
     */
    public function defaultWeight(): string
    {
        return match ($this) {
            self::Test => '1.00',
            self::Exam => '2.00',
            self::Assignment => '0.50',
        };
    }
}
