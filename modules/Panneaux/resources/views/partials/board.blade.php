{{-- Variables : $data (BoardData::for), $editable (barres d'outils au survol : aperçu d'administration seulement) --}}
@php
    $s = $data['settings'];
    $landscape = $s['page']['orientation'] === 'paysage';
    $lines = array_values(array_filter(preg_split('/\R/u', (string) $s['titre']['content']), fn ($l) => trim($l) !== ''));
    $titles = $lines === [] ? [] : ($s['titre']['ononepage'] ? [implode('<br>', array_map('e', $lines))] : array_map('e', $lines));
    $bc = $s['titre']['bordercolor'];
    $titleStyle = "font-family:'".e($s['titre']['font'])."';font-size:".(int) $s['titre']['size']."px;color:".e($s['titre']['color']).';'
        ."text-shadow:-1px -1px 0 {$bc},1px -1px 0 {$bc},-1px 1px 0 {$bc},1px 1px 0 {$bc};";
    $hasInfo = collect([...$data['before'], ...$data['after']])->contains(fn ($b) => $b['html'] !== '');
@endphp

<div class="pnl {{ $landscape ? 'paysage' : 'portrait' }} pnl-allergene-{{ $s['bonbon']['allergene'] }}" data-pnl data-cols="{{ (int) $s['page']['columnsPerRow'] }}" data-editable="{{ $editable ? 1 : 0 }}" data-title-mode="{{ $s['titre']['ononepage'] ? 'one' : 'each' }}">
    <div class="pnl-pool" data-pool>
        @foreach ($titles as $title)
            <div class="pnl-title" data-title style="{{ $titleStyle }}">{!! $title !!}</div>
        @endforeach

        @foreach (['before', 'after'] as $zone)
            @foreach ($data[$zone] as $block)
                @continue($block['html'] === '')
                <div data-info data-zone="{{ $zone }}" data-id="{{ $block['id'] }}" data-width="{{ $block['width'] }}" data-repeat="{{ $block['repeat'] ? 1 : 0 }}">{!! $block['html'] !!}</div>
            @endforeach
        @endforeach

        @foreach ($data['labelBlocks'] as $block)
            @continue(! $block['active'] || $block['count'] === 0)
            @if ($block['showHeader'] && $block['title'] !== '')
                <div class="pnl-block-head" data-item data-keep-next="1"><span>{{ $block['title'] }}</span>@if ($block['logo'])<img src="{{ $block['logo'] }}" alt="">@endif</div>
            @endif
            @foreach ($block['labels'] as $l)
                <div data-item @if ($editable) data-edit-url="{{ Lunar\Admin\Filament\Resources\ProductResource::getUrl('edit', ['record' => $l['id']]) }}" @endif>@include('panneaux::partials.label', ['l' => $l, 's' => $s])</div>
            @endforeach
        @endforeach

        @if ($data['recap'])
            <div data-recap>
                <div class="pnl-recap">
                    <h1>{{ __('panneaux::board.recap_title') }}</h1>
                    @foreach ($data['recap'] as $row)
                        <b>{{ $row['dluo'] }}</b>{{ implode(', ', $row['names']) }}
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    @if ($data['total'] === 0 && ! $hasInfo)
        <div class="pnl-none">{{ __('panneaux::board.empty') }}</div>
    @endif

    <div class="pnl-pages" data-pages wire:ignore></div>
</div>

