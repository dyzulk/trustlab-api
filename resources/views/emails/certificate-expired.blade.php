<!DOCTYPE html>
<html>
<head>
    <title>Certificate Expired Alert</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
        <h2 style="color: #a94442;">URGENT: Certificate Has Expired</h2>
        
        <p>Hello {{ $certificate->user->first_name ?? 'User' }},</p>
        
        <p>This is a critical notification that your SSL certificate has <strong>ALREADY EXPIRED</strong>.</p>
        
        <div style="background-color: #fee; padding: 15px; border-radius: 5px; margin: 20px 0; border: 1px solid #ebccd1;">
            <p><strong>Common Name:</strong> {{ $certificate->common_name }}</p>
            <p><strong>Organization:</strong> {{ $certificate->organization }}</p>
            <p><strong>Key Strength:</strong> {{ $certificate->key_bits }}-bit</p>
            <p><strong>Expired On:</strong> {{ $certificate->valid_to->format('d M Y H:i:s') }}</p>
        </div>
        
        <p>Your services using this certificate may be inaccessible or showing security warnings. Please renew immediately.</p>
        
        <a href="{{ config('app.frontend_url', 'http://localhost:3000') }}/dashboard" style="display: inline-block; padding: 10px 20px; background-color: #d9534f; color: white; text-decoration: none; border-radius: 5px;">Renew Now</a>
    </div>
</body>
</html>
