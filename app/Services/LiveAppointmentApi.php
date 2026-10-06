<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class LiveAppointmentApi
{
    public function fetchCandidates(string $dueBefore, ?string $cursor = null): array
    {
        $response = $this->request()->get($this->url(config('services.appointment_api.candidates_path')), array_filter([
            'due_before' => $dueBefore,
            'limit' => 100,
            'cursor' => $cursor,
        ], static fn ($value) => $value !== null));

        $response->throw();
        $payload = $response->json();

        if (! is_array($payload) || ! is_array($payload['data'] ?? null)) {
            throw new RuntimeException('The appointment candidates API returned an invalid response.');
        }

        return $payload;
    }

    public function claim(string $activityId, string $claimToken, string $claimedUntil): bool
    {
        $response = $this->request()->patch($this->statusUrl($activityId), [
            'email_sent_status' => 'processing',
            'claim_token' => $claimToken,
            'claimed_until' => $claimedUntil,
        ]);
        $response->throw();

        return (bool) ($response->json('claimed') ?? $response->json('data.claimed') ?? false);
    }

    public function updateStatus(string $activityId, string $status, ?string $claimToken = null): void
    {
        $payload = ['email_sent_status' => $status];

        if ($claimToken !== null) {
            $payload['claim_token'] = $claimToken;
        }

        if ($status === 'sent') {
            $payload['sent_at'] = now()->toIso8601String();
        }

        $this->request()->patch($this->statusUrl($activityId), $payload)->throw();
    }

    private function request(): PendingRequest
    {
        $url = config('services.appointment_api.url');
        if (! is_string($url) || $url === '') {
            throw new RuntimeException('APPOINTMENT_API_URL is not configured.');
        }

        $request = Http::acceptJson()->timeout((int) config('services.appointment_api.timeout', 15));
        $token = config('services.appointment_api.token');

        if (! is_string($token) || $token === '') {
            throw new RuntimeException('APPOINTMENT_API_TOKEN is not configured.');
        }

        return $request->withToken($token);
    }

    private function statusUrl(string $activityId): string
    {
        $path = str_replace(
            ['{activity_id}', '{appointment_id}'],
            rawurlencode($activityId),
            (string) config('services.appointment_api.status_path')
        );

        return $this->url($path);
    }

    private function url(string $path): string
    {
        $baseUrl = (string) config('services.appointment_api.url');

        if ($path === '') {
            return rtrim($baseUrl, '/');
        }

        if (preg_match('#^https?://#i', $path) === 1) {
            return rtrim($path, '/');
        }

        return rtrim($baseUrl, '/').'/'.ltrim($path, '/');
    }
}
