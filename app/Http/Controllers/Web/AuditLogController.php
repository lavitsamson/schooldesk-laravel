<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Actions\AuditLog\ListActivities;
use App\Enums\ActivityArea;
use App\Http\Controllers\Controller;
use App\Http\Requests\AuditLog\ListActivitiesRequest;
use App\Http\Resources\ActivityResource;
use App\Models\Activity;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The school's audit log for school admins: changes to students, marks, invoices, payments
 * and report cards, and data protection actions, filterable by area, author, date and student.
 */
class AuditLogController extends Controller
{
    public function index(ListActivitiesRequest $request, ListActivities $action): Response
    {
        $student = $request->student();
        $filters = $request->filters($student);

        return Inertia::render('AuditLog/Index', [
            'activities' => ActivityResource::collection($action->handle($filters)),
            'filters' => $filters,
            'student' => $student === null ? null : ['id' => $student->id, 'name' => $student->fullName()],
            'areas' => array_map(fn (ActivityArea $a): array => ['value' => $a->value, 'label' => $a->label()], ActivityArea::cases()),
            'users' => $this->authors(),
        ]);
    }

    /**
     * Everyone who has changed something in this school (staff, and possibly former staff).
     *
     * @return array<int, array{id: int, name: string}>
     */
    private function authors(): array
    {
        $ids = Activity::query()
            ->where('causer_type', (new User)->getMorphClass())
            ->distinct()
            ->pluck('causer_id');

        return User::query()->whereKey($ids)->orderBy('name')->get(['id', 'name'])
            ->map(fn (User $user): array => ['id' => $user->id, 'name' => $user->name])
            ->values()
            ->all();
    }
}
