<?php

namespace App\Jobs;

use App\Mail\AppointmentAccepted;
use App\Models\AppointmentEmailDelivery;
use App\Services\LiveAppointmentApi;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendAppointmentEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public int $timeout = 60;

    public array $backoff = [30, 90, 180];

    public function __construct(public string $activityId)
    {
        $this->onQueue('appointment-emails');
    }

    public function handle(LiveAppointmentApi $api): void
    {
        $delivery = AppointmentEmailDelivery::findOrFail($this->activityId);

        if ($delivery->status === 'sent') {
            $api->updateStatus($this->activityId, 'sent', $delivery->claim_token);

            return;
        }

        $staleBefore = now()->subMinutes((int) config('services.appointment_api.sending_lease_minutes', 10));
        $claimed = AppointmentEmailDelivery::query()
            ->whereKey($this->activityId)
            ->where(function ($query) use ($staleBefore) {
                $query->where('status', 'queued')
                    ->orWhere(function ($query) use ($staleBefore) {
                        $query->where('status', 'sending')
                            ->where('sending_at', '<=', $staleBefore);
                    });
            })
            ->update([
                'status' => 'sending',
                'sending_at' => now(),
                'updated_at' => now(),
            ]);

        if ($claimed === 0) {
            return;
        }

        $delivery->refresh();

        try {
            Mail::to($delivery->recipient_email)->send(new AppointmentAccepted($delivery->appointment_data));
        } catch (Throwable $exception) {
            $delivery->forceFill([
                'status' => 'queued',
                'sending_at' => null,
            ])->save();

            throw $exception;
        }

        $delivery->forceFill([
            'status' => 'sent',
            'sent_at' => now(),
            'recipient_email' => null,
            'appointment_data' => null,
        ])->save();

        $api->updateStatus($this->activityId, 'sent', $delivery->claim_token);
    }
}
