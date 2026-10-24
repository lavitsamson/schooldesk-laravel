<?php

declare(strict_types=1);

namespace App\Http\Requests\Invitations;

use App\Models\Membership;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * The link itself is checked by the `signed` middleware. New users choose their name and
 * password here; existing users must be logged in as the invited account.
 */
class AcceptInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $invitee = $this->invitation()->user;

        return ! $invitee->hasPassword() || $this->user()?->is($invitee) === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        if ($this->invitation()->user->hasPassword()) {
            return [];
        }

        return [
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ];
    }

    public function invitation(): Membership
    {
        /** @var Membership */
        return $this->route('invitation');
    }
}
