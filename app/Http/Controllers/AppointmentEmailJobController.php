<?php

namespace App\Http\Controllers;

use App\Models\AppointmentEmailDelivery;
use Illuminate\Http\Request;

class AppointmentEmailJobController extends Controller
{
    public function index(Request $request)
    {
        $query = AppointmentEmailDelivery::query()->orderByDesc('updated_at');

        if ($request->filled('status')) {
            $query->where('status', (string) $request->status);
        }

        if ($request->filled('activity_id')) {
            $query->where('activity_id', (string) $request->activity_id);
        }

        $items = $query->get()->map(fn (AppointmentEmailDelivery $job) => $this->serialize($job))->values();

        return response()->json([
            'data' => $items,
            'meta' => [
                'total' => $items->count(),
                'filtered_by' => [
                    'status' => $request->query('status'),
                    'activity_id' => $request->query('activity_id'),
                ],
            ],
        ]);
    }

    public function summary()
    {
        $totals = [
            'queued' => AppointmentEmailDelivery::where('status', 'queued')->count(),
            'processing' => AppointmentEmailDelivery::where('status', 'processing')->count(),
            'sent' => AppointmentEmailDelivery::where('status', 'sent')->count(),
            'expired' => AppointmentEmailDelivery::where('status', 'expired')->count(),
            'failed' => AppointmentEmailDelivery::where('status', 'failed')->count(),
        ];

        return response()->json([
            'data' => [
                'counts' => $totals,
            ],
            'meta' => [
                'total' => array_sum($totals),
                'queued' => $totals['queued'],
                'processing' => $totals['processing'],
                'sent' => $totals['sent'],
                'expired' => $totals['expired'],
                'failed' => $totals['failed'],
            ],
        ]);
    }

    private function serialize(AppointmentEmailDelivery $job): array
    {
        $appointmentData = $job->appointment_data ?? [];

        return [
            'activity_id' => $job->activity_id,
            'recipient_email' => $job->recipient_email,
            'patient_name' => $appointmentData['patient_name'] ?? null,
            'requested_at' => $appointmentData['requested_at'] ?? null,
            'appointment_status' => $appointmentData['appointment_status'] ?? null,
            'status' => $job->status,
            'claim_token' => $job->claim_token,
            'sending_at' => $job->sending_at?->toIso8601String(),
            'sent_at' => $job->sent_at?->toIso8601String(),
            'created_at' => $job->created_at?->toIso8601String(),
            'updated_at' => $job->updated_at?->toIso8601String(),
        ];
    }
}
