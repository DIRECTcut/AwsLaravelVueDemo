<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('document_processing_job', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->string('job_type'); // 'textract', 'comprehend', 'thumbnail'
            $table->string('status')->default('pending'); // pending, processing, completed, failed
            $table->string('aws_job_id')->nullable();
            $table->json('job_parameters')->nullable();
            $table->json('result_data')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            
            $table->index(['document_id', 'job_type']);
            $table->index('status');
            $table->index('aws_job_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_processing_job');
    }
};
