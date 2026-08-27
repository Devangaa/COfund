<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Kampanye Disetujui</title>
</head>
<body>
    <h2>🎉 Selamat!</h2>
    <p>Hai {{ $creator->name }},</p>

    <p>Kampanye Anda <strong>{{ $campaign->title }}</strong> telah disetujui oleh admin dan sekarang sudah aktif!</p>

    <p>Kampanye ini kini dapat dilihat oleh publik dan menerima dukungan dari backer.</p>

    <table style="margin: 20px 0; padding: 15px; background: #f8f9fa; border-radius: 5px;">
        <tr>
            <td><strong>Judul:</strong></td>
            <td>{{ $campaign->title }}</td>
        </tr>
        <tr>
            <td><strong>Target Dana:</strong></td>
            <td>Rp {{ number_format($campaign->target_amount, 0, ',', '.') }}</td>
        </tr>
        <tr>
            <td><strong>Deadline:</strong></td>
            <td>{{ \Carbon\Carbon::parse($campaign->deadline)->format('d F Y') }}</td>
        </tr>
    </table>

    <p>Berbagi kampanye Anda ke media sosial dapat meningkatkan visibilitas.</p>

    <p>Salam hangat,<br>Tim CoFund</p>
</body>
</html>
