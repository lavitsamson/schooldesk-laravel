<?php

declare(strict_types=1);

namespace App\Actions\Guardians;

use App\Models\Guardian;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

final class ListGuardians
{
    public const PER_PAGE = 25;

    /**
     * @return LengthAwarePaginator<int, Guardian>
     */
    public function handle(string $search): LengthAwarePaginator
    {
        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_strtolower(trim($search))).'%';

        return Guardian::query()
            ->with(['students' => fn ($q) => $q->orderBy('first_name')])
            ->when(trim($search) !== '', fn (Builder $q) => $q->where(fn (Builder $q) => $q
                ->whereRaw('lower(name) like ?', [$like])
                ->orWhereRaw('lower(email) like ?', [$like])
                ->orWhereRaw('lower(phone) like ?', [$like])))
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();
    }
}
