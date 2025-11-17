<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\StoryRepository;
use App\Repositories\TextGenerationRepository;
use App\Services\TextGenerationService;

class TextGenerationController
{
    private TextGenerationRepository $textGenerationRepository;
    private StoryRepository $storyRepository;
    private TextGenerationService $textGenerationService;

    public function __construct(
        TextGenerationRepository $textGenerationRepository,
        StoryRepository $storyRepository,
        TextGenerationService $textGenerationService
    ) {
        $this->textGenerationRepository = $textGenerationRepository;
        $this->storyRepository = $storyRepository;
        $this->textGenerationService = $textGenerationService;
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

    /**
     * @param array<string, string> $params
     */
    public function generateForStory(array $params): void
    {
        $storyId = (int) ($params['id'] ?? 0);

        if ($storyId <= 0) {
            jsonError('Invalid story id', 400);
            return;
        }

        // Verifica che la story esista
        $story = $this->storyRepository->find($storyId);

        if (!$story) {
            jsonError('Story not found', 404);
            return;
        }

        // Leggi e valida il body JSON
        $data = getJsonInput();
        $missing = $this->validateRequired($data, ['title', 'type', 'plot', 'teachings', 'duration']);

        if (!empty($missing)) {
            jsonResponse(['error' => 'Missing required fields', 'fields' => $missing], 400);
            return;
        }

        // Prepara i dati per l'aggiornamento della story
        $storyUpdateData = [
            'title' => $data['title'],
            'type' => $data['type'],
            'plot' => $data['plot'],
            'teachings' => $data['teachings'],
            'duration_minutes' => (int) $data['duration'],
        ];

        if (isset($data['otherNotes']) && $data['otherNotes'] !== '') {
            $storyUpdateData['other_notes'] = $data['otherNotes'];
        }

        // Aggiorna la story
        $updatedStory = $this->storyRepository->update($storyId, $storyUpdateData);

        if (!$updatedStory) {
            jsonError('Unable to update story', 500);
            return;
        }

        // Prepara il payload per l'API esterna
        $apiPayload = [
            'title' => $data['title'],
            'type' => $data['type'],
            'plot' => $data['plot'],
            'teachings' => $data['teachings'],
            'duration' => (int) $data['duration'],
        ];

        if (isset($data['otherNotes']) && $data['otherNotes'] !== '') {
            $apiPayload['otherNotes'] = $data['otherNotes'];
        }

        // Chiama l'API esterna per generare i testi
        try {
            $generations = $this->textGenerationService->generateText($apiPayload);
        } catch (\Throwable $e) {
            jsonError('Text generation service unavailable or returned invalid data: ' . $e->getMessage(), 502);
            return;
        }

        // Prepara i dati per il salvataggio nel DB
        $generationsData = [];

        foreach ($generations as $generation) {
            $generationsData[] = [
                'story_id' => $storyId,
                'full_text' => $generation['generated_text'],
                'plot' => $data['plot'],
                'teachings' => $data['teachings'],
                'duration_minutes' => (int) $data['duration'],
                'provider' => 'ai_service',
                'model' => 'default-model',
            ];
        }

        // Salva le generazioni in una transazione
        try {
            $savedGenerations = $this->textGenerationRepository->createBatch($generationsData);
        } catch (\Throwable $e) {
            jsonError('Unable to save text generations: ' . $e->getMessage(), 500);
            return;
        }

        // Prepara la risposta
        $response = [
            'story_id' => $storyId,
            'text_generations' => array_map(static fn ($gen) => $gen->toArray(), $savedGenerations),
        ];

        jsonResponse($response, 201);
    }
}