@verbatim
<script>
    /* Mise en page : répartit les étiquettes en colonnes puis en pages A4 (une colonne se remplit jusqu'à débordement,
       un bandeau de bloc reste avec l'étiquette qui le suit), avec titre, blocs d'information avant/après sur une
       grille de 12 (répétables sur chaque page) et page récapitulative des DLUO. Les éléments d'origine restent
       dans .pnl-pool : on ne fait que les cloner. */
    /* La mise en page se calcule à taille réelle : le zoom de l'aperçu fausse les hauteurs mesurées
       (débordements non détectés). On le neutralise le temps du calcul, puis on le rétablit. */
    window.pnlRender = function (root) {
        const host = root.closest('[data-pnl-zoom]');
        const saved = host ? host.style.zoom : '';
        if (host) host.style.zoom = '1';
        try {
            // Un calcul fait trop tôt (polices ou images pas encore prêtes) peut laisser des colonnes qui débordent :
            // on vérifie et on recommence.
            for (let attempt = 0; attempt < 3; attempt++) {
                window.pnlLayout(root);
                const overflowing = Array.from(root.querySelectorAll('.pnl-pages .pnl-col')).some((c) => c.scrollHeight > c.clientHeight + 1);
                if (!overflowing) break;
            }
        } finally { if (host) host.style.zoom = saved; }
    };

    window.pnlLayout = function (root) {
        const pool = root.querySelector('[data-pool]');
        const out = root.querySelector('[data-pages]');
        if (!pool || !out) return;

        const cols = parseInt(root.dataset.cols, 10) || 3;
        const editable = root.dataset.editable === '1';
        const titles = Array.from(pool.querySelectorAll('[data-title]'));
        const infos = Array.from(pool.querySelectorAll('[data-info]'));
        const flow = Array.from(pool.querySelectorAll('[data-item]'));
        const recap = pool.querySelector('[data-recap]');
        const before = infos.filter((i) => i.dataset.zone === 'before');
        const after = infos.filter((i) => i.dataset.zone === 'after');
        const repeats = (i) => i.dataset.repeat === '1';
        out.innerHTML = '';

        const el = (cls) => { const e = document.createElement('div'); e.className = cls; return e; };
        const btn = (act, id, text, title, disabled) => {
            const b = document.createElement('button');
            b.type = 'button'; b.dataset.act = act; b.dataset.id = id; b.textContent = text; b.title = title;
            b.disabled = !!disabled;
            return b;
        };
        const overlay = (...nodes) => { const o = el('pnl-overlay'); const t = el('pnl-tools'); nodes.forEach((n) => t.appendChild(n)); o.appendChild(t); return o; };

        const blocksRow = (list) => {
            if (list.length === 0) return null;
            const row = el('pnl-blocks');
            list.forEach((src) => {
                const cell = el('pnl-block');
                cell.style.gridColumn = 'span ' + src.dataset.width;
                cell.innerHTML = src.innerHTML;
                if (editable) {
                    const sameZone = infos.filter((i) => i.dataset.zone === src.dataset.zone);
                    const at = sameZone.indexOf(src);
                    const width = document.createElement('span');
                    width.className = 'w'; width.textContent = src.dataset.width;
                    cell.appendChild(overlay(
                        btn('zoom', src.dataset.id, '🔍 Zoom', 'Agrandir'),
                        btn('shrink', src.dataset.id, '−', 'Réduire la largeur', parseInt(src.dataset.width, 10) <= 1), width,
                        btn('grow', src.dataset.id, '+', 'Augmenter la largeur', parseInt(src.dataset.width, 10) >= 12),
                        btn('up', src.dataset.id, '▲', 'Monter', at <= 0), btn('down', src.dataset.id, '▼', 'Descendre', at >= sameZone.length - 1),
                        btn('zone', src.dataset.id, '⇄ ' + (src.dataset.zone === 'before' ? 'Avant' : 'Après'), 'Changer de côté (avant / après les étiquettes)'),
                        btn('edit', src.dataset.id, '✎ Éditer', 'Éditer le bloc'),
                    ));
                }
                row.appendChild(cell);
            });
            return row;
        };

        const pages = [];
        const newPage = () => {
            const index = pages.length;
            const page = el('pnl-page');
            const margin = el('pnl-margin');
            page.appendChild(margin);

            const title = root.dataset.titleMode === 'one' ? (index === 0 ? titles[0] : null) : titles[index];
            if (title) margin.appendChild(title.cloneNode(true));

            const head = blocksRow(before.filter((b) => index === 0 || repeats(b)));
            if (head) margin.appendChild(head);

            const row = el('pnl-cols');
            const columns = [];
            for (let i = 0; i < cols; i++) { const c = el('pnl-col'); row.appendChild(c); columns.push(c); }
            margin.appendChild(row);

            const foot = blocksRow(after.filter(repeats));
            if (foot) margin.appendChild(foot);

            out.appendChild(page);
            pages.push(page);
            return columns;
        };

        let columns = newPage();
        let ci = 0;
        const advance = () => { ci++; if (ci >= columns.length) { columns = newPage(); ci = 0; } };
        const overflows = (col) => col.scrollHeight > col.clientHeight + 1;

        const build = (src) => {
            if (src.dataset.keepNext === '1') return src.cloneNode(true);
            const wrap = el('pnl-lab');
            wrap.innerHTML = src.innerHTML;
            if (editable) {
                const zoom = btn('zoom', '', '🔍 Zoom', 'Agrandir');
                const parts = [zoom];
                if (src.dataset.editUrl) {
                    const a = document.createElement('a');
                    a.href = src.dataset.editUrl; a.target = '_blank'; a.rel = 'noopener'; a.textContent = '✎ Éditer';
                    parts.push(a);
                }
                wrap.appendChild(overlay(...parts));
            }
            return wrap;
        };

        flow.forEach((src) => {
            const node = build(src);
            columns[ci].appendChild(node);
            if (!overflows(columns[ci])) return;

            const col = columns[ci];
            col.removeChild(node);
            const carry = [node];
            const prev = col.lastElementChild;
            if (prev && prev.dataset.keepNext === '1') { col.removeChild(prev); carry.unshift(prev); }
            if (col.children.length === 0 && carry.length === 1) { col.appendChild(node); return; } // plus haut qu'une colonne : accepté
            advance();
            carry.forEach((n) => columns[ci].appendChild(n));
        });

        // Titres « une ligne = une page » : au moins autant de pages que de lignes.
        while (root.dataset.titleMode === 'each' && pages.length < titles.length) newPage();

        const extraPage = (nodes) => {
            const page = el('pnl-page');
            const margin = el('pnl-margin');
            nodes.forEach((n) => n && margin.appendChild(n));
            page.appendChild(margin);
            out.appendChild(page);
        };
        // Un bloc « après » non répété n'a pas de place réservée : il obtient sa propre page.
        const once = after.filter((b) => !repeats(b));
        if (once.length > 0) extraPage([blocksRow(once)]);
        if (recap) extraPage([recap.firstElementChild.cloneNode(true)]);

        root.dataset.pages = String(out.children.length);
    };

    window.pnlRenderAll = function () { document.querySelectorAll('[data-pnl]').forEach(window.pnlRender); };

    /* Actions des barres d'outils : elles appellent le composant Livewire qui contient l'aperçu. */
    if (!window.__pnlClicks) {
        window.__pnlClicks = true;
        document.addEventListener('click', (event) => {
            const btn = event.target.closest('.pnl-pages [data-act]');
            if (!btn) return;
            const root = btn.closest('[data-pnl]');
            const host = root && root.closest('[wire\\:id]');
            const lw = host && window.Livewire ? window.Livewire.find(host.getAttribute('wire:id')) : null;
            const id = btn.dataset.id;

            if (btn.dataset.act === 'zoom') {
                const host = btn.closest('.pnl-lab, .pnl-block');
                if (!host) return;
                const isBlock = host.classList.contains('pnl-block');
                const layer = document.createElement('div');
                layer.className = 'pnl-zoom';
                const inner = document.createElement('div');
                isBlock ? inner.classList.add('wide') : (inner.style.width = '300px');
                inner.innerHTML = Array.from(host.children).filter((c) => !c.classList.contains('pnl-overlay')).map((c) => c.outerHTML).join('');
                layer.appendChild(inner);
                layer.addEventListener('click', () => layer.remove());
                document.body.appendChild(layer);
                return;
            }
            if (!lw) return;
            ({
                grow: () => lw.call('growBlock', Number(id)),
                shrink: () => lw.call('shrinkBlock', Number(id)),
                up: () => lw.call('moveBlock', Number(id), -1),
                down: () => lw.call('moveBlock', Number(id), 1),
                zone: () => lw.call('toggleZone', Number(id)),
                edit: () => lw.call('mountAction', 'editBlock', { block: Number(id) }),
            })[btn.dataset.act]?.();
        });
    }

    (function () {
        const hook = () => {
            if (window.__pnlHooked || !window.Livewire) return;
            window.__pnlHooked = true;
            window.Livewire.hook('commit', ({ succeed }) => succeed(() => setTimeout(window.pnlRenderAll, 0)));
        };
        window.Livewire ? hook() : document.addEventListener('livewire:init', hook);
        requestAnimationFrame(window.pnlRenderAll);
        // Recalculs une fois la page stabilisée (onglets, polices, images de logos).
        [200, 800, 2000].forEach((ms) => setTimeout(window.pnlRenderAll, ms));
        window.addEventListener('load', window.pnlRenderAll);
        if (document.fonts && document.fonts.ready) document.fonts.ready.then(window.pnlRenderAll);
    })();
</script>
@endverbatim
