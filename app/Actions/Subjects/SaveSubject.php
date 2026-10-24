<?php

declare(strict_types=1);

namespace App\Actions\Subjects;

use App\Models\Subject;
use Illuminate\Support\Str;

final class SaveSubject
{
    /**
     * @param  array{name: string, code: string}  $data
     */
    public function handle(?Subject $subject, array $data): Subject
    {
        $subject ??= new Subject;

        $subject->fill([
            'name' => trim($data['name']),
            'code' => Str::upper(trim($data['code'])),
        ])->save();

        return $subject;
    }
}
