<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
</head>
<body style="margin:0;padding:0;background:#f3f4f6;font-family:'Segoe UI',Arial,sans-serif;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background:#f3f4f6;padding:24px 12px;">
        <tr>
            <td align="center">
                <table width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border-radius:12px;overflow:hidden;border:1px solid #e2e8f0;">
                    <tr>
                        <td style="background:#4f46e5;color:#ffffff;padding:20px 26px;" align="center">
                            <div style="font-size:17px;font-weight:700;">{{ config('app.name') }}</div>
                            <div style="font-size:12px;opacity:.85;margin-top:2px;">Prototype Sistem Absensi & Penggajian RFID</div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:26px;color:#0f172a;font-size:14px;line-height:1.6;">
                            {{ $slot }}
                        </td>
                    </tr>
                    <tr>
                        <td style="background:#f8fafc;padding:14px 26px;font-size:11px;color:#64748b;text-align:center;border-top:1px solid #e2e8f0;">
                            Email ini dikirim otomatis oleh sistem. &bull; {{ now()->translatedFormat('d F Y H:i') }} WIB
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>