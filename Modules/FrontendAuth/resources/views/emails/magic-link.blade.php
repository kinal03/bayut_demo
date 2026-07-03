<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>Magic Login Link</title>
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

                <table width="600" cellpadding="0" cellspacing="0"
                    style="
                        background:#ffffff;
                        margin-top:40px;
                        border-radius:10px;
                        overflow:hidden;
                    ">

                    <tr>
                        <td style="
                            background:#111827;
                            padding:30px;
                            text-align:center;
                            color:#ffffff;
                            font-size:24px;
                            font-weight:bold;
                        ">
                            Secure Login Link
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
                                Click the button below to securely
                                login to your account.
                            </p>

                            <div style="
                                text-align:center;
                                margin-top:35px;
                                margin-bottom:35px;
                            ">

                                <a href="{{ $url }}"
                                    style="
                                        background:#16a34a;
                                        color:#ffffff;
                                        padding:14px 30px;
                                        text-decoration:none;
                                        border-radius:6px;
                                        display:inline-block;
                                        font-size:16px;
                                        font-weight:bold;
                                    ">
                                    Login Now
                                </a>

                            </div>

                            <p style="
                                font-size:14px;
                                color:#777777;
                                line-height:22px;
                            ">
                                This link can only be used once and
                                expires in 15 minutes.
                            </p>

                            <p style="
                                font-size:14px;
                                color:#777777;
                                line-height:22px;
                            ">
                                If you did not request this email,
                                please ignore it.
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