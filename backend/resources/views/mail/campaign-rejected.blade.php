<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Kampanye Ditolak</title>
</head>
<body>
    <h2>⚠️ Kamboan Anda Ditolak</h2>
    <p>Hai {{ $creator->name }},</p>

    <p>Kampanye <strong>{{ $campaign->title }}</strong> Anda ditolak oleh admin.</p>

    <table style="margin: 20px 0; padding: 15px; background: #fff3cd; border-radius: 5px;">
        <tr>
            <td><strong>Catatan Penolakan:</strong></td>
            <td>{{ $campaign->rejection_note ?? 'Tanpa catatan' }}</td>
        </tr>
    </table>

    <p>Silakan perbaiki kampanye dan submit kembali.</p>

    <p>Salam hangat,<br>Tim CoFund</p>
</body>
</html>
