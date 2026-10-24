<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\Role;
use App\Models\School;
use App\Models\User;
use App\Support\CurrentSchool;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    public function __construct(private readonly CurrentSchool $currentSchool) {}

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default. Explicit shapes only: never a whole model.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user instanceof User ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'email_verified_at' => $user->email_verified_at?->toIso8601String(),
                    'is_platform_admin' => $user->is_platform_admin,
                ] : null,
            ],
            'school' => fn (): ?array => $user instanceof User ? $this->schoolProps($user) : null,
            'schools' => fn (): array => $user instanceof User
                ? $user->activeSchools()->orderBy('name')->get(['schools.id', 'schools.name'])
                    ->map(fn (School $school): array => ['id' => $school->id, 'name' => $school->name])
                    ->all()
                : [],
            'flash' => [
                'status' => fn (): mixed => $request->session()->get('status'),
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function schoolProps(User $user): ?array
    {
        if (! $this->currentSchool->check()) {
            return null;
        }

        $school = $this->currentSchool->get();

        return [
            'id' => $school->id,
            'name' => $school->name,
            'setup_completed' => $school->isSetUp(),
            'roles' => $user->getRoleNames()
                ->map(fn (string $name): string => Role::tryFrom($name)?->label() ?? $name)
                ->values()
                ->all(),
            'permissions' => $user->getAllPermissions()->pluck('name')->values()->all(),
        ];
    }
}
