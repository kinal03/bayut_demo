<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Password Reset</title>
</head>
<body style="font-family: Arial, sans-serif; background:#f4f4f4; padding:30px;">

    <table width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td align="center">

                <table width="600" cellpadding="0" cellspacing="0"
                       style="background:#ffffff; border-radius:8px; padding:40px;">

                    <tr>
                        <td align="center">
                            <h2 style="color:#333;">
                                Password Reset Request
                            </h2>
                        </td>
                    </tr>

                    <tr>
                        <td>
                            <p>Hello,</p>

                            <p>
                                We received a request to reset your password.
                                Click the button below to create a new password.
                            </p>

                            <p style="text-align:center; margin:35px 0;">
                                <a href="{{ $resetUrl }}"
                                   style="
                                        background:#2563eb;
                                        color:#ffffff;
                                        padding:12px 25px;
                                        text-decoration:none;
                                        border-radius:5px;
                                        display:inline-block;">
                                    Reset Password
                                </a>
                            </p>

                            <p>
                                This link will expire in 60 minutes.
                            </p>

                            <p>
                                If you did not request a password reset,
                                please ignore this email.
                            </p>

                            <hr>

                            <p style="font-size:12px;color:#888;">
                                If the button doesn't work, copy and paste
                                the following URL into your browser:
                            </p>

                            <p style="font-size:12px;color:#666;">
                                {{ $resetUrl }}
                            </p>

                        </td>
                    </tr>

                </table>

            </td>
        </tr>
    </table>

</body>
</html>