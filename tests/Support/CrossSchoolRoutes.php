<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\Membership;
use App\Models\School;
use Closure;

/**
 * The tenant isolation harness. Every route that takes a parameter must either have a case
 * here (a request for School B's record, which a School A admin must get a 404 for) or be
 * listed as exempt with a reason. TenantIsolationTest fails when a new route is in neither.
 */
final class CrossSchoolRoutes
{
    /**
     * Routes whose parameters do not identify a school's records.
     */
    public const EXEMPT = [
        'setup.show' => 'the parameter is a wizard step, not a record',
        'invitations.show' => 'access is proven by the signed link, not by membership',
        'invitations.accept' => 'access is proven by the signed link, not by membership',
        'password.reset' => 'Breeze password reset token',
        'verification.verify' => 'Breeze email verification (signed, own user only)',
        'storage.local' => 'Laravel local disk serving (signed URLs only)',
        'storage.local.upload' => 'Laravel local disk uploads (signed URLs only)',
    ];

    /**
     * @return array<string, Closure(School): array{0: string, 1: string}>
     */
    public static function cases(): array
    {
        return [
            'schools.switch' => fn (School $other): array => [
                'post', route('schools.switch', $other),
            ],
            'members.resend' => fn (School $other): array => [
                'post', route('members.resend', Membership::factory()->for($other)->invited()->create()),
            ],
            'members.destroy' => fn (School $other): array => [
                'delete', route('members.destroy', Membership::factory()->for($other)->create()),
            ],
        ];
    }
}
