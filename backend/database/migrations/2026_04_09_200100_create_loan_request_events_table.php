<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_request_events', function (Blueprint $table) {
            $table->id();

            $table->foreignId('loan_request_id')->constrained('loan_requests')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('type', 50);
            $table->json('meta')->nullable();

            $table->timestamps();

            $table->index(['loan_request_id', 'created_at']);
            $table->index(['type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_request_events');
    }
};
