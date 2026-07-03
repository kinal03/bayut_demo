<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>Verification OTP</title>
</head>

<body style="
    margin:0;
    padding:0;
    background:#f4f4f4;
    font-family:Arial,sans-serif;
">

    <table width="100%" cellpadding="0" cellspacing="0">

        <tr>
            <td align="center">

                <table width="600"
                    cellpadding="0"
                    cellspacing="0"
                    style="
                        background:#ffffff;
                        margin-top:40px;
                        border-radius:10px;
                        overflow:hidden;
                    ">

                    <tr>
                        <td style="
                            background:#2563eb;
                            padding:25px;
                            text-align:center;
                            color:#ffffff;
                            font-size:24px;
                            font-weight:bold;
                        ">
                            Verify Your Email
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:40px;">

                            <p style="
                                font-size:16px;
                                color:#333333;
                            ">
                                Hello,
                            </p>

                            <p style="
                                font-size:15px;
                                color:#555555;
                                line-height:24px;
                            ">
                                Use the following OTP to verify
                                your email address.
                            </p>

                            <div style="
                                text-align:center;
                                margin-top:35px;
                                margin-bottom:35px;
                            ">

                                <span style="
                                    display:inline-block;
                                    background:#eff6ff;
                                    color:#2563eb;
                                    padding:15px 35px;
                                    border-radius:8px;
                                    font-size:32px;
                                    font-weight:bold;
                                    letter-spacing:8px;
                                ">
                                    {{ $otp }}
                                </span>

                            </div>

                            <p style="
                                font-size:14px;
                                color:#777777;
                                line-height:22px;
                            ">
                                This OTP expires in 10 minutes.
                            </p>

                            <p style="
                                font-size:14px;
                                color:#777777;
                                line-height:22px;
                            ">
                                If you did not request this,
                                please ignore this email.
                            </p>

                        </td>
                    </tr>

                    <tr>
                        <td style="
                            background:#f9fafb;
                            padding:20px;
                            text-align:center;
                            font-size:12px;
                            color:#999999;
                        ">
                            © {{ date('Y') }} Your Company.
                            All rights reserved.
                        </td>
                    </tr>

                </table>

            </td>
        </tr>

    </table>

</body>

</html>