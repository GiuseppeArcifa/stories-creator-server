<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

class TextGenerationService
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
     * @return array<int, array{generated_text: string}>
     *
     * @throws RuntimeException
     */
    public function generateText(array $payload): array
    {
        if (empty($this->apiUrl)) {
            throw new RuntimeException('AI text generation URL is not configured');
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
            CURLOPT_TIMEOUT => 120, // 2 minuti timeout
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

        if (!isset($decoded['generations']) || !is_array($decoded['generations'])) {
            throw new RuntimeException('Response missing "generations" array');
        }

        if (count($decoded['generations']) !== 3) {
            throw new RuntimeException(sprintf('Expected 3 generations, got %d', count($decoded['generations'])));
        }

        $generations = [];

        foreach ($decoded['generations'] as $index => $generation) {
            if (!isset($generation['generated_text']) || !is_string($generation['generated_text'])) {
                throw new RuntimeException(sprintf('Generation %d missing or invalid "generated_text"', $index));
            }

            $generations[] = [
                'generated_text' => $generation['generated_text'],
            ];
        }

        return $generations;
    }
}

