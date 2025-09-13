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
        Schema::create('document', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('original_filename');
            $table->string('file_extension', 10);
            $table->string('mime_type');
            $table->unsignedBigInteger('file_size');
            $table->string('s3_key');
            $table->string('s3_bucket');
            $table->string('processing_status')->default('pending');
            $table->json('metadata')->nullable();
            $table->text('description')->nullable();
            $table->json('tags')->nullable();
            $table->boolean('is_public')->default(false);
            $table->timestamp('uploaded_at');
            $table->timestamps();
            
            $table->index(['user_id', 'processing_status']);
            $table->index('s3_key');
            $table->index('uploaded_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document');
    }
};
