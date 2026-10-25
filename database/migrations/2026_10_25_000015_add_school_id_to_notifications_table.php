<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * A parent with children at two schools sees each school's notifications only there. The
 * database channel fills school_id from the current school (SchoolDatabaseChannel); the
 * portal and the API read the current school's notifications only.
 *
 * Existing rows are not backfilled: no release has had the parent portal yet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table): void {
            $table->foreignId('school_id')->nullable()->after('id')->constrained()->cascadeOnDelete();

            $table->index(['school_id', 'notifiable_type', 'notifiable_id', 'created_at'], 'notifications_school_notifiable_index');
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table): void {
            // MySQL backs the foreign key with the composite index: drop the key first.
            $table->dropForeign(['school_id']);
            $table->dropIndex('notifications_school_notifiable_index');
            $table->dropColumn('school_id');
        });
    }
};
