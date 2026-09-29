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
