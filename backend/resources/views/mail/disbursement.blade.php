<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Dana Berhasil Dicairkan</title>
</head>
<body>
    <h2>🎉 Selamat!</h2>
    <p>Hai {{ $creator->name }},</p>
    <p>Kampanye Anda <strong>{{ $campaign->title }}</strong> telah berhasil mencapai target dan dana telah dicairkan ke akun Anda.</p>

    <table style="margin: 20px 0; padding: 15px; background: #f8f9fa; border-radius: 5px;">
        <tr>
            <td><strong>Jumlah Disbursement:</strong></td>
            <td>Rp {{ number_format($disbursementAmount, 0, ',', '.') }}</td>
        </tr>
        <tr>
            <td><strong>Biaya Platform (5%):</strong></td>
            <td>Rp {{ number_format($platformFee, 0, ',', '.') }}</td>
        </tr>
        <tr>
            <td><strong>Total Terkumpul:</strong></td>
            <td>Rp {{ number_format($campaign->collected_amount, 0, ',', '.') }}</td>
        </tr>
    </table>

    <p>Dana telah otomatis ditambahkan ke saldo Anda. Terima kasih atas partisipasi di CoFund!</p>

    <p>Salam hangat,<br>Tim CoFund</p>
</body>
</html>
