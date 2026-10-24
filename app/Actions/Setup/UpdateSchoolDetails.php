<?php

declare(strict_types=1);

namespace App\Actions\Setup;

use App\Models\School;

final class UpdateSchoolDetails
{
    /**
     * @param  array{name: string, country: string, timezone: string, locale: string, currency: string}  $data
     */
    public function handle(School $school, array $data): School
    {
        $school->update($data);

        return $school;
    }
}
