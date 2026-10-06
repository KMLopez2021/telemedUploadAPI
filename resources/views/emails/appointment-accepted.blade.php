<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Telemedicine appointment accepted</title>
</head>
<body style="margin:0;background:#f3f5f4;color:#252a28;font-family:Arial,Helvetica,sans-serif;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f3f5f4;padding:28px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:560px;background:#ffffff;padding:28px 32px;">
                    <tr>
                        <td align="center" style="padding-bottom:20px;">
                            <span style="display:inline-block;background:#16b84e;color:#ffffff;border-radius:16px;padding:5px 12px;font-size:10px;font-weight:bold;letter-spacing:.4px;">ACCEPTED STATUS</span>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <h1 style="margin:0 0 12px;text-align:center;font-size:22px;line-height:1.3;">Telemedicine Appointment Accepted</h1>
                            <p style="margin:0 0 20px;text-align:center;color:#606965;font-size:14px;line-height:1.6;">
                                Your telemedicine consultation request has been accepted by the doctor. Please find your appointment details below and use the provided video link to join at the scheduled time.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="border-left:3px solid #42a879;background:#f7f8f7;padding:10px 14px;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                                <tr><td style="padding:9px 0;border-bottom:1px solid #e2e6e3;color:#21805b;font-size:12px;font-weight:bold;width:34%;">Patient Name</td><td style="padding:9px 0;border-bottom:1px solid #e2e6e3;color:#444;font-size:12px;">{{ $appointment['patient_name'] }}</td></tr>
                                <tr><td style="padding:9px 0;border-bottom:1px solid #e2e6e3;color:#21805b;font-size:12px;font-weight:bold;">Requested Date</td><td style="padding:9px 0;border-bottom:1px solid #e2e6e3;color:#444;font-size:12px;">{{ \Carbon\CarbonImmutable::parse($appointment['requested_at'])->format('F j, Y') }}</td></tr>
                                <tr><td style="padding:9px 0;border-bottom:1px solid #e2e6e3;color:#21805b;font-size:12px;font-weight:bold;">Requested Time</td><td style="padding:9px 0;border-bottom:1px solid #e2e6e3;color:#444;font-size:12px;">{{ \Carbon\CarbonImmutable::parse($appointment['requested_at'])->format('g:i A') }}</td></tr>
                                <tr><td style="padding:9px 0;border-bottom:1px solid #e2e6e3;color:#21805b;font-size:12px;font-weight:bold;">Preferred Doctor</td><td style="padding:9px 0;border-bottom:1px solid #e2e6e3;color:#444;font-size:12px;">{{ $appointment['preferred_doctor'] }}</td></tr>
                                <tr><td style="padding:9px 0;border-bottom:1px solid #e2e6e3;color:#21805b;font-size:12px;font-weight:bold;">Facility</td><td style="padding:9px 0;border-bottom:1px solid #e2e6e3;color:#444;font-size:12px;">{{ $appointment['facility'] }}</td></tr>
                                <tr><td style="padding:9px 0;color:#21805b;font-size:12px;font-weight:bold;vertical-align:top;">Facility Address</td><td style="padding:9px 0;color:#444;font-size:12px;">{{ $appointment['facility_address'] }}</td></tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td align="center" style="padding:22px 0 14px;">
                            <a href="{{ $appointment['video_url'] }}" style="display:inline-block;background:#20ad62;color:#ffffff;text-decoration:none;font-size:14px;font-weight:bold;padding:12px 22px;border-radius:3px;">Join Video Call</a>
                        </td>
                    </tr>
                    <tr>
                        <td align="center" style="font-size:12px;">
                            <a href="{{ $calendarUrl }}" style="color:#2379b7;">Add to Google Calendar</a>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>