<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\MeResource;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;

/**
 * @group Account
 */
class MeController extends Controller
{
    /**
     * The signed-in parent
     *
     * With the token's school: its currency is the currency of every amount in the API, in
     * minor units (cents).
     *
     * @response {"data": {"user": {"id": 7, "name": "Paula Parent", "email": "parent@riverside.demo", "locale": "en"}, "school": {"id": 1, "name": "Riverside International School", "locale": "en", "currency": "EUR", "timezone": "Europe/Berlin"}}}
     */
    public function __invoke(#[CurrentUser] User $user): MeResource
    {
        return new MeResource($user);
    }
}
