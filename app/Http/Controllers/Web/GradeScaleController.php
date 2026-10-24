<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Actions\GradeScales\SaveGradeScale;
use App\Enums\GradeScalePreset;
use App\Http\Controllers\Controller;
use App\Http\Requests\GradeScales\SaveGradeScaleRequest;
use App\Models\GradeScale;
use App\Support\Grading\GradeBands;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class GradeScaleController extends Controller
{
    public function edit(): Response
    {
        Gate::authorize('viewAny', GradeScale::class);

        return Inertia::render('GradeScale/Edit', [
            'bands' => GradeBands::current()->all(),
            'presets' => array_map(fn (GradeScalePreset $preset): array => [
                'value' => $preset->value,
                'label' => $preset->label(),
                'bands' => $preset->bands(),
            ], GradeScalePreset::cases()),
        ]);
    }

    public function update(SaveGradeScaleRequest $request, SaveGradeScale $action): RedirectResponse
    {
        $action->handle($request->bands());

        return back()->with('status', 'Grade scale saved.');
    }
}
