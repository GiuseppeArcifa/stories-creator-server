<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\StoryRepository;
use App\Repositories\TextGenerationRepository;

class TextGenerationController
{
    private TextGenerationRepository $textGenerationRepository;
    private StoryRepository $storyRepository;

    public function __construct(TextGenerationRepository $textGenerationRepository, StoryRepository $storyRepository)
    {
        $this->textGenerationRepository = $textGenerationRepository;
        $this->storyRepository = $storyRepository;
    }

    /**
     * @param array<string, string> $params
     */
    public function index(array $params): void
    {
        $storyId = (int) ($params['id'] ?? 0);

        if ($storyId <= 0) {
            jsonError('Invalid story id', 400);
            return;
        }

        $story = $this->storyRepository->find($storyId);

        if (!$story) {
            jsonError('Story not found', 404);
            return;
        }

        $textGenerations = $this->textGenerationRepository->findByStoryId($storyId);
        $payload = array_map(static fn ($gen) => $gen->toArray(), $textGenerations);

        jsonResponse($payload);
    }

    /**
     * @param array<string, string> $params
     */
    public function store(array $params): void
    {
        $storyId = (int) ($params['id'] ?? 0);

        if ($storyId <= 0) {
            jsonError('Invalid story id', 400);
            return;
        }

        $story = $this->storyRepository->find($storyId);

        if (!$story) {
            jsonError('Story not found', 404);
            return;
        }

        $data = getJsonInput();
        $missing = $this->validateRequired($data, ['full_text']);

        if (!empty($missing)) {
            jsonResponse(['error' => 'Missing required fields', 'fields' => $missing], 400);
            return;
        }

        $data['story_id'] = $storyId;

        try {
            $textGeneration = $this->textGenerationRepository->create($data);
            jsonResponse($textGeneration->toArray(), 201);
        } catch (\Throwable $e) {
            jsonError('Unable to create text generation: ' . $e->getMessage(), 500);
        }
    }

    /**
     * @param array<string, mixed> $data
     * @param array<int, string> $fields
     *
     * @return array<int, string>
     */
    private function validateRequired(array $data, array $fields): array
    {
        $missing = [];

        foreach ($fields as $field) {
            if (!isset($data[$field]) || $data[$field] === '') {
                $missing[] = $field;
            }
        }

        return $missing;
    }
}

