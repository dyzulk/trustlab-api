<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TrustLab Connection Test</title>
    <style>
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            line-height: 1.5;
            color: #374151;
            margin: 0;
            padding: 0;
            background-color: #f9fafb;
        }
        .container {
            max-width: 600px;
            margin: 40px auto;
            background-color: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }
        .header {
            padding: 30px 40px;
            background-color: #eff6ff;
            text-align: center;
        }
        .header img {
            height: 32px;
            margin-bottom: 15px;
        }
        .content {
            padding: 40px;
        }
        .title {
            font-size: 24px;
            font-weight: 700;
            color: #111827;
            margin-bottom: 20px;
        }
        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            background-color: #ecfdf5;
            color: #065f46;
            border-radius: 9999px;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 25px;
        }
        .details {
            background-color: #f3f4f6;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 25px;
        }
        .details p {
            margin: 5px 0;
            font-size: 14px;
        }
        .footer {
            padding: 30px 40px;
            background-color: #f9fafb;
            text-align: center;
            font-size: 12px;
            color: #6b7280;
            border-top: 1px solid #e5e7eb;
        }
        .button {
            display: inline-block;
            padding: 12px 24px;
            background-color: #3b82f6;
            color: #ffffff;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <!-- Using CID for embedding logo or absolute URL if accessible -->
            <img src="{{ url('/images/logo/logo.png') }}" alt="TrustLab Logo">
        </div>
        <div class="content">
            <h1 class="title">SMTP Connection Test</h1>
            <div class="status-badge">Connection Successful</div>
            <p>Hello,</p>
            <p>This is a test email sent from <strong>TrustLab - PKI & Certificate Management</strong> to verify your SMTP configuration.</p>
            
            <div class="details">
                <p><strong>Mailer:</strong> {{ $mailer }}</p>
                <p><strong>Sent At:</strong> {{ now()->format('Y-m-d H:i:s') }}</p>
                <p><strong>Host:</strong> {{ $host }}</p>
            </div>

            <p>If you received this email, it means your SMTP settings for the <strong>{{ $mailer }}</strong> mailer are working correctly.</p>
            
            <a href="{{ env('FRONTEND_URL') }}" class="button">Go to Dashboard</a>
        </div>
        <div class="footer">
            <p>&copy; {{ date('Y') }} TrustLab. All rights reserved.</p>
            <p>This is an automated system message. Please do not reply to this email.</p>
        </div>
    </div>
</body>
</html>
