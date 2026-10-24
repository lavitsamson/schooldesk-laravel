<?php

declare(strict_types=1);

namespace App\Exceptions;

use LogicException;

/**
 * Thrown when code tries to write a school-owned record for a school other than the current one,
 * or to move an existing record to another school.
 */
final class CrossSchoolWriteException extends LogicException
{
    public static function forModel(string $model): self
    {
        return new self("Refusing to write {$model} for a school other than the current one.");
    }
}
