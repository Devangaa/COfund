<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Konfirmasi Backing</title>
</head>
<body>
    <h2>✅ Backing Berhasil!</h2>
    <p>Hai {{ $backer->name }},</p>

    <p>Terima kasih! Backing Anda untuk kampanye <strong>{{ $campaign->title }}</strong> telah berhasil diproses.</p>

    <table style="margin: 20px 0; padding: 15px; background: #f8f9fa; border-radius: 5px;">
        <tr>
            <td><strong>Nominal Backing:</strong></td>
            <td>Rp {{ number_format($backing->amount, 0, ',', '.') }}</td>
        </tr>
        <tr>
            <td><strong>Kampanye:</strong></td>
            <td>{{ $campaign->title }}</td>
        </tr>
        <tr>
            <td><strong>Status:</strong></td>
            <td>Completed</td>
        </tr>
    </table>

    <p>Dana Anda kini berada dalam escrow virtual dan akan dicairkan sesuai aturan kampanye.</p>

    <p>Salam hangat,<br>Tim CoFund</p>
</body>
</html>
