<?php

namespace Database\Factories;

use App\JobStatus;
use App\Models\Document;
use App\Models\DocumentProcessingJob;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DocumentProcessingJob>
 */
class DocumentProcessingJobFactory extends Factory
{
    protected $model = DocumentProcessingJob::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'document_id' => Document::factory(),
            'job_type' => fake()->randomElement([
                'textract_text',
                'textract_analysis',
                'comprehend_sentiment',
                'comprehend_entities',
                'comprehend_key_phrases',
                'comprehend_language',
            ]),
            'status' => JobStatus::PENDING,
            'job_parameters' => [],
            'aws_job_id' => null,
            'started_at' => null,
            'completed_at' => null,
            'result_data' => null,
            'error_message' => null,
        ];
    }

    /**
     * Indicate that the job is completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => JobStatus::COMPLETED,
            'started_at' => now()->subMinutes(5),
            'completed_at' => now(),
            'result_data' => ['test' => 'results'],
        ]);
    }

    /**
     * Indicate that the job is processing.
     */
    public function processing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => JobStatus::PROCESSING,
            'started_at' => now()->subMinutes(2),
        ]);
    }

    /**
     * Indicate that the job has failed.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => JobStatus::FAILED,
            'started_at' => now()->subMinutes(3),
            'error_message' => 'Test error message',
        ]);
    }

    /**
     * Create a Textract job.
     */
    public function textract(): static
    {
        return $this->state(fn (array $attributes) => [
            'job_type' => fake()->randomElement(['textract_text', 'textract_analysis']),
        ]);
    }

    /**
     * Create a Comprehend job.
     */
    public function comprehend(): static
    {
        return $this->state(fn (array $attributes) => [
            'job_type' => fake()->randomElement([
                'comprehend_sentiment',
                'comprehend_entities',
                'comprehend_key_phrases',
                'comprehend_language',
            ]),
        ]);
    }
}
