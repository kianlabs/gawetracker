<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verifikasi email GaweTracker</title>
</head>
<body style="margin:0; padding:0; background-color:#f4f4f5; font-family:Arial,Helvetica,sans-serif; color:#18181b;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f4f5; padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:480px; background-color:#ffffff; border-radius:12px; padding:32px;">
                    <tr>
                        <td>
                            <h1 style="margin:0 0 8px; font-size:20px; color:#18181b;">GaweTracker</h1>
                            <p style="margin:0 0 16px; font-size:14px; line-height:1.6; color:#52525b;">
                                Halo {{ $user->name }}, terima kasih sudah mendaftar.
                                Klik tombol di bawah untuk memverifikasi alamat email Anda.
                            </p>
                            <p style="margin:0 0 24px; text-align:center;">
                                <a href="{{ $url }}"
                                   style="display:inline-block; padding:12px 24px; background-color:#18181b; color:#ffffff; font-size:14px; font-weight:600; text-decoration:none; border-radius:8px;">
                                    Verifikasi Email
                                </a>
                            </p>
                            <p style="margin:0 0 16px; font-size:13px; line-height:1.6; color:#71717a;">
                                Tautan ini berlaku selama 60 menit. Jika tombol tidak berfungsi,
                                salin dan tempel alamat berikut ke peramban Anda:
                            </p>
                            <p style="margin:0 0 24px; font-size:12px; line-height:1.6; color:#2563eb; word-break:break-all;">
                                {{ $url }}
                            </p>
                            <p style="margin:0; font-size:12px; line-height:1.6; color:#a1a1aa;">
                                Jika Anda tidak merasa membuat akun GaweTracker, abaikan saja email ini.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
