<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $jenis }}</title>
</head>
<body style="margin:0;padding:24px;background:#faf6f0;font-family:Arial,Helvetica,sans-serif;color:#2b2b2b;">
    <div style="max-width:560px;margin:0 auto;background:#ffffff;border-radius:12px;padding:24px;">
        <h2 style="margin:0 0 16px;color:#b45f06;">MeowNesa</h2>
        <p style="margin:0 0 8px;font-size:13px;color:#777;">{{ $jenis }}</p>
        <p style="margin:0;font-size:15px;line-height:1.6;">{!! nl2br(e($isi)) !!}</p>
        <hr style="border:none;border-top:1px solid #eee;margin:24px 0 12px;">
        <p style="margin:0;font-size:12px;color:#999;">
            Pesan otomatis dari Jaga Satwa UNESA. Mohon tidak membalas email ini.
        </p>
    </div>
</body>
</html>
