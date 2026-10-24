<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ImportRowOutcome;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The outcome of one file row. Only read through its (school-scoped) import batch, so it has
 * no school_id of its own. Original values are kept for failed rows only.
 *
 * @property int $id
 * @property int $import_batch_id
 * @property int $row_number
 * @property string|null $key
 * @property ImportRowOutcome $outcome
 * @property array<string, string|null>|null $values
 * @property list<string>|null $errors
 */
#[Fillable(['import_batch_id', 'row_number', 'key', 'outcome', 'values', 'errors'])]
class ImportRow extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'outcome' => ImportRowOutcome::class,
            'values' => 'array',
            'errors' => 'array',
        ];
    }

    /**
     * @return BelongsTo<ImportBatch, $this>
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class, 'import_batch_id');
    }
}
