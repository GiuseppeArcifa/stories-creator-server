<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

class AudioGenerationService
{
    private string $apiUrl;
    private ?string $apiKey;

    public function __construct(string $apiUrl, ?string $apiKey = null)
    {
        $this->apiUrl = $apiUrl;
        $this->apiKey = $apiKey;
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array{audio_file_id: string, duration_seconds?: int}
     *
     * @throws RuntimeException
     */
    public function generateAudio(array $payload): array
    {
        if (empty($this->apiUrl)) {
            throw new RuntimeException('AI audio generation URL is not configured');
        }

        $ch = curl_init($this->apiUrl);

        if ($ch === false) {
            throw new RuntimeException('Unable to initialize cURL');
        }

        $jsonPayload = json_encode($payload, JSON_UNESCAPED_UNICODE);

        if ($jsonPayload === false) {
            throw new RuntimeException('Unable to encode payload to JSON');
        }

        $headers = [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($jsonPayload),
        ];

        if ($this->apiKey !== null && $this->apiKey !== '') {
            $headers[] = 'Authorization: Bearer ' . $this->apiKey;
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS => $jsonPayload,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 60 * 60, // 1 ora timeout (generazione audio può richiedere più tempo)
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);

        curl_close($ch);

        if ($response === false || !empty($curlError)) {
            throw new RuntimeException('cURL error: ' . ($curlError ?: 'Unknown error'));
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            throw new RuntimeException(sprintf('API returned HTTP %d', $httpCode));
        }

        $decoded = json_decode($response, true);

        if ($decoded === null || !is_array($decoded)) {
            throw new RuntimeException('Invalid JSON response from API');
        }

        $decoded = current( $response );

        if (!isset($decoded['audio_file_id']) || !is_string($decoded['audio_file_id'])) {
            throw new RuntimeException('Response missing or invalid "audio_file_id"');
        }

        $result = [
            'audio_file_id' => $decoded['audio_file_id'],
        ];

        if (isset($decoded['duration_seconds']) && is_int($decoded['duration_seconds'])) {
            $result['duration_seconds'] = $decoded['duration_seconds'];
        }

        return $result;
    }
}

