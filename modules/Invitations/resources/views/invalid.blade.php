<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ config('app.name') }}</title>
    <style>
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; font-family: system-ui, sans-serif; background: #faf5ee; color: #3b2f2f; padding: 1rem; }
        main { max-width: 32rem; text-align: center; }
        .emoji { font-size: 3.5rem; }
        h1 { font-size: 1.4rem; margin: .5rem 0 1rem; }
        p { margin: .35rem 0; color: #6b5b5b; }
    </style>
</head>
<body>
<main>
    <div class="emoji" aria-hidden="true">🍬</div>
    <h1>{{ $expired ? 'Ce lien d\'invitation n\'est plus valide' : 'Accès sur invitation' }}</h1>
    @if ($expired)
        <p>Le lien a expiré ou a été désactivé. Demandez-en un nouveau.</p>
        <p>De uitnodigingslink is verlopen of uitgeschakeld. Vraag een nieuwe aan.</p>
        <p>This invitation link has expired or was disabled. Please ask for a new one.</p>
    @else
        <p>Ce catalogue est accessible uniquement via un lien d'invitation.</p>
        <p>Deze catalogus is enkel toegankelijk via een uitnodigingslink.</p>
        <p>This catalogue is only available through an invitation link.</p>
    @endif
</main>
</body>
</html>
