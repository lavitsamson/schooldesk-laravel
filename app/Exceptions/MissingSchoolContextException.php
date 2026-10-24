<?php

declare(strict_types=1);

namespace App\Exceptions;

use LogicException;

/**
 * Thrown when school-owned data is queried or created without a school context.
 * The tenancy layer fails closed: no context means no data, never all data.
 */
final class MissingSchoolContextException extends LogicException
{
    public function __construct()
    {
        parent::__construct('No school context is set. Set one with CurrentSchool before touching school-owned data.');
    }
}
