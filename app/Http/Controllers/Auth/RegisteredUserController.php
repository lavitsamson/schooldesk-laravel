<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Actions\Schools\RegisterSchool;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    /**
     * Register a new school with its first school admin, then start the setup wizard.
     */
    public function store(RegisterRequest $request, RegisterSchool $registerSchool): RedirectResponse
    {
        /** @var array{name: string, email: string, password: string, school_name: string} $data */
        $data = $request->validated();

        $user = $registerSchool->handle($data);

        event(new Registered($user));

        Auth::login($user);

        return redirect(route('setup', absolute: false));
    }
}
