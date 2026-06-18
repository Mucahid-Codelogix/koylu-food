<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <title>{{ $mailSubject }}</title>
</head>
<body style="font-family: Arial, sans-serif; color: #1a1a1a; line-height: 1.5; max-width: 640px; margin: 0 auto; padding: 24px;">
    <h1 style="font-size: 20px; margin: 0 0 12px;">{{ $headline }}</h1>

    <p style="margin: 0 0 16px;">{{ $summary }}</p>

    <div style="background: #fdeaea; border: 1px solid #f5c2c7; border-radius: 8px; padding: 12px 16px; margin-bottom: 24px;">
        <p style="margin: 0; font-size: 14px; color: #842029; white-space: pre-wrap;">{{ $error }}</p>
    </div>

    <p style="margin: 0; font-size: 13px; color: #6b7280;">
        Bekijk de sync-log en mislukte jobs in het admin-panel onder Systeem / Beheer.
    </p>
</body>
</html>
