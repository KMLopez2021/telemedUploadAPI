<?php

namespace Tests\Feature;

use App\Models\AppointmentEmailDelivery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppointmentEmailStatusApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_a_summary_of_the_appointment_email_workflow(): void
    {
        AppointmentEmailDelivery::create([
            'activity_id' => 'activity-1001',
            'recipient_email' => 'alice@example.com',
            'appointment_data' => [
                'appointment_id' => 'appt-1001',
                'activity_id' => 'activity-1001',
                'patient_name' => 'Alice Example',
                'requested_at' => '2026-10-03T10:00:00Z',
                'appointment_status' => 'accepted',
            ],
            'status' => 'sent',
            'sent_at' => '2026-10-02T15:05:00Z',
        ]);

        AppointmentEmailDelivery::create([
            'activity_id' => 'activity-1002',
            'recipient_email' => 'bob@example.com',
            'appointment_data' => [
                'appointment_id' => 'appt-1002',
                'activity_id' => 'activity-1002',
                'patient_name' => 'Bob Example',
                'requested_at' => '2026-10-03T11:00:00Z',
                'appointment_status' => 'accepted',
            ],
            'status' => 'queued',
        ]);

        $response = $this->getJson('/api/admin/appointment-email-jobs/summary');

        $response->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.sent', 1)
            ->assertJsonPath('meta.queued', 1);
    }

    public function test_it_lists_appointment_email_jobs_with_status_and_patient_details(): void
    {
        AppointmentEmailDelivery::create([
            'activity_id' => 'activity-2001',
            'recipient_email' => 'carol@example.com',
            'appointment_data' => [
                'appointment_id' => 'appt-2001',
                'activity_id' => 'activity-2001',
                'patient_name' => 'Carol Example',
                'requested_at' => '2026-10-03T12:00:00Z',
                'appointment_status' => 'accepted',
            ],
            'status' => 'processing',
            'claim_token' => 'claim-2001',
            'sending_at' => '2026-10-02T15:00:00Z',
        ]);

        $response = $this->getJson('/api/admin/appointment-email-jobs?status=processing&activity_id=activity-2001');

        $response->assertOk()
            ->assertJsonPath('data.0.activity_id', 'activity-2001')
            ->assertJsonPath('data.0.patient_name', 'Carol Example')
            ->assertJsonPath('data.0.status', 'processing');
    }
}
