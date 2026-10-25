<?php

declare(strict_types=1);

namespace App\Actions\Fees;

use App\Enums\FeeFrequency;
use App\Models\FeeStructure;

/**
 * Creates or changes a fee structure. Invoices already issued keep their items: a new amount
 * applies to invoices generated from now on.
 */
final class SaveFeeStructure
{
    /**
     * @param  array{academic_year_id: int, class_id: int|null, name: string, amount: int, frequency: FeeFrequency, due_day_offset: int}  $data
     */
    public function handle(?FeeStructure $feeStructure, array $data): FeeStructure
    {
        $feeStructure ??= new FeeStructure;

        $feeStructure->fill([...$data, 'name' => trim($data['name'])])->save();

        return $feeStructure;
    }
}
