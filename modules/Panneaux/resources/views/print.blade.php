<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="robots" content="noindex">
    <title>{{ $panel->name }} — impression</title>
    @include('panneaux::partials.board-style')
    <style>
        @page { size: {{ $data['settings']['page']['orientation'] === 'paysage' ? '297mm 210mm' : '210mm 297.15mm' }}; margin: 0; }
        html, body { margin: 0; background: #e5e5e5; }
        .bar { position: sticky; top: 0; z-index: 5; background: #222; color: #fff; padding: .6rem 1rem; display: flex; gap: 1rem; align-items: center; font: 14px system-ui, sans-serif; }
        .bar button { background: #c2185b; color: #fff; border: 0; border-radius: .4rem; padding: .45rem 1rem; font: inherit; cursor: pointer; }
        .bar .hint { opacity: .75; }
        .sheet { padding: 14px; }
        @media print { .bar { display: none !important; } html, body { background: #fff; } .sheet { padding: 0; } }
    </style>
</head>
<body>
    <div class="bar">
        <button type="button" onclick="window.print()">Imprimer</button>
        <span class="hint">{{ $panel->name }} · A4 {{ $data['settings']['page']['orientation'] === 'paysage' ? 'paysage' : 'portrait' }} · marges du navigateur : « Aucune » · échelle : 100 %</span>
    </div>
    <div class="sheet">
        @include('panneaux::partials.board', ['data' => $data, 'editable' => false])
    </div>
    @if (request()->boolean('auto'))
        <script>window.addEventListener('load', () => setTimeout(() => { window.pnlRenderAll(); setTimeout(() => window.print(), 300); }, 300));</script>
    @endif
</body>
</html>
