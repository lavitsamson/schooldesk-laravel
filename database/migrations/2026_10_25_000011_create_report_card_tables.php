<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_cards', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('term_id')->constrained()->restrictOnDelete();
            // The class the card was written in (the class teacher's class at the time).
            $table->foreignId('class_id')->constrained('classes')->restrictOnDelete();
            // CalculateReportCard's snapshot: frozen once approved (ADR 0007).
            $table->json('data');
            $table->text('teacher_comment')->nullable();
            $table->string('comment_source', 20)->nullable();
            $table->string('status', 20)->default('draft');
            $table->string('pdf_path')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['student_id', 'term_id']);
            $table->index(['school_id', 'term_id', 'class_id', 'status']);
        });

        // A "generate for the whole class" run: one queued job per student in a Laravel job
        // batch, whose progress the class page polls. This row ties the batch to a school.
        Schema::create('report_card_batches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('term_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('job_batch_id', 36)->nullable();
            $table->unsignedInteger('total');
            $table->timestamps();

            $table->index(['school_id', 'class_id', 'term_id', 'created_at']);
        });

        Schema::create('ai_comment_drafts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('report_card_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            // sha256 of what was sent: shows two drafts came from the same data, without storing it.
            $table->char('prompt_hash', 64);
            $table->text('draft_text');
            $table->string('model', 100);
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            // Taken over into the teacher's comment (and then edited or not).
            $table->boolean('accepted')->default(false);
            $table->timestamps();

            $table->index(['school_id', 'report_card_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_comment_drafts');
        Schema::dropIfExists('report_card_batches');
        Schema::dropIfExists('report_cards');
    }
};
