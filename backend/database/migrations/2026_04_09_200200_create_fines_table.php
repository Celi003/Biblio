<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fines', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('loan_request_id')->constrained('loan_requests')->cascadeOnDelete();

            $table->unsignedInteger('amount_cents')->default(0);
            $table->unsignedInteger('days_overdue')->default(0);

            $table->string('status', 20)->default('unpaid'); // unpaid | paid | waived
            $table->dateTime('calculated_at');
            $table->dateTime('paid_at')->nullable();

            $table->timestamps();

            $table->unique(['loan_request_id'], 'fines_one_per_loan');
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fines');
    }
};
