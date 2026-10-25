<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\ImportBatch;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An import and its progress. created_rows / updated_rows are only present when counted
 * (ListImportBatches and the progress page do).
 *
 * @mixin ImportBatch
 *
 * @property-read int|null $created_rows
 * @property-read int|null $updated_rows
 */
class ImportBatchResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type_label' => $this->type->label(),
            'original_name' => $this->original_name,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'is_finished' => $this->status->isFinished(),
            'progress' => $this->progress(),
            'total_rows' => $this->total_rows,
            'processed_rows' => $this->processed_rows,
            'failed_rows' => $this->failed_rows,
            'created_rows' => $this->created_rows,
            'updated_rows' => $this->updated_rows,
            // Stored as the English text (FailImport), shown in the reader's language.
            'failure_message' => $this->failure_message === null ? null : __($this->failure_message),
            'error_report_url' => $this->error_report_path === null ? null : route('imports.error-report', $this->id),
            'uploaded_by' => $this->relationLoaded('user') ? $this->user?->name : null,
            'created_at' => $this->created_at->toIso8601String(),
            'finished_at' => $this->finished_at?->toIso8601String(),
        ];
    }
}
