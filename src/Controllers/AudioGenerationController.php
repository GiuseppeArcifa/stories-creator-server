<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\AudioGenerationRepository;
use App\Repositories\StoryRepository;
use App\Repositories\TextGenerationRepository;

class AudioGenerationController
{
    private AudioGenerationRepository $audioGenerationRepository;
    private StoryRepository $storyRepository;
    private TextGenerationRepository $textGenerationRepository;

    public function __construct(
        AudioGenerationRepository $audioGenerationRepository,
        StoryRepository $storyRepository,
        TextGenerationRepository $textGenerationRepository
    ) {
        $this->audioGenerationRepository = $audioGenerationRepository;
        $this->storyRepository = $storyRepository;
        $this->textGenerationRepository = $textGenerationRepository;
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

        $audioGenerations = $this->audioGenerationRepository->findByStoryId($storyId);
        $payload = array_map(static fn ($gen) => $gen->toArray(), $audioGenerations);

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
        $missing = $this->validateRequired($data, ['text_generation_id', 'audio_file_id']);

        if (!empty($missing)) {
            jsonResponse(['error' => 'Missing required fields', 'fields' => $missing], 400);
            return;
        }

        $textGenerationId = (int) $data['text_generation_id'];

        // Verifica che la text_generation esista e appartenga alla stessa story
        $textGeneration = $this->textGenerationRepository->find($textGenerationId);

        if (!$textGeneration) {
            jsonError('Text generation not found', 404);
            return;
        }

        if (!$this->textGenerationRepository->belongsToStory($textGenerationId, $storyId)) {
            jsonError('Text generation does not belong to this story', 400);
            return;
        }

        $data['story_id'] = $storyId;

        try {
            $audioGeneration = $this->audioGenerationRepository->create($data);
            jsonResponse($audioGeneration->toArray(), 201);
        } catch (\Throwable $e) {
            jsonError('Unable to create audio generation: ' . $e->getMessage(), 500);
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

