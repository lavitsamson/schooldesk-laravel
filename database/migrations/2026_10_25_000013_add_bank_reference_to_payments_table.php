<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * A bank transfer is recorded at most once per school: its bank reference, normalised (upper
 * case, no spaces), is unique per school. Only bank transfers fill the column; NULLs do not
 * collide, on MySQL or PostgreSQL. `reference` keeps the reference as typed, for display.
 *
 * Existing rows are not backfilled: no release has recorded bank transfers yet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->string('bank_reference', 100)->nullable()->after('reference');

            $table->unique(['school_id', 'bank_reference']);
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropUnique(['school_id', 'bank_reference']);
            $table->dropColumn('bank_reference');
        });
    }
};
