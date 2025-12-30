<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            line-height: 1.6;
            color: #374151;
            margin: 0;
            padding: 0;
            background-color: #f9fafb;
        }
        .container {
            max-width: 600px;
            margin: 40px auto;
            background: #ffffff;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1);
            border: 1px solid #e5e7eb;
        }
        .header {
            background-color: #111827;
            padding: 32px;
            text-align: center;
        }
        .header h1 {
            color: #ffffff;
            margin: 0;
            font-size: 24px;
            font-weight: 700;
        }
        .content {
            padding: 40px;
            text-align: center;
        }
        .content p {
            margin-bottom: 24px;
        }
        .btn {
            display: inline-block;
            padding: 12px 32px;
            background-color: #465fff;
            color: #ffffff !important;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            margin-bottom: 32px;
        }
        .footer {
            padding: 32px;
            text-align: center;
            font-size: 14px;
            color: #6b7280;
            border-top: 1px solid #f3f4f6;
        }
        .sub-link {
            font-size: 12px;
            color: #9ca3af;
            word-break: break-all;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>TrustLab Verification</h1>
        </div>
        <div class="content">
            <p>Hello {{ $name }},</p>
            
            <p>Thank you for joining TrustLab! Before you can start managing your certificates and API keys, we need you to verify your email address.</p>

            <a href="{{ $url }}" class="btn">Verify Email Address</a>

            <p>If you did not create an account, no further action is required.</p>

            <div class="sub-link">
                <p>If you're having trouble clicking the "Verify Email Address" button, copy and paste the URL below into your web browser:</p>
                <p>{{ $url }}</p>
            </div>
        </div>
        <div class="footer">
            &copy; {{ date('Y') }} TrustLab. All rights reserved.
        </div>
    </div>
</body>
</html>
