<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Password Reset Verification</title>
</head>
<body style="font-family: Arial, sans-serif; background:#f4f4f4; padding:20px;">

    <table width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td align="center">

                <table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff; padding:30px; border-radius:8px;">

                    <tr>
                        <td>

                            <h2 style="margin-top:0;">
                                Password Reset Verification
                            </h2>

                            <p>
                                Hello {{ $user->name }},
                            </p>

                            <p>
                                We received a request to reset your password.
                            </p>

                            <p>
                                Please use the verification code below:
                            </p>

                            <div style="
                                text-align:center;
                                margin:30px 0;
                            ">
                                <span style="
                                    display:inline-block;
                                    font-size:32px;
                                    font-weight:bold;
                                    letter-spacing:8px;
                                    padding:15px 25px;
                                    border:1px solid #ddd;
                                    border-radius:6px;
                                ">
                                    {{ $otp }}
                                </span>
                            </div>

                            <p>
                                This verification code will expire in
                                <strong>10 minutes</strong>.
                            </p>

                            <p>
                                If you did not request a password reset,
                                you can safely ignore this email.
                            </p>

                            <br>

                            <p>
                                Regards,<br>
                                {{ config('app.name') }}
                            </p>

                        </td>
                    </tr>

                </table>

            </td>
        </tr>
    </table>

</body>
</html>