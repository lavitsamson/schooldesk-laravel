<?php

declare(strict_types=1);

namespace App\Actions\Guardians;

use App\Models\Guardian;
use Illuminate\Support\Str;

final class SaveGuardian
{
    /**
     * @param  array{name: string, email?: string|null, phone?: string|null, relationship: string}  $data
     */
    public function handle(?Guardian $guardian, array $data): Guardian
    {
        $guardian ??= new Guardian;

        $email = isset($data['email']) ? Str::lower(trim($data['email'])) : null;

        $guardian->fill([
            'name' => trim($data['name']),
            // The email is the portal login once invited, so it is fixed from then on.
            'email' => $guardian->user_id === null ? $email : $guardian->email,
            'phone' => $data['phone'] ?? null,
            'relationship' => $data['relationship'],
        ])->save();

        return $guardian;
    }
}
