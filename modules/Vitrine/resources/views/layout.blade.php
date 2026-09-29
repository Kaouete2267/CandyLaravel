<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', __('vitrine::ui.catalogue')) · {{ config('app.name') }}</title>
    <style>
        :root { --bg:#fbf7f1; --card:#fff; --ink:#33282a; --muted:#7a6a6c; --line:#eadfd3; --brand:#c2185b; --brand-ink:#fff; --soft:#fbe4ee; --warn:#b45309; --warn-soft:#fef3c7; --bad:#b91c1c; --bad-soft:#fee2e2; }
        * { box-sizing: border-box; }
        body { margin:0; font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; background:var(--bg); color:var(--ink); line-height:1.45; }
        a { color: inherit; }
        .wrap { max-width: 72rem; margin: 0 auto; padding: 0 1rem; }
        header.top { background: var(--card); border-bottom: 1px solid var(--line); position: sticky; top: 0; z-index: 10; }
        header.top .wrap { display:flex; align-items:center; gap:1rem; min-height:3.5rem; flex-wrap:wrap; }
        .logo { font-weight:800; font-size:1.15rem; text-decoration:none; color: var(--brand); }
        nav.main { display:flex; gap:.25rem; margin-right:auto; }
        nav.main a { text-decoration:none; padding:.4rem .8rem; border-radius:999px; font-size:.95rem; }
        nav.main a[aria-current="page"] { background: var(--soft); color: var(--brand); font-weight:600; }
        .lang { display:flex; gap:.25rem; }
        .lang a { text-decoration:none; padding:.25rem .55rem; border-radius:.4rem; font-size:.85rem; color:var(--muted); border:1px solid transparent; }
        .lang a[aria-current="true"] { border-color: var(--brand); color: var(--brand); font-weight:700; }
        main { padding: 1.25rem 0 3rem; }
        h1 { font-size:1.5rem; margin:.25rem 0 1rem; }
        .layout { display:grid; grid-template-columns: 16rem 1fr; gap:1.5rem; align-items:start; }
        @media (max-width: 899px) { .layout { grid-template-columns: 1fr; } }

        .filters { background:var(--card); border:1px solid var(--line); border-radius:1rem; padding:.25rem 1rem; }
        .filters summary { cursor:pointer; font-weight:700; padding:.75rem 0; list-style:none; display:flex; justify-content:space-between; }
        .filters summary::-webkit-details-marker { display:none; }
        .filters fieldset { border:0; padding:0; margin:0 0 1rem; }
        .filters legend { font-weight:600; font-size:.85rem; color:var(--muted); text-transform:uppercase; letter-spacing:.04em; margin-bottom:.4rem; padding:0; }
        .filters label.opt { display:flex; align-items:center; gap:.5rem; padding:.3rem 0; font-size:.95rem; cursor:pointer; }
        .filters input[type=checkbox] { width:1.1rem; height:1.1rem; accent-color: var(--brand); }
        .filters input[type=search], .filters select, .field { width:100%; padding:.55rem .7rem; border:1px solid var(--line); border-radius:.6rem; font: inherit; background:#fff; }
        .hint { font-size:.8rem; color:var(--muted); margin:.2rem 0 .5rem; }
        .btn { display:inline-block; padding:.6rem 1.1rem; border-radius:.7rem; border:0; background:var(--brand); color:var(--brand-ink); font:inherit; font-weight:600; cursor:pointer; text-decoration:none; }
        .btn.ghost { background:transparent; color:var(--brand); border:1px solid var(--brand); }
        .row { display:flex; gap:.5rem; flex-wrap:wrap; align-items:center; }

        .bar { display:flex; justify-content:space-between; align-items:center; gap:1rem; margin-bottom:1rem; flex-wrap:wrap; }
        .count { color:var(--muted); }
        .grid { display:grid; grid-template-columns: repeat(auto-fill, minmax(13rem, 1fr)); gap:1rem; }
        @media (max-width: 560px) { .grid { grid-template-columns: repeat(2, 1fr); gap:.7rem; } .card .body { padding:.55rem .65rem .7rem; } .card .name { font-size:.95rem; } }
        .card { background:var(--card); border:1px solid var(--line); border-radius:1rem; overflow:hidden; text-decoration:none; display:flex; flex-direction:column; transition: box-shadow .15s, transform .15s; position:relative; }
        .card:hover, .card:focus-visible { box-shadow:0 8px 22px rgba(80,40,50,.14); transform: translateY(-2px); outline:none; }
        .card.match { border-color: var(--brand); box-shadow: 0 0 0 2px var(--soft); }
        .thumb { aspect-ratio:1/1; background:#f3ece3; display:flex; align-items:center; justify-content:center; font-size:3rem; }
        .thumb img { width:100%; height:100%; object-fit:cover; display:block; }
        .card .body { padding:.7rem .85rem .9rem; display:flex; flex-direction:column; gap:.2rem; }
        .card .name { font-weight:700; line-height:1.2; }
        .card .brand { font-size:.85rem; color:var(--muted); }
        .chips { display:flex; flex-wrap:wrap; gap:.3rem; margin-top:.35rem; }
        .chip { font-size:.72rem; padding:.1rem .5rem; border-radius:999px; background:var(--bad-soft); color:var(--bad); white-space:nowrap; }
        .chip.trace { background:var(--warn-soft); color:var(--warn); }
        .chip.where { background:#efe8f7; color:#5b3d8f; }
        .chip.type { background:#f1ece6; color:#5a4a44; }
        .dot { display:inline-block; width:.6rem; height:.6rem; border-radius:50%; margin-right:.35rem; vertical-align:baseline; }
        .chip.more { background:#eee; color:#555; }
        .pill { position:absolute; top:.5rem; left:.5rem; background:var(--brand); color:#fff; font-size:.75rem; font-weight:700; padding:.15rem .6rem; border-radius:999px; }
        .empty { text-align:center; color:var(--muted); padding:3rem 1rem; background:var(--card); border:1px dashed var(--line); border-radius:1rem; }
        .note { background:var(--soft); border-radius:.8rem; padding:.75rem 1rem; margin-bottom:1rem; }
        .note.err { background:var(--bad-soft); color:var(--bad); }

        .detail { display:grid; grid-template-columns: minmax(0,26rem) 1fr; gap:2rem; align-items:start; }
        @media (max-width: 799px) { .detail { grid-template-columns: 1fr; } }
        .detail .photo { border-radius:1rem; overflow:hidden; background:#f3ece3; aspect-ratio:1/1; display:flex; align-items:center; justify-content:center; font-size:5rem; }
        .detail .photo img { width:100%; height:100%; object-fit:cover; }
        .detail h2 { font-size:1.15rem; margin:1.5rem 0 .4rem; }
        .dropzone { border:2px dashed var(--line); border-radius:1rem; padding:1.25rem; background:var(--card); display:flex; gap:1rem; flex-wrap:wrap; align-items:center; margin-bottom:1.25rem; }
        .dropzone img.preview { max-height:6rem; border-radius:.6rem; display:none; }
        .sr { position:absolute; width:1px; height:1px; overflow:hidden; clip:rect(0 0 0 0); }
        @media (pointer: fine) { .touch-only { display:none; } }
    </style>
</head>
<body>
<header class="top">
    <div class="wrap">
        <a class="logo" href="{{ route('vitrine.catalogue') }}">🍬 {{ config('app.name') }}</a>
        <nav class="main" aria-label="Navigation">
            <a href="{{ route('vitrine.catalogue') }}" @if (request()->routeIs('vitrine.catalogue', 'vitrine.product')) aria-current="page" @endif>{{ __('vitrine::ui.catalogue') }}</a>
            @if (Route::has('vitrine.photo'))
                <a href="{{ route('vitrine.photo') }}" @if (request()->routeIs('vitrine.photo*')) aria-current="page" @endif>📷 {{ __('vitrine::ui.photo_search') }}</a>
            @endif
        </nav>
        <div class="lang" role="group" aria-label="{{ __('vitrine::ui.language') }}">
            @foreach (config('bonbon.locales') as $code => $label)
                <a href="{{ route('vitrine.locale', $code) }}" hreflang="{{ $code }}" title="{{ $label }}" @if (app()->getLocale() === $code) aria-current="true" @endif>{{ strtoupper($code) }}</a>
            @endforeach
        </div>
    </div>
</header>
<main>
    <div class="wrap">
        @yield('content')
    </div>
</main>
</body>
</html>
