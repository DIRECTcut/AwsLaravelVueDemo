<?php

namespace Database\Factories;

use App\Models\User;
use App\ProcessingStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Document>
 */
class DocumentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $filename = $this->faker->word . '.' . $this->faker->randomElement(['pdf', 'jpg', 'png', 'docx']);
        
        return [
            'user_id' => User::factory(),
            'title' => $this->faker->sentence(3),
            'original_filename' => $filename,
            'file_extension' => pathinfo($filename, PATHINFO_EXTENSION),
            'mime_type' => $this->getMimeType(pathinfo($filename, PATHINFO_EXTENSION)),
            'file_size' => $this->faker->numberBetween(1024, 10485760), // 1KB to 10MB
            's3_key' => 'documents/' . $this->faker->uuid . '.' . pathinfo($filename, PATHINFO_EXTENSION),
            's3_bucket' => 'test-bucket',
            'processing_status' => $this->faker->randomElement(ProcessingStatus::cases()),
            'metadata' => [
                'width' => $this->faker->optional()->numberBetween(100, 2000),
                'height' => $this->faker->optional()->numberBetween(100, 2000),
            ],
            'description' => $this->faker->optional()->paragraph,
            'tags' => $this->faker->optional()->randomElements(['work', 'personal', 'urgent', 'contract', 'invoice'], $this->faker->numberBetween(0, 3)),
            'is_public' => $this->faker->boolean(20), // 20% chance of being public
            'uploaded_at' => $this->faker->dateTimeBetween('-1 year', 'now'),
        ];
    }
    
    private function getMimeType(string $extension): string
    {
        return match($extension) {
            'pdf' => 'application/pdf',
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'doc' => 'application/msword',
            default => 'application/octet-stream',
        };
    }
}
