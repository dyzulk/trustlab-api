<!DOCTYPE html>
<html>
<head>
    <title>Certificate Expiration Alert</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
        <h2 style="color: #d9534f;">Action Required: Certificate Expiring Soon</h2>
        
        <p>Hello {{ $certificate->user->first_name ?? 'User' }},</p>
        
        <p>This is a notification that one of your SSL certificates is expiring in <strong>{{ $daysRemaining }} days</strong>.</p>
        
        <div style="background-color: #f9f9f9; padding: 15px; border-radius: 5px; margin: 20px 0;">
            <p><strong>Common Name:</strong> {{ $certificate->common_name }}</p>
            <p><strong>Organization:</strong> {{ $certificate->organization }}</p>
            <p><strong>Key Strength:</strong> {{ $certificate->key_bits }}-bit</p>
            <p><strong>Expiration Date:</strong> {{ $certificate->valid_to->format('d M Y H:i:s') }}</p>
        </div>
        
        <p>Please log in to your dashboard to renew this certificate before it expires to ensure uninterrupted service.</p>
        
        <a href="{{ config('app.frontend_url', 'http://localhost:3000') }}/dashboard" style="display: inline-block; padding: 10px 20px; background-color: #007bff; color: white; text-decoration: none; border-radius: 5px;">Go to Dashboard</a>
        
        <p style="margin-top: 30px; font-size: 12px; color: #777;">
            If you have already renewed this certificate, please ignore this message.<br>
            You are receiving this email because you have enabled certificate renewal alerts in your account settings.
        </p>
    </div>
</body>
</html>
