<?php

declare(strict_types=1);

use App\Actions\Roles\SyncRolesAndPermissions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * GDPR data exports of a student (a ZIP on the private disk, deleted when its link expires),
 * and the new data protection permission for school admins.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_exports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            // Who asked for it; the download link is emailed to them.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('file_path')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'student_id']);
            $table->index('expires_at');
        });

        app(SyncRolesAndPermissions::class)->handle();
    }

    public function down(): void
    {
        Schema::dropIfExists('student_exports');
    }
};
