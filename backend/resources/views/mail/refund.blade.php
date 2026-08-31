<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Dana Dikembalikan</title>
</head>
<body>
    <h2>📢 Informasi Refund</h2>
    <p>Hai {{ $backer->name }},</p>

    <p>Kampanye <strong>{{ $campaign->title }}</strong> yang Anda dukung telah berakhir tanpa mencapai target dana. Dana backing Anda akan dikembalikan penuh.</p>

    <table style="margin: 20px 0; padding: 15px; background: #f8f9fa; border-radius: 5px;">
        <tr>
            <td><strong>Nominal Refund:</strong></td>
            <td>Rp {{ number_format($amount, 0, ',', '.') }}</td>
        </tr>
        <tr>
            <td><strong>Kampanye:</strong></td>
            <td>{{ $campaign->title }}</td>
        </tr>
    </table>

    <p>Dana telah otomatis ditambahkan ke saldo Anda. Terima kasih atas dukungan Anda.</p>

    <p>Salam hangat,<br>Tim CoFund</p>
</body>
</html>
