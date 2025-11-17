<?php

declare(strict_types=1);

namespace App\Models;

class AudioGeneration
{
    public ?int $id;
    public int $story_id;
    public int $text_generation_id;
    public string $audio_file_id;
    public ?int $duration_seconds;
    public ?string $voice_name;
    public ?string $provider;
    public string $created_at;

    /**
     * @param array<string, mixed> $attributes
     */
    public function __construct(array $attributes)
    {
        $this->id = isset($attributes['id']) ? (int) $attributes['id'] : null;
        $this->story_id = (int) ($attributes['story_id'] ?? 0);
        $this->text_generation_id = (int) ($attributes['text_generation_id'] ?? 0);
        $this->audio_file_id = (string) ($attributes['audio_file_id'] ?? '');
        $this->duration_seconds = isset($attributes['duration_seconds']) ? (int) $attributes['duration_seconds'] : null;
        $this->voice_name = isset($attributes['voice_name']) ? (string) $attributes['voice_name'] : null;
        $this->provider = isset($attributes['provider']) ? (string) $attributes['provider'] : null;
        $this->created_at = (string) ($attributes['created_at'] ?? '');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'story_id' => $this->story_id,
            'text_generation_id' => $this->text_generation_id,
            'audio_file_id' => $this->audio_file_id,
            'duration_seconds' => $this->duration_seconds,
            'voice_name' => $this->voice_name,
            'provider' => $this->provider,
            'created_at' => $this->created_at,
        ];
    }
}

