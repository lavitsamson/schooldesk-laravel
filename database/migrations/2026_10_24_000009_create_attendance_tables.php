<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            // Restrict: a class with attendance history cannot be deleted by accident.
            $table->foreignId('class_id')->constrained('classes')->restrictOnDelete();
            $table->date('date');
            $table->string('status', 10);
            $table->string('note')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            // Set when the primary guardian was told about an absence: at most once per day.
            $table->timestamp('guardian_notified_at')->nullable();
            $table->timestamps();

            $table->unique(['student_id', 'date']);
            // The grid and the monthly report read a class's records by date.
            $table->index(['school_id', 'class_id', 'date']);
            $table->index(['school_id', 'date']);
        });

        Schema::create('notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('attendance_records');
    }
};
