<?php

declare(strict_types=1);

namespace App\Models;

class Story
{
    public ?int $id;
    public string $title;
    public string $type;
    public string $plot;
    public string $teachings;
    public ?string $other_notes;
    public ?int $final_text_generation_id;
    public ?int $final_audio_generation_id;
    public ?int $duration_minutes;
    public string $created_at;
    public string $updated_at;

    /**
     * @param array<string, mixed> $attributes
     */
    public function __construct(array $attributes)
    {
        $this->id = isset($attributes['id']) ? (int) $attributes['id'] : null;
        $this->title = (string) ($attributes['title'] ?? '');
        $this->type = (string) ($attributes['type'] ?? '');
        $this->plot = (string) ($attributes['plot'] ?? '');
        $this->teachings = (string) ($attributes['teachings'] ?? '');
        $this->other_notes = isset($attributes['other_notes']) ? (string) $attributes['other_notes'] : null;
        $this->final_text_generation_id = isset($attributes['final_text_generation_id']) ? (int) $attributes['final_text_generation_id'] : null;
        $this->final_audio_generation_id = isset($attributes['final_audio_generation_id']) ? (int) $attributes['final_audio_generation_id'] : null;
        $this->duration_minutes = isset($attributes['duration_minutes']) ? (int) $attributes['duration_minutes'] : null;
        $this->created_at = (string) ($attributes['created_at'] ?? '');
        $this->updated_at = (string) ($attributes['updated_at'] ?? '');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'type' => $this->type,
            'plot' => $this->plot,
            'teachings' => $this->teachings,
            'other_notes' => $this->other_notes,
            'final_text_generation_id' => $this->final_text_generation_id,
            'final_audio_generation_id' => $this->final_audio_generation_id,
            'duration_minutes' => $this->duration_minutes,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}

