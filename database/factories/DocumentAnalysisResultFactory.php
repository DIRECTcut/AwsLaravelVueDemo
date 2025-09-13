<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\DocumentAnalysisResult;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DocumentAnalysisResult>
 */
class DocumentAnalysisResultFactory extends Factory
{
    protected $model = DocumentAnalysisResult::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'document_id' => Document::factory(),
            'analysis_type' => fake()->randomElement([
                'textract_text',
                'textract_analysis',
                'comprehend_sentiment',
                'comprehend_entities',
                'comprehend_key_phrases',
                'comprehend_language',
            ]),
            'raw_results' => [
                'test_data' => 'sample results',
                'ResponseMetadata' => ['RequestId' => fake()->uuid()],
            ],
            'processed_data' => [
                'processed' => 'sample processed data',
            ],
            'confidence_score' => fake()->randomFloat(2, 0.5, 1.0),
            'metadata' => [
                'processing_time' => fake()->numberBetween(1, 60),
                'aws_request_id' => fake()->uuid(),
            ],
        ];
    }

    /**
     * Create a Textract text result.
     */
    public function textractText(): static
    {
        return $this->state(fn (array $attributes) => [
            'analysis_type' => 'textract_text',
            'raw_results' => [
                'Blocks' => [
                    [
                        'BlockType' => 'LINE',
                        'Text' => fake()->sentence(),
                        'Confidence' => fake()->randomFloat(1, 80, 99),
                    ]
                ],
                'ResponseMetadata' => ['RequestId' => fake()->uuid()],
            ],
            'processed_data' => [
                'text_blocks' => [
                    ['text' => fake()->sentence(), 'confidence' => fake()->randomFloat(1, 80, 99)],
                ],
                'tables' => [],
                'forms' => [],
            ],
        ]);
    }

    /**
     * Create a Textract analysis result.
     */
    public function textractAnalysis(): static
    {
        return $this->state(fn (array $attributes) => [
            'analysis_type' => 'textract_analysis',
            'raw_results' => [
                'Blocks' => [
                    [
                        'BlockType' => 'TABLE',
                        'Id' => fake()->uuid(),
                        'Confidence' => fake()->randomFloat(1, 80, 99),
                    ]
                ],
                'ResponseMetadata' => ['RequestId' => fake()->uuid()],
            ],
            'processed_data' => [
                'text_blocks' => [],
                'tables' => [
                    ['id' => fake()->uuid(), 'confidence' => fake()->randomFloat(1, 80, 99)],
                ],
                'forms' => [],
            ],
        ]);
    }

    /**
     * Create a Comprehend sentiment result.
     */
    public function comprehendSentiment(): static
    {
        return $this->state(fn (array $attributes) => [
            'analysis_type' => 'comprehend_sentiment',
            'raw_results' => [
                'Sentiment' => fake()->randomElement(['POSITIVE', 'NEGATIVE', 'NEUTRAL', 'MIXED']),
                'SentimentScore' => [
                    'Positive' => fake()->randomFloat(2, 0, 1),
                    'Negative' => fake()->randomFloat(2, 0, 1),
                    'Neutral' => fake()->randomFloat(2, 0, 1),
                    'Mixed' => fake()->randomFloat(2, 0, 1),
                ],
                'ResponseMetadata' => ['RequestId' => fake()->uuid()],
            ],
            'processed_data' => [
                'sentiment' => fake()->randomElement(['POSITIVE', 'NEGATIVE', 'NEUTRAL', 'MIXED']),
                'confidence_scores' => [
                    'Positive' => fake()->randomFloat(2, 0, 1),
                    'Negative' => fake()->randomFloat(2, 0, 1),
                    'Neutral' => fake()->randomFloat(2, 0, 1),
                    'Mixed' => fake()->randomFloat(2, 0, 1),
                ],
            ],
        ]);
    }

    /**
     * Create a Comprehend entities result.
     */
    public function comprehendEntities(): static
    {
        return $this->state(fn (array $attributes) => [
            'analysis_type' => 'comprehend_entities',
            'raw_results' => [
                'Entities' => [
                    [
                        'Text' => fake()->name(),
                        'Type' => 'PERSON',
                        'Score' => fake()->randomFloat(2, 0.5, 1),
                    ],
                    [
                        'Text' => fake()->company(),
                        'Type' => 'ORGANIZATION',
                        'Score' => fake()->randomFloat(2, 0.5, 1),
                    ],
                ],
                'ResponseMetadata' => ['RequestId' => fake()->uuid()],
            ],
            'processed_data' => [
                'entities' => [
                    [
                        'text' => fake()->name(),
                        'type' => 'PERSON',
                        'confidence' => fake()->randomFloat(2, 0.5, 1),
                    ],
                    [
                        'text' => fake()->company(),
                        'type' => 'ORGANIZATION',
                        'confidence' => fake()->randomFloat(2, 0.5, 1),
                    ],
                ],
            ],
        ]);
    }
}