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
        }
        .reply-box {
            background-color: #f3f4f6;
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 32px;
            border-left: 4px solid #3b82f6;
        }
        .footer {
            padding: 32px;
            text-align: center;
            font-size: 14px;
            color: #6b7280;
            border-top: 1px solid #f3f4f6;
        }
        .original-message {
            margin-top: 40px;
            padding-top: 24px;
            border-top: 1px dashed #e5e7eb;
            font-size: 13px;
            color: #9ca3af;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>TrustLab Support</h1>
        </div>
        <div class="content">
            <p>Hello {{ $inquiry->name }},</p>
            
            <div class="reply-box">
                {!! nl2br(e($replyMessage)) !!}
            </div>

            <p>Best regards,<br>The TrustLab Team</p>

            <div class="original-message">
                <p><strong>Original Message:</strong></p>
                <p><em>{{ $inquiry->message }}</em></p>
            </div>
        </div>
        <div class="footer">
            &copy; {{ date('Y') }} TrustLab. All rights reserved.
        </div>
    </div>
</body>
</html>
