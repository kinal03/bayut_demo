<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Invitation</title>
</head>
<body style="font-family: Arial, sans-serif; background:#f5f5f5; padding:20px;">

<div style="max-width:600px;margin:auto;background:#ffffff;padding:30px;border-radius:8px;">

    <h2>You're Invited!</h2>

    <p>
        Hello {{ $invitation['first_name'] }} {{ $invitation['last_name'] }},
    </p>

    <p>
        <strong>
            {{ $inviter['first_name'] ?? $inviter['name'] }}
        </strong>
        has invited you to join
        <strong>{{ config('app.name') }}</strong>.
    </p>

    <p>
        Click the button below to accept the invitation and create your account.
    </p>

    <div style="margin:30px 0;text-align:center;">
        <a href="{{ $frontendUrl }}"
           style="
                background:#2563eb;
                color:#ffffff;
                text-decoration:none;
                padding:12px 24px;
                border-radius:5px;
                display:inline-block;
           ">
            Accept Invitation
        </a>
    </div>

    <p>
        This invitation will expire on:
        <strong>
            {{ \Carbon\Carbon::parse($invitation['expires_at'])->format('d M Y h:i A') }}
        </strong>
    </p>

    <p>
        If you were not expecting this invitation, you can safely ignore this email.
    </p>

    <hr>

    <p style="font-size:12px;color:#666;">
        If the button above does not work, copy and paste the following URL into your browser:
    </p>

    <p style="font-size:12px;word-break:break-all;">
        {{ $frontendUrl }}
    </p>

    <br>

    <p>
        Regards,<br>
        {{ config('app.name') }}
    </p>

</div>

</body>
</html>