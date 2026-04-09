<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reminders', function (Blueprint $table) {
            $table->id();

            $table->foreignId('loan_request_id')->constrained('loan_requests')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->string('type', 30); // due_soon | overdue
            $table->date('scheduled_for');

            $table->string('status', 20)->default('pending'); // pending | sent | failed
            $table->dateTime('sent_at')->nullable();
            $table->text('error')->nullable();

            $table->timestamps();

            $table->unique(['loan_request_id', 'type', 'scheduled_for'], 'reminders_unique_per_day');
            $table->index(['status', 'scheduled_for']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reminders');
    }
};
