<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Money is stored as integers in minor units (cents) of the school's currency, never as
 * floats or decimals (ADR 0008).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fee_structures', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
            // Null: charged to every class of the year.
            $table->foreignId('class_id')->nullable()->constrained('classes')->cascadeOnDelete();
            $table->string('name', 100);
            $table->unsignedInteger('amount');
            $table->string('frequency', 10);
            // The invoice is due this many days after the term starts.
            $table->unsignedSmallInteger('due_day_offset')->default(14);
            $table->timestamps();

            $table->index(['school_id', 'academic_year_id', 'class_id']);
        });

        Schema::create('invoices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('term_id')->constrained()->restrictOnDelete();
            // The class the student was in when invoiced, for the fees dashboard.
            $table->foreignId('class_id')->nullable()->constrained('classes')->nullOnDelete();
            $table->string('number', 30);
            $table->unsignedInteger('total');
            $table->string('status', 20);
            $table->date('due_on');
            $table->timestamp('issued_at');
            $table->timestamp('voided_at')->nullable();
            // Overdue reminders sent so far (after 3 and after 10 days): each at most once.
            $table->unsignedTinyInteger('reminders_sent')->default(0);
            $table->timestamps();

            $table->unique(['school_id', 'number']);
            // One invoice per student and term: what makes generation idempotent.
            $table->unique(['student_id', 'term_id']);
            $table->index(['school_id', 'term_id', 'class_id', 'status']);
            $table->index(['school_id', 'status', 'due_on']);
        });

        Schema::create('invoice_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            // Kept when the fee structure is deleted: the item has its own description and amount.
            $table->foreignId('fee_structure_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description', 150);
            $table->unsignedInteger('amount');
            $table->timestamps();

            $table->index(['fee_structure_id', 'invoice_id']);
        });

        // Gapless invoice numbers per school and year: the counter is locked and incremented
        // in the transaction that creates the invoice, so a rollback gives the number back.
        Schema::create('invoice_number_sequences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();

            $table->unique(['school_id', 'year']);
        });

        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('amount');
            // Refunds (Stripe only) reduce what the payment counts towards the invoice.
            $table->unsignedInteger('refunded_amount')->default(0);
            $table->string('method', 20);
            $table->string('stripe_payment_intent_id')->nullable()->unique();
            // A bank reference or a note on a manual payment.
            $table->string('reference', 100)->nullable();
            $table->timestamp('paid_at');
            $table->timestamp('refunded_at')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('receipt_path')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'invoice_id']);
            $table->index(['school_id', 'paid_at']);
        });

        // Not school-owned: a Stripe event is recorded before we know which school it is for.
        Schema::create('stripe_webhook_events', function (Blueprint $table): void {
            $table->id();
            $table->string('stripe_event_id')->unique();
            $table->string('type', 100);
            $table->timestamp('processed_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stripe_webhook_events');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('invoice_number_sequences');
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('fee_structures');
    }
};
