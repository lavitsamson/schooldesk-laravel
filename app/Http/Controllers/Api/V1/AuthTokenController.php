<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Api\IssueApiToken;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\IssueTokenRequest;
use App\Http\Resources\ApiTokenResource;
use App\Models\PersonalAccessToken;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * @group Authentication
 *
 * Parents sign in with the email address and password of their parent portal account. Each
 * token is for one device and one school; send it as `Authorization: Bearer <token>`.
 */
class AuthTokenController extends Controller
{
    /**
     * Get a token
     *
     * Parents at several schools choose one with `school_id` (the error lists their schools).
     * Tokens expire after 90 days. Limited to 5 attempts a minute per email address.
     *
     * @unauthenticated
     *
     * @response 201 {"data": {"token": "12|sd_Xb3k…", "token_type": "Bearer", "school_id": 1, "expires_at": "2027-01-23T10:00:00+00:00"}}
     * @response 422 {"message": "These credentials do not match our records.", "code": "validation_failed", "errors": {"email": ["These credentials do not match our records."]}}
     * @response 403 {"message": "The API is for parents. Please use the web app.", "code": "forbidden"}
     */
    public function store(IssueTokenRequest $request, IssueApiToken $action): JsonResponse
    {
        $token = $action->handle(
            $request->string('email')->value(),
            $request->string('password')->value(),
            $request->string('device_name')->value(),
            $request->schoolId(),
        );

        return (new ApiTokenResource($token))->response()->setStatusCode(201);
    }

    /**
     * Revoke the token
     *
     * Signs this device out. Other devices keep their tokens.
     *
     * @response 204 scenario="Revoked"
     */
    public function destroy(#[CurrentUser] User $user): Response
    {
        /** @var PersonalAccessToken $token */
        $token = $user->currentAccessToken();
        $token->delete();

        return response()->noContent();
    }
}
