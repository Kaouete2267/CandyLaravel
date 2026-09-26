@php
    $data = $this->data;
    $panel = $this->panel;
    $warnings = $this->warnings;
    $settings = $data['settings'];
    $blocks = $panel->blocks;
    $labelInfo = collect($data['labelBlocks'])->keyBy('id');
    $manualBlocks = $blocks->where('kind', 'manual');
    $zones = ['before' => 'Avant les étiquettes', 'labels' => 'Étiquettes', 'after' => 'Après les étiquettes'];
    $kindClass = fn ($b) => $b->isSystem() ? 'sys' : ($b->kind === 'brand' ? 'auto' : ($b->kind === 'manual' ? 'man' : 'info'));
@endphp

<div>
    @include('panneaux::partials.board-style')

    <style>
        /* L'aperçu du panneau garde au moins les deux tiers de la largeur en desktop (2fr / 1fr) ; le panneau
           latéral (blocs, bonbons, avertissements) se voit quand même garantir une largeur minimale lisible. */
        .pbx { display: grid; grid-template-columns: minmax(0, 2fr) minmax(22rem, 1fr); gap: 1.25rem; align-items: start; }
        @media (max-width: 1300px) { .pbx { grid-template-columns: 1fr; } }
        [x-cloak] { display: none !important; }
        /* Toggle moderne (remplace les cases à cocher brutes) : piste + curseur, coché = teinte primaire. */
        .pbx-switch { position: relative; display: inline-flex; width: 2rem; height: 1.15rem; flex: none; cursor: pointer; }
        .pbx-switch input { position: absolute; inset: 0; opacity: 0; margin: 0; cursor: pointer; z-index: 1; }
        .pbx-switch .track { position: absolute; inset: 0; background: var(--gray-300); border-radius: 999px; transition: background .15s; }
        .dark .pbx-switch .track { background: var(--gray-600); }
        .pbx-switch .thumb { position: absolute; top: 2px; left: 2px; width: .95rem; height: .95rem; background: #fff; border-radius: 999px; transition: transform .15s; box-shadow: 0 1px 2px rgba(0,0,0,.35); }
        .pbx-switch input:checked ~ .track { background: var(--primary-600); }
        .pbx-switch input:checked ~ .thumb { transform: translateX(.85rem); }
        .pbx-switch input:disabled ~ .track { opacity: .4; }
        .pbx-switch input:disabled { cursor: default; }
        .pbx-card-actions { display: flex; align-items: center; gap: .6rem; }
        /* En-tête de section repliable : tout le libellé (hors actions) est cliquable, chevron qui pivote. */
        .pbx-collapse { border: none; background: none; padding: 0; margin: 0; font: inherit; font-weight: 700; color: inherit; cursor: pointer; display: flex; align-items: center; gap: .4rem; }
        .pbx-chevron { display: inline-block; transition: transform .15s; font-size: .75rem; color: var(--gray-500); }
        .pbx-chevron.open { transform: rotate(90deg); }
        .pbx-preview { background: var(--gray-200); border-radius: .8rem; padding: 1rem; overflow: auto; max-height: calc(100vh - 12rem); }
        .dark .pbx-preview { background: var(--gray-800); }
        .pbx-bar { display: flex; flex-wrap: wrap; gap: .75rem; align-items: center; margin-bottom: .75rem; font-size: .85rem; position: sticky; left: 0; }
        .pbx-bar input[type=range] { width: 9rem; }
        .pbx-side { display: flex; flex-direction: column; gap: 1rem; }
        .pbx-card { background: #fff; border: 1px solid var(--gray-200); border-radius: .8rem; padding: .8rem .9rem; }
        .dark .pbx-card { background: var(--gray-900); border-color: var(--gray-700); }
        .pbx-card h3 { font-weight: 700; font-size: .95rem; margin: 0 0 .6rem; display: flex; justify-content: space-between; align-items: center; gap: .5rem; }
        .pbx-zone { font-size: .72rem; text-transform: uppercase; letter-spacing: .04em; color: var(--gray-500); margin: .7rem 0 .3rem; }
        .pbx-row { display: flex; gap: .35rem; align-items: center; padding: .3rem .2rem; border-top: 1px solid var(--gray-100); font-size: .85rem; }
        .dark .pbx-row { border-color: var(--gray-800); }
        .pbx-name { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .pbx-name small { color: var(--gray-500); }
        .pbx-off { opacity: .5; }
        .pbx-tag { font-size: .68rem; border-radius: .35rem; padding: 0 .4rem; font-weight: 600; flex: none; }
        .pbx-tag.sys { background: #dbeafe; color: #1e40af; } .pbx-tag.auto { background: #dcfce7; color: #166534; }
        .pbx-tag.man { background: #fef3c7; color: #92400e; } .pbx-tag.info { background: #f3e8ff; color: #6b21a8; }
        .pbx-btn { border: 1px solid var(--gray-300); background: transparent; border-radius: .4rem; min-width: 1.7rem; height: 1.7rem; cursor: pointer; color: inherit; font-size: .85rem; flex: none; padding: 0 .3rem; }
        .pbx-btn:disabled { opacity: .35; cursor: default; }
        .pbx-btn.danger { color: #dc2626; }
        .pbx-input { border: 1px solid var(--gray-300); border-radius: .4rem; padding: .15rem .35rem; font-size: .8rem; background: transparent; color: inherit; }
        .pbx-muted { color: var(--gray-500); font-size: .8rem; }
        .pbx-list { max-height: 24rem; overflow: auto; }
        .pbx-warn { color: #b45309; }

        /* Éditeur riche (Filament RichEditor / TipTap-ProseMirror) : en thème sombre, le curseur texte
           disparaît dès qu'il franchit une limite de mise en forme (gras…) ou se place à côté d'un nœud
           bloc (ex: <hr>). ProseMirror masque alors le vrai curseur du navigateur — et la sélection — sur
           TOUS les descendants de l'éditeur (règle du cœur ProseMirror : « .ProseMirror-hideselection * »,
           donc les descendants, pas l'élément lui-même) le temps de dessiner son propre curseur. Et ce
           curseur de substitution ("gap cursor", utilisé pour les positions à côté d'un <hr>/image/tableau)
           est codé en dur en noir — invisible sur fond sombre. On force donc un curseur, une sélection et un
           gap cursor lisibles dans les deux thèmes, en ciblant explicitement les mêmes descendants. */
        .fi-fo-rich-editor .ProseMirror-hideselection,
        .fi-fo-rich-editor .ProseMirror-hideselection * { caret-color: auto !important; }
        .fi-fo-rich-editor .ProseMirror-hideselection *::selection { background: rgba(59, 130, 246, .35) !important; }
        .fi-fo-rich-editor .ProseMirror-hideselection *::-moz-selection { background: rgba(59, 130, 246, .35) !important; }
        .fi-fo-rich-editor .ProseMirror-gapcursor:after { border-top-color: currentColor; }
    </style>

    <div class="pbx">
        {{-- ------------------------------------------------ Aperçu --}}
        <div class="pbx-preview" x-data="{ zoom: 100 }">
            <div class="pbx-bar">
                <span>Zoom</span>
                <input type="range" min="30" max="120" step="5" x-model.number="zoom" aria-label="Zoom">
                <span x-text="zoom + ' %'"></span>
                <span class="pbx-muted">{{ $data['total'] }} bonbon(s) imprimé(s)</span>
                <a class="fi-btn fi-color-primary" style="margin-left:auto;padding:.3rem .8rem;border-radius:.5rem;background:var(--primary-600);color:#fff;text-decoration:none;font-size:.85rem"
                   href="{{ route('filament.lunar.panneaux.print', ['panel' => $panel->id, 'auto' => 1]) }}" target="_blank" rel="noopener">🖨️ Imprimer</a>
            </div>
            <div data-pnl-zoom :style="'zoom:' + (zoom / 100)">
                @include('panneaux::partials.board', ['data' => $data, 'editable' => true])
            </div>
        </div>

        {{-- ------------------------------------------------ Panneau latéral --}}
        <aside class="pbx-side" x-data="{ blocksOpen: false, membersOpen: false }">
            {{-- Avertissements --}}
            @if ($warnings['count'] > 0 || count($data['orphans']) > 0)
                <div class="pbx-card">
                    <h3>Avertissements <span class="pbx-tag info">{{ $warnings['count'] + count($data['orphans']) }}</span></h3>
                    @if (count($data['orphans']))
                        <p class="pbx-warn" style="margin:.2rem 0;font-size:.82rem">
                            {{ count($data['orphans']) }} bonbon(s) du panneau ne sont affichés par aucun bloc
                            ({{ collect($data['orphans'])->pluck('name')->take(3)->implode(', ') }}…).
                        </p>
                        <button type="button" class="pbx-btn" style="width:auto;padding:0 .6rem;margin-bottom:.4rem" wire:click="createMissingBlocks">Créer les blocs marque manquants</button>
                    @endif
                    @foreach ([['missingMark', 'sans marque'], ['missingType', 'sans type']] as [$key, $label])
                        @if ($warnings[$key])
                            <details><summary class="pbx-warn" style="cursor:pointer;font-size:.82rem">{{ count($warnings[$key]) }} bonbon(s) {{ $label }}</summary>
                                <div class="pbx-muted">{{ implode(', ', $warnings[$key]) }}</div></details>
                        @endif
                    @endforeach
                    @if ($warnings['expiring'])
                        <details><summary class="pbx-warn" style="cursor:pointer;font-size:.82rem">{{ count($warnings['expiring']) }} DLUO courte(s) ou dépassée(s)</summary>
                            @foreach ($warnings['expiring'] as $w)
                                <div class="pbx-muted">{{ $w['name'] }} — {{ $w['dluo'] }} @if ($w['months'] < 0)<b>(dépassée)</b>@endif</div>
                            @endforeach
                        </details>
                    @endif
                </div>
            @endif

            {{-- Blocs --}}
            <div class="pbx-card">
                <h3>
                    <button type="button" class="pbx-collapse" x-on:click="blocksOpen = !blocksOpen" :aria-expanded="blocksOpen.toString()">
                        <span class="pbx-chevron" :class="{ open: blocksOpen }">▸</span>
                        <span>Blocs</span>
                    </button>
                    <span class="pbx-card-actions">
                        <button type="button" class="pbx-btn" style="width:auto;padding:0 .6rem" wire:click="mountAction('createBlock')">+ Nouveau</button>
                    </span>
                </h3>

                <div x-show="blocksOpen" x-cloak>
                    @foreach ($zones as $zone => $zoneLabel)
                        @php $inZone = $blocks->where('zone', $zone)->values(); @endphp
                        <div class="pbx-zone">{{ $zoneLabel }}</div>
                        @forelse ($inZone as $i => $block)
                            <div class="pbx-row {{ $block->active ? '' : 'pbx-off' }}" wire:key="block-{{ $block->id }}">
                                <span class="pbx-tag {{ $kindClass($block) }}" title="{{ Modules\Panneaux\Models\Block::kindLabel($block->kind) }}">
                                    {{ $block->isSystem() ? 'Système' : ($block->kind === 'brand' ? 'Auto' : ($block->kind === 'manual' ? 'Manuel' : 'Info')) }}
                                </span>
                                <span class="pbx-name">
                                    {{ $block->displayTitle() ?: $block->code }}
                                    @if (! $block->isInfo())<small>· {{ $labelInfo[$block->id]['count'] ?? 0 }}</small>@else<small>· {{ $block->width }}/12{{ $block->repeat ? ' · répété' : '' }}</small>@endif
                                    @unless ($block->isDeletable())<small title="Bloc système : non supprimable">🔒</small>@endunless
                                </span>
                                <label class="pbx-switch" title="Actif">
                                    <input type="checkbox" @checked($block->active) wire:click="toggleBlock({{ $block->id }})">
                                    <span class="track"></span><span class="thumb"></span>
                                </label>
                                <button type="button" class="pbx-btn" title="Monter" wire:click="moveBlock({{ $block->id }}, -1)" @disabled($loop->first)>↑</button>
                                <button type="button" class="pbx-btn" title="Descendre" wire:click="moveBlock({{ $block->id }}, 1)" @disabled($loop->last)>↓</button>
                                <button type="button" class="pbx-btn" title="Modifier (suppression incluse si permise)" wire:click="mountAction('editBlock', { block: {{ $block->id }} })">✎</button>
                            </div>
                        @empty
                            <div class="pbx-muted">—</div>
                        @endforelse
                    @endforeach

                    <p class="pbx-muted" style="margin:.7rem 0 0">
                        <b>Auto</b> : alimenté par une marque, on ne choisit pas ses bonbons ni leur ordre (tri par nom).
                        <b>Manuel</b> : bonbons et ordre choisis à la main ci-dessous.
                    </p>
                </div>
            </div>

            {{-- Bonbons du panneau --}}
            <div class="pbx-card">
                <h3>
                    <button type="button" class="pbx-collapse" x-on:click="membersOpen = !membersOpen" :aria-expanded="membersOpen.toString()">
                        <span class="pbx-chevron" :class="{ open: membersOpen }">▸</span>
                        <span>Bonbons du panneau ({{ count($this->members) }})</span>
                    </button>
                    <span class="pbx-card-actions">
                        <button type="button" class="pbx-btn" style="width:auto;padding:0 .6rem" wire:click="mountAction('addProducts')">+ Ajouter</button>
                    </span>
                </h3>
                <div x-show="membersOpen" x-cloak>
                    <input type="search" class="pbx-input" style="width:100%;margin-bottom:.4rem" placeholder="Rechercher…" wire:model.live.debounce.300ms="search">
                    <div class="pbx-list">
                        @forelse ($this->members as $m)
                            <div class="pbx-row {{ $m['active'] && $m['published'] ? '' : 'pbx-off' }}" wire:key="member-{{ $m['id'] }}">
                                <input type="checkbox" title="Actif (en stock) : s'imprime sur le panneau" @checked($m['active']) wire:click="toggleProduct({{ $m['id'] }})">
                                <span class="pbx-name">{{ $m['name'] }}@if ($m['brand']) <small>· {{ $m['brand'] }}</small>@endif @unless ($m['published'])<small>(non publié)</small>@endunless</span>
                                @if ($settings['showDluo'])
                                    <input type="text" class="pbx-input" style="width:5.2rem" placeholder="MM/AAAA" value="{{ $m['dluo'] }}" title="DLUO" wire:change="updateDluo({{ $m['id'] }}, $event.target.value)">
                                @endif
                                @if ($manualBlocks->isNotEmpty())
                                    <select class="pbx-input" style="width:5.5rem" title="Bloc manuel" wire:change="assignProduct({{ $m['id'] }}, $event.target.value)">
                                        <option value="">—</option>
                                        @foreach ($manualBlocks as $mb)
                                            <option value="{{ $mb->id }}" @selected($m['block'] === $mb->id)>{{ $mb->displayTitle() ?: $mb->code }}</option>
                                        @endforeach
                                    </select>
                                @endif
                                <button type="button" class="pbx-btn danger" title="Retirer du panneau" wire:click="removeProduct({{ $m['id'] }})">✕</button>
                            </div>
                        @empty
                            <div class="pbx-muted">Aucun bonbon. Utilisez « + Ajouter ».</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </aside>
    </div>

    <x-filament-actions::modals />
</div>
