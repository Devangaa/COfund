<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Deadline Approaching</title>
</head>
<body>
    <h2>⚠️ Deadline Approaching!</h2>
    <p>Hai {{ $backer->name }},</p>
    <p>Kampanye yang Anda dukung <strong>{{ $campaign->title }}</strong> akan berakhir dalam {{ $daysRemaining }} hari!</p>

    @if($daysRemaining === 1)
        <p>Pastikan dukungan Anda selesai sebelum deadline.</p>
    @else
        <p>Waktu masih ada, tapi jadwalkan dukungan Anda segera.</p>
    @endif

    <p>Salam hangat,<br>Tim CoFund</p>
</body>
</html>
