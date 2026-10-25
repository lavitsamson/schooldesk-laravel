<?php

declare(strict_types=1);

namespace App\Actions\Family;

use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * A parent's children in the current school, by first name, with their current class. Found
 * through the parent's guardian records (Student::childrenOf), never by id from the request.
 */
final class ListChildren
{
    /**
     * The query, for the API to paginate.
     *
     * @return Builder<Student>
     */
    public function query(User $parent): Builder
    {
        return Student::query()
            ->childrenOf($parent)
            ->with('currentEnrollment.schoolClass')
            ->orderBy('first_name')
            ->orderBy('id');
    }

    /**
     * @return Collection<int, Student>
     */
    public function handle(User $parent): Collection
    {
        return $this->query($parent)->get();
    }
}
