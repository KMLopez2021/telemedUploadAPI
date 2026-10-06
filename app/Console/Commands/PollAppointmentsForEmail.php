<?php

namespace App\Console\Commands;

use App\Jobs\SendAppointmentEmail;
use App\Models\AppointmentEmailDelivery;
use App\Services\LiveAppointmentApi;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class PollAppointmentsForEmail extends Command
{
    protected $signature = 'appointments:poll-email-reminders';

    protected $description = 'Poll the live appointment API and queue accepted appointment emails';

    public function handle(LiveAppointmentApi $api): int
    {
        $reminderTimezone = config('services.appointment_api.reminder_timezone', 'Asia/Manila');
        $tomorrowStart = CarbonImmutable::now($reminderTimezone)->addDay()->startOfDay();
        $dueBefore = $tomorrowStart->endOfDay();
        $cursor = null;
        $seenCursors = [];
        $queued = 0;
        $expired = 0;

        do {
            $payload = $api->fetchCandidates($dueBefore->toIso8601String(), $cursor);

            foreach ($payload['data'] as $candidate) {
                if (! $this->isValidCandidate($candidate)) {
                    $this->warn('Skipped an invalid appointment candidate.');

                    continue;
                }

                $requestedAt = CarbonImmutable::parse($candidate['requested_at']);
                $activityId = (string) $candidate['activity_id'];

                if ($requestedAt->lessThanOrEqualTo(now())) {
                    $api->updateStatus($activityId, 'expired');
                    $expired++;

                    continue;
                }

                if ($candidate['appointment_status'] !== 'accepted') {
                    continue;
                }

                if ($candidate['email_sent_status'] === 'processing') {
                    $claimExpired = empty($candidate['claimed_until'])
                        || CarbonImmutable::parse($candidate['claimed_until'])->lessThanOrEqualTo(now());

                    if (! $claimExpired) {
                        continue;
                    }
                } elseif ($candidate['email_sent_status'] !== 'pending') {
                    continue;
                }

                if ($requestedAt->setTimezone($reminderTimezone)->toDateString() !== $tomorrowStart->toDateString()
                    || $requestedAt->greaterThan($dueBefore)) {
                    continue;
                }

                $existing = AppointmentEmailDelivery::find($activityId);
                if ($existing?->status === 'sent') {
                    $api->updateStatus($activityId, 'sent', $existing->claim_token);

                    continue;
                }

                $sendingLeaseMinutes = (int) config('services.appointment_api.sending_lease_minutes', 10);
                if ($existing?->status === 'sending' && $existing->sending_at?->greaterThan(now()->subMinutes($sendingLeaseMinutes))) {
                    continue;
                }

                $claimToken = (string) Str::uuid();
                $claimedUntil = now()->addMinutes((int) config('services.appointment_api.claim_minutes', 30))->toIso8601String();

                try {
                    if (! $api->claim($activityId, $claimToken, $claimedUntil)) {
                        continue;
                    }
                } catch (RequestException $exception) {
                    if ($exception->response->status() !== 409) {
                        throw $exception;
                    }

                    Log::warning('Skipped activity because the live API did not accept its email claim.', [
                        'activity_id' => $activityId,
                    ]);
                    $this->warn("Skipped activity {$activityId}: live API rejected the email claim.");

                    continue;
                }

                $delivery = AppointmentEmailDelivery::firstOrCreate(
                    ['activity_id' => $activityId],
                    [
                        'recipient_email' => $candidate['patient_email'],
                        'appointment_data' => $candidate,
                        'claim_token' => $claimToken,
                        'status' => 'queued',
                    ]
                );

                if ($delivery->status === 'sent') {
                    $api->updateStatus($activityId, 'sent', $delivery->claim_token);

                    continue;
                }

                if ($delivery->status === 'sending' && $delivery->sending_at?->greaterThan(now()->subMinutes($sendingLeaseMinutes))) {
                    continue;
                }

                $delivery->forceFill([
                    'recipient_email' => $candidate['patient_email'],
                    'appointment_data' => $candidate,
                    'claim_token' => $claimToken,
                    'status' => 'queued',
                    'sending_at' => null,
                ])->save();

                SendAppointmentEmail::dispatch($activityId)
                    ->onConnection('database')
                    ->onQueue('appointment-emails');
                $queued++;
            }

            $cursor = $payload['next_cursor'] ?? null;
            if ($cursor !== null && (! is_string($cursor) || isset($seenCursors[$cursor]))) {
                $this->error('The appointment candidates API returned an invalid or repeated cursor.');

                return self::FAILURE;
            }
            if ($cursor !== null) {
                $seenCursors[$cursor] = true;
            }
        } while ($cursor !== null);

        $this->info("Queued {$queued} appointment email(s); expired {$expired} past appointment(s).");

        return self::SUCCESS;
    }

    private function isValidCandidate(mixed $candidate): bool
    {
        if (! is_array($candidate)) {
            return false;
        }

        return Validator::make($candidate, [
            'appointment_id' => ['required', 'string', 'max:191'],
            'activity_id' => ['required', 'string', 'max:191'],
            'patient_name' => ['required', 'string', 'max:255'],
            'patient_email' => ['required', 'email', 'max:255'],
            'requested_at' => [
                'required',
                'date',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! is_string($value) || ! preg_match('/(?:Z|[+-]\d{2}:\d{2})$/i', $value)) {
                        $fail('The requested appointment time must include a timezone.');
                    }
                },
            ],
            'preferred_doctor' => ['required', 'string', 'max:255'],
            'facility' => ['required', 'string', 'max:255'],
            'facility_address' => ['required', 'string', 'max:1000'],
            'video_url' => ['required', 'url', 'max:2048'],
            'appointment_status' => ['required', 'string'],
            'email_sent_status' => ['required', 'string'],
            'claimed_until' => ['nullable', 'date'],
        ])->passes();
    }
}
