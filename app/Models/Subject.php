<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Database\Factories\SubjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['school_id', 'name', 'code'])]
class Subject extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<SubjectFactory> */
    use HasFactory;
}
