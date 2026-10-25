<?php

declare(strict_types=1);

namespace App\Http\Requests\Imports;

use App\Models\ImportBatch;
use App\Support\StudentSheet;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;
use Maatwebsite\Excel\Excel;
use Maatwebsite\Excel\HeadingRowImport;
use Throwable;

/**
 * A CSV or XLSX file whose heading row has at least the required columns. The headings are
 * checked here, so a wrong file is rejected at once instead of failing on every row.
 */
class StoreStudentImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', ImportBatch::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'file' => [
                'required', 'file', 'extensions:csv,xlsx', 'mimes:csv,txt,xlsx',
                'max:'.(int) config('schooldesk.imports.max_file_kb'),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.extensions' => __('Upload a CSV or Excel (.xlsx) file.'),
            'file.mimes' => __('Upload a CSV or Excel (.xlsx) file.'),
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $headings = $this->headings();

                if ($headings === null) {
                    $validator->errors()->add('file', __('The file could not be read. Save it as CSV (UTF-8) or Excel (.xlsx) and try again.'));

                    return;
                }

                $missing = array_diff(StudentSheet::REQUIRED, $headings);

                if ($missing !== []) {
                    $validator->errors()->add('file', __('The first row must contain the column headings. Missing: :columns. Download the template to see the format.', ['columns' => implode(', ', $missing)]));
                }
            },
        ];
    }

    public function upload(): UploadedFile
    {
        /** @var UploadedFile */
        return $this->file('file');
    }

    /**
     * The slugged headings of the first row, or null if the file cannot be read.
     *
     * @return list<string>|null
     */
    private function headings(): ?array
    {
        $file = $this->upload();
        $type = Str::lower($file->getClientOriginalExtension()) === 'xlsx' ? Excel::XLSX : Excel::CSV;

        try {
            /** @var array<int, array<int, array<int, mixed>>> $sheets */
            $sheets = (new HeadingRowImport)->toArray($file, null, $type);
        } catch (Throwable) {
            return null;
        }

        return array_values(array_map('strval', array_filter($sheets[0][0] ?? [], is_string(...))));
    }
}
