<?php

namespace Tests\Feature;

use App\Jobs\ConvertWebmToMp4;
use App\Jobs\SendAppointmentEmail;
use App\Mail\AppointmentAccepted;
use App\Models\AppointmentEmailDelivery;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AppointmentEmailWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.appointment_api.url' => 'https://live.example.test',
            'services.appointment_api.token' => 'test-token',
        ]);
    }

    public function test_poll_claims_and_queues_appointments_within_the_reminder_window(): void
    {
        CarbonImmutable::setTestNow('2026-10-02T15:00:00Z');
        Queue::fake();
        Http::fake([
            'https://live.example.test/api/internal/appointment-email-candidates*' => Http::response([
                'data' => [$this->appointment()],
                'next_cursor' => null,
            ]),
            'https://live.example.test/api/internal/appointments/activity-8391/email-status' => Http::response([
                'claimed' => true,
            ]),
        ]);

        $this->artisan('appointments:poll-email-reminders')->assertSuccessful();

        Queue::assertPushedOn('appointment-emails', SendAppointmentEmail::class, function (SendAppointmentEmail $job) {
            return $job->activityId === 'activity-8391';
        });
        Queue::assertNotPushed(ConvertWebmToMp4::class);
        $this->assertDatabaseHas('appointment_email_deliveries', [
            'activity_id' => 'activity-8391',
            'recipient_email' => 'taylor@example.com',
            'status' => 'queued',
        ]);
        Http::assertSent(fn (Request $request) => $request->method() === 'PATCH'
            && $request->url() === 'https://live.example.test/api/internal/appointments/activity-8391/email-status'
            && $request['email_sent_status'] === 'processing');

        CarbonImmutable::setTestNow();
    }

    public function test_poll_persists_email_jobs_to_the_database_queue(): void
    {
        CarbonImmutable::setTestNow('2026-10-02T15:00:00Z');
        Http::fake([
            'https://live.example.test/api/internal/appointment-email-candidates*' => Http::response([
                'data' => [$this->appointment()],
                'next_cursor' => null,
            ]),
            'https://live.example.test/api/internal/appointments/activity-8391/email-status' => Http::response([
                'claimed' => true,
            ]),
        ]);

        $this->artisan('appointments:poll-email-reminders')->assertSuccessful();

        $this->assertDatabaseHas('jobs', [
            'queue' => 'appointment-emails',
        ]);

        CarbonImmutable::setTestNow();
    }

    public function test_poll_queues_tomorrow_appointments_even_when_more_than_24_hours_away(): void
    {
        CarbonImmutable::setTestNow('2026-10-06T02:00:00Z');
        Queue::fake();
        Http::fake([
            'https://live.example.test/api/internal/appointment-email-candidates*' => Http::response([
                'data' => [
                    $this->appointment([
                        'appointment_id' => 'appt-tomorrow',
                        'activity_id' => 'activity-tomorrow',
                        'requested_at' => '2026-10-07T23:30:00+08:00',
                    ]),
                    $this->appointment([
                        'appointment_id' => 'appt-later',
                        'activity_id' => 'activity-later',
                        'requested_at' => '2026-10-08T09:00:00+08:00',
                    ]),
                ],
                'next_cursor' => null,
            ]),
            'https://live.example.test/api/internal/appointments/*/email-status' => Http::response([
                'claimed' => true,
            ]),
        ]);

        $this->artisan('appointments:poll-email-reminders')->assertSuccessful();

        Queue::assertPushedOn('appointment-emails', SendAppointmentEmail::class, fn (SendAppointmentEmail $job) => $job->activityId === 'activity-tomorrow');
        Queue::assertNotPushed(SendAppointmentEmail::class, fn (SendAppointmentEmail $job) => $job->activityId === 'activity-later');

        CarbonImmutable::setTestNow();
    }

    public function test_poll_uses_the_unique_activity_id_when_appointment_ids_repeat(): void
    {
        CarbonImmutable::setTestNow('2026-10-06T02:00:00Z');
        Queue::fake();
        Http::fake([
            'https://live.example.test/api/internal/appointment-email-candidates*' => Http::response([
                'data' => [
                    $this->appointment([
                        'appointment_id' => 'appt-shared',
                        'activity_id' => 'activity-ambiguous',
                        'requested_at' => '2026-10-07T10:10:00+08:00',
                    ]),
                    $this->appointment([
                        'appointment_id' => 'appt-shared',
                        'activity_id' => 'activity-valid',
                        'requested_at' => '2026-10-07T11:10:00+08:00',
                    ]),
                ],
                'next_cursor' => null,
            ]),
            'https://live.example.test/api/internal/appointments/*/email-status' => Http::sequence()
                ->push(['claimed' => true], 200)
                ->push(['claimed' => true], 200),
        ]);

        $this->artisan('appointments:poll-email-reminders')->assertSuccessful();

        Queue::assertPushedOn('appointment-emails', SendAppointmentEmail::class, fn (SendAppointmentEmail $job) => $job->activityId === 'activity-valid');
        Queue::assertPushedOn('appointment-emails', SendAppointmentEmail::class, fn (SendAppointmentEmail $job) => $job->activityId === 'activity-ambiguous');
        $this->assertDatabaseHas('appointment_email_deliveries', [
            'activity_id' => 'activity-valid',
            'appointment_data->appointment_id' => 'appt-shared',
        ]);

        CarbonImmutable::setTestNow();
    }

    public function test_poll_marks_past_appointments_expired_without_queueing_them(): void
    {
        CarbonImmutable::setTestNow('2026-10-02T15:00:00Z');
        Queue::fake();
        Http::fake([
            'https://live.example.test/api/internal/appointment-email-candidates*' => Http::response([
                'data' => [$this->appointment(['requested_at' => '2026-10-02T14:00:00Z'])],
                'next_cursor' => null,
            ]),
            'https://live.example.test/api/internal/appointments/activity-8391/email-status' => Http::response(['updated' => true]),
        ]);

        $this->artisan('appointments:poll-email-reminders')->assertSuccessful();

        Queue::assertNothingPushed();
        Http::assertSent(fn (Request $request) => $request->method() === 'PATCH'
            && $request['email_sent_status'] === 'expired');

        CarbonImmutable::setTestNow();
    }

    public function test_poll_marks_non_accepted_past_appointments_expired_before_queueing(): void
    {
        CarbonImmutable::setTestNow('2026-10-02T15:00:00Z');
        Queue::fake();
        Http::fake([
            'https://live.example.test/api/internal/appointment-email-candidates*' => Http::response([
                'data' => [$this->appointment([
                    'appointment_status' => 'completed',
                    'requested_at' => '2026-10-02T14:00:00Z',
                ])],
                'next_cursor' => null,
            ]),
            'https://live.example.test/api/internal/appointments/activity-8391/email-status' => Http::response(['updated' => true]),
        ]);

        $this->artisan('appointments:poll-email-reminders')->assertSuccessful();

        Queue::assertNothingPushed();
        Http::assertSent(fn (Request $request) => $request->method() === 'PATCH'
            && $request['email_sent_status'] === 'expired');

        CarbonImmutable::setTestNow();
    }

    public function test_poll_reclaims_an_expired_processing_claim(): void
    {
        CarbonImmutable::setTestNow('2026-10-02T15:00:00Z');
        Queue::fake();
        Http::fake([
            'https://live.example.test/api/internal/appointment-email-candidates*' => Http::response([
                'data' => [$this->appointment([
                    'email_sent_status' => 'processing',
                    'claimed_until' => '2026-10-02T14:30:00Z',
                ])],
                'next_cursor' => null,
            ]),
            'https://live.example.test/api/internal/appointments/activity-8391/email-status' => Http::response([
                'claimed' => true,
            ]),
        ]);
        Queue::fake();
        Http::fake([
            'https://live.example.test/api/internal/appointment-email-candidates*' => Http::response([
                'data' => [$this->appointment([
                    'email_sent_status' => 'processing',
                    'claimed_until' => '2026-10-02T14:30:00Z',
                ])],
                'next_cursor' => null,
            ]),
            'https://live.example.test/api/internal/appointments/activity-8391/email-status' => Http::response([
                'claimed' => true,
            ]),
        ]);

        $this->artisan('appointments:poll-email-reminders')->assertSuccessful();

        Queue::assertPushedOn('appointment-emails', SendAppointmentEmail::class);

        CarbonImmutable::setTestNow();
    }

    public function test_successful_email_is_not_resent_when_the_live_status_callback_retries(): void
    {
        CarbonImmutable::setTestNow('2026-10-02T15:00:00Z');
        Mail::fake();
        Http::fake([
            'https://live.example.test/api/internal/appointments/activity-8391/email-status' => Http::sequence()
                ->push(['error' => 'temporary failure'], 500)
                ->push(['updated' => true], 200),
        ]);
        AppointmentEmailDelivery::create([
            'activity_id' => 'activity-8391',
            'recipient_email' => 'taylor@example.com',
            'appointment_data' => $this->appointment(),
            'claim_token' => 'claim-8391',
            'status' => 'queued',
        ]);

        $job = new SendAppointmentEmail('activity-8391');
        try {
            $job->handle(app(\App\Services\LiveAppointmentApi::class));
            $this->fail('The failed status callback should be retried.');
        } catch (\Illuminate\Http\Client\RequestException) {
        }
        $job->handle(app(\App\Services\LiveAppointmentApi::class));

        Mail::assertSent(AppointmentAccepted::class, fn (AppointmentAccepted $mail) => $mail->hasTo('taylor@example.com'));
        Mail::assertSentCount(1);
        $html = (new AppointmentAccepted($this->appointment()))->render();
        $this->assertStringContainsString('Telemedicine Appointment Accepted', $html);
        $this->assertStringContainsString('Add to Google Calendar', $html);
        $this->assertStringContainsString('video.example.test/join/appt-8391', $html);
        $this->assertDatabaseHas('appointment_email_deliveries', [
            'activity_id' => 'activity-8391',
            'status' => 'sent',
        ]);

        CarbonImmutable::setTestNow();
    }

    private function appointment(array $overrides = []): array
    {
        return array_merge([
            'appointment_id' => 'appt-8391',
            'activity_id' => 'activity-8391',
            'patient_name' => 'Taylor Morgan',
            'patient_email' => 'taylor@example.com',
            'requested_at' => '2026-10-03T10:00:00Z',
            'preferred_doctor' => 'Dr. Kari Michael Manayan Lopez',
            'facility' => 'Primary Health Care Facility',
            'facility_address' => '123 Main Street',
            'video_url' => 'https://video.example.test/join/appt-8391',
            'appointment_status' => 'accepted',
            'email_sent_status' => 'pending',
        ], $overrides);
    }
}
