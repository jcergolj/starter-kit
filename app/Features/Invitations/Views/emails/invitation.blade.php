<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
</head>
<body>
    <p>{{ __('You have been invited to join. Click the link below to complete your registration:') }}</p>
    <p><a href="{{ $acceptUrl }}">{{ __('Accept invitation') }}</a></p>
    <p>{{ __('This invitation expires on :date.', ['date' => $expiresAt->translatedFormat('F j, Y')]) }}</p>
</body>
</html>
