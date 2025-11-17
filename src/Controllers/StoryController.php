<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\AudioGenerationRepository;
use App\Repositories\StoryRepository;
use App\Repositories\TextGenerationRepository;
use App\Services\TextGenerationService;

class StoryController
{
    private StoryRepository $repository;
    private TextGenerationRepository $textGenerationRepository;
    private AudioGenerationRepository $audioGenerationRepository;
    private TextGenerationService $textGenerationService;

    public function __construct(
        StoryRepository $repository,
        TextGenerationRepository $textGenerationRepository,
        AudioGenerationRepository $audioGenerationRepository,
        TextGenerationService $textGenerationService
    ) {
        $this->repository = $repository;
        $this->textGenerationRepository = $textGenerationRepository;
        $this->audioGenerationRepository = $audioGenerationRepository;
        $this->textGenerationService = $textGenerationService;
    }

    /**
     * @param array<string, string> $params
     */
    public function index(array $params = []): void
    {
        $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 50;
        $offset = isset($_GET['offset']) ? (int) $_GET['offset'] : 0;
        $includeGenerations = isset($_GET['include_generations']) && (int) $_GET['include_generations'] === 1;

        $limit = max(1, min($limit, 100));
        $offset = max(0, $offset);

        $stories = $this->repository->all($limit, $offset);
        $payload = array_map(function ($story) use ($includeGenerations) {
            $storyData = $story->toArray();

            if ($includeGenerations) {
                $storyData['text_generations'] = array_map(
                    fn ($gen) => $gen->toArray(),
                    $this->textGenerationRepository->findByStoryId($story->id ?? 0)
                );
                $storyData['audio_generations'] = array_map(
                    fn ($gen) => $gen->toArray(),
                    $this->audioGenerationRepository->findByStoryId($story->id ?? 0)
                );
            }

            return $storyData;
        }, $stories);

        jsonResponse($payload);
    }

    /**
     * @param array<string, string> $params
     */
    public function show(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);

        $story = $this->repository->find($id);

        if (!$story) {
            jsonError('Story not found', 404);
            return;
        }

        $storyData = $story->toArray();

        // Include sempre le generazioni nel dettaglio
        $storyData['text_generations'] = array_map(
            fn ($gen) => $gen->toArray(),
            $this->textGenerationRepository->findByStoryId($id)
        );
        $storyData['audio_generations'] = array_map(
            fn ($gen) => $gen->toArray(),
            $this->audioGenerationRepository->findByStoryId($id)
        );

        jsonResponse($storyData);
    }

    /**
     * @param array<string, string> $params
     */
    public function store(array $params = []): void
    {
        $data = getJsonInput();
        $missing = $this->validateRequired($data, ['title', 'type', 'plot', 'teachings']);

        if (!empty($missing)) {
            jsonResponse(['error' => 'Missing required fields', 'fields' => $missing], 400);
            return;
        }

        try {
            // Crea la storia
            $story = $this->repository->create($data);

            // Prepara i dati per la generazione testo (usa duration se presente, altrimenti duration_minutes)
            $duration = $data['duration'] ?? $data['duration_minutes'] ?? null;

            // Prepara il payload per l'API esterna
            $apiPayload = [
                'title' => $data['title'],
                'type' => $data['type'],
                'plot' => $data['plot'],
                'teachings' => $data['teachings'],
            ];

            if ($duration !== null) {
                $apiPayload['duration'] = (int) $duration;
            }

            if (isset($data['otherNotes']) && $data['otherNotes'] !== '') {
                $apiPayload['otherNotes'] = $data['otherNotes'];
            } elseif (isset($data['other_notes']) && $data['other_notes'] !== '') {
                $apiPayload['otherNotes'] = $data['other_notes'];
            }

            // Chiama l'API esterna per generare i testi
            $textGenerations = [];

            try {
                $generations = $this->textGenerationService->generateText($apiPayload);

                // Prepara i dati per il salvataggio nel DB
                $generationsData = [];

                foreach ($generations as $generation) {
                    $generationsData[] = [
                        'story_id' => $story->id ?? 0,
                        'full_text' => $generation['generated_text'],
                        'provider' => 'ai_service',
                        'model' => 'default-model',
                    ];
                }

                // Salva le generazioni in una transazione
                $textGenerations = $this->textGenerationRepository->createBatch($generationsData);
            } catch (\Throwable $e) {
                // Se la generazione fallisce, logga l'errore ma non blocca la creazione della storia
                // Le generazioni rimarranno vuote
                error_log('Text generation failed for story ' . ($story->id ?? 'unknown') . ': ' . $e->getMessage());
            }

            // Prepara la risposta con la storia e le generazioni
            $response = $story->toArray();
            $response['text_generations'] = array_map(static fn ($gen) => $gen->toArray(), $textGenerations);

            jsonResponse($response, 201);
        } catch (\Throwable $e) {
            jsonError('Unable to create story: ' . $e->getMessage(), 500);
        }
    }

    /**
     * @param array<string, string> $params
     */
    public function update(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);

        if ($id <= 0) {
            jsonError('Invalid story id', 400);
            return;
        }

        $payload = getJsonInput();

        if (empty($payload)) {
            jsonError('No data provided for update', 400);
            return;
        }

        $story = $this->repository->update($id, $payload);

        if (!$story) {
            jsonError('Story not found', 404);
            return;
        }

        jsonResponse($story->toArray());
    }

    /**
     * @param array<string, string> $params
     */
    public function destroy(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);

        if ($id <= 0) {
            jsonError('Invalid story id', 400);
            return;
        }

        $story = $this->repository->find($id);

        if (!$story) {
            jsonError('Story not found', 404);
            return;
        }

        $deleted = $this->repository->delete($id);

        if (!$deleted) {
            jsonError('Unable to delete story', 500);
            return;
        }

        jsonResponse(['message' => 'Story deleted']);
    }

    /**
     * @param array<string, string> $params
     */
    public function updateFinalGenerations(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);

        if ($id <= 0) {
            jsonError('Invalid story id', 400);
            return;
        }

        $story = $this->repository->find($id);

        if (!$story) {
            jsonError('Story not found', 404);
            return;
        }

        $data = getJsonInput();
        $finalTextGenerationId = isset($data['final_text_generation_id']) ? (int) $data['final_text_generation_id'] : null;
        $finalAudioGenerationId = isset($data['final_audio_generation_id']) ? (int) $data['final_audio_generation_id'] : null;

        // Valida final_text_generation_id se fornito
        if ($finalTextGenerationId !== null) {
            $textGen = $this->textGenerationRepository->find($finalTextGenerationId);
            if (!$textGen) {
                jsonError('Text generation not found', 404);
                return;
            }
            if (!$this->textGenerationRepository->belongsToStory($finalTextGenerationId, $id)) {
                jsonError('Text generation does not belong to this story', 400);
                return;
            }
        }

        // Valida final_audio_generation_id se fornito
        if ($finalAudioGenerationId !== null) {
            $audioGen = $this->audioGenerationRepository->find($finalAudioGenerationId);
            if (!$audioGen) {
                jsonError('Audio generation not found', 404);
                return;
            }
            if (!$this->audioGenerationRepository->belongsToStory($finalAudioGenerationId, $id)) {
                jsonError('Audio generation does not belong to this story', 400);
                return;
            }
        }

        $updatedStory = $this->repository->updateFinalGenerations($id, $finalTextGenerationId, $finalAudioGenerationId);

        if (!$updatedStory) {
            jsonError('Unable to update story', 500);
            return;
        }

        jsonResponse($updatedStory->toArray());
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

