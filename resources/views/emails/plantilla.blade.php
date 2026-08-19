<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ config('app.name') }}</title>
</head>
<body style="margin:0; padding:24px; background:#f4f3f0; font-family: Arial, Helvetica, sans-serif;">
    <div style="max-width:560px; margin:0 auto; background:#ffffff; border:1px solid #e1e0d9; border-radius:10px; padding:28px 32px;">
        <div style="font-size:13px; color:#4a3aa7; font-weight:700; letter-spacing:.02em; margin-bottom:18px;">
            {{ config('app.name') }}
        </div>
        <div style="white-space:pre-wrap; font-size:14px; line-height:1.6; color:#2b2a27;">{{ $cuerpo }}</div>
    </div>
</body>
</html>
