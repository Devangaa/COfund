<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Update Baru dari Kampanye</title>
</head>
<body>
    <h2>🆕 Update Baru!</h2>
    <p>Hai {{ $backer->name }},</p>
    <p>Kreator kampanye Anda <strong>{{ $campaign->title }}</strong> baru saja memposting pembaruan.</p>

    <table style="margin: 20px 0; padding: 15px; background: #f8f9fa; border-left: 4px solid #3498db; border-radius: 5px;">
        <tr>
            <td><strong>Judul Update:</strong></td>
            <td>{{ $update->title }}</td>
        </tr>
    </table>

    <p>Terima kasih telah mendukung kampanye ini. Jadwalkan dukungan Anda sebelum deadline jika belum selesai!</p>

    <p>Salam hangat,<br>Tim CoFund</p>
</body>
</html>
