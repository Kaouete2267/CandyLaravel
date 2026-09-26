@php
    use Modules\Stock\Enums\StockReason;

    $levelLabels = ['ok' => 'En stock', 'low' => 'Stock bas', 'out' => 'Rupture'];
    $selected = $this->selected;
    $preview = $this->preview;
    $plural = fn (int $n, string $word) => $n.' '.$word.($n > 1 ? 's' : '');
@endphp

<x-filament-panels::page>
    <style>
        .sg-toolbar { display: flex; flex-wrap: wrap; gap: .75rem; align-items: center; }
        .sg-toolbar .sg-search { flex: 1 1 16rem; min-width: 12rem; }
        .sg-pills { display: flex; gap: .5rem; flex-wrap: wrap; }
        .sg-pill { border: 1px solid var(--gray-300); border-radius: 999px; padding: .35rem .9rem; font-size: .875rem; background: transparent; cursor: pointer; color: inherit; }
        .sg-pill[aria-pressed="true"] { background: var(--primary-600); border-color: var(--primary-600); color: #fff; }
        .dark .sg-pill { border-color: var(--gray-600); }
        .sg-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(11rem, 1fr)); gap: 1rem; }
        .sg-card { display: flex; flex-direction: column; text-align: left; border: 1px solid var(--gray-200); border-radius: .9rem; overflow: hidden; background: #fff; cursor: pointer; padding: 0; transition: box-shadow .15s, transform .15s; color: inherit; }
        .sg-card:hover, .sg-card:focus-visible { box-shadow: 0 6px 18px rgba(0,0,0,.12); transform: translateY(-2px); outline: none; }
        .dark .sg-card { background: var(--gray-900); border-color: var(--gray-700); }
        .sg-thumb { position: relative; aspect-ratio: 1 / 1; background: var(--gray-100); display: flex; align-items: center; justify-content: center; font-size: 3rem; }
        .dark .sg-thumb { background: var(--gray-800); }
        .sg-thumb img { width: 100%; height: 100%; object-fit: cover; }
        .sg-badge { position: absolute; top: .5rem; left: .5rem; font-size: .7rem; font-weight: 600; padding: .15rem .55rem; border-radius: 999px; color: #fff; }
        .sg-ok { background: #16a34a; } .sg-low { background: #d97706; } .sg-out { background: #dc2626; }
        .sg-where { position: absolute; bottom: .5rem; right: .5rem; font-size: .7rem; padding: .1rem .5rem; border-radius: .4rem; background: rgba(0,0,0,.65); color: #fff; }
        .sg-body { padding: .65rem .8rem .8rem; display: flex; flex-direction: column; gap: .1rem; }
        .sg-name { font-weight: 600; line-height: 1.2; }
        .sg-brand { font-size: .8rem; color: var(--gray-500); }
        .sg-stock { margin-top: .35rem; font-size: 1.35rem; font-weight: 700; line-height: 1; }
        .sg-stock small { font-size: .75rem; font-weight: 400; color: var(--gray-500); }
        .sg-sub { font-size: .75rem; color: var(--gray-500); }

        .sg-overlay { position: fixed; inset: 0; z-index: 60; background: rgba(15,23,42,.55); display: flex; align-items: center; justify-content: center; padding: 1rem; overflow-y: auto; }
        .sg-modal { width: 100%; max-width: 30rem; background: #fff; border-radius: 1.1rem; padding: 1.25rem; display: flex; flex-direction: column; gap: 1rem; box-shadow: 0 25px 60px rgba(0,0,0,.35); margin: auto; }
        .dark .sg-modal { background: var(--gray-900); }
        .sg-head { display: flex; gap: .9rem; align-items: center; }
        .sg-head img, .sg-head .sg-noimg { width: 4.5rem; height: 4.5rem; border-radius: .7rem; object-fit: cover; background: var(--gray-100); display: flex; align-items: center; justify-content: center; font-size: 2rem; flex: none; }
        .sg-head h2 { font-size: 1.15rem; font-weight: 700; margin: 0; line-height: 1.2; }
        .sg-close { margin-left: auto; align-self: flex-start; border: 0; background: transparent; font-size: 1.6rem; line-height: 1; cursor: pointer; color: var(--gray-500); padding: .25rem .5rem; }
        .sg-now { display: flex; justify-content: space-between; align-items: baseline; gap: 1rem; padding: .75rem 1rem; border-radius: .8rem; background: var(--gray-50); }
        .dark .sg-now { background: var(--gray-800); }
        .sg-now strong { font-size: 1.8rem; }
        .sg-seg { display: grid; gap: .4rem; }
        .sg-seg.c3 { grid-template-columns: repeat(3, 1fr); } .sg-seg.c2 { grid-template-columns: repeat(2, 1fr); }
        .sg-seg button { padding: .7rem .5rem; border-radius: .7rem; border: 1px solid var(--gray-300); background: transparent; font-weight: 600; cursor: pointer; color: inherit; min-height: 3rem; }
        .dark .sg-seg button { border-color: var(--gray-600); }
        .sg-seg button[aria-pressed="true"] { background: var(--primary-600); border-color: var(--primary-600); color: #fff; }
        .sg-seg button:disabled { opacity: .4; cursor: not-allowed; }
        .sg-label { font-size: .8rem; font-weight: 600; color: var(--gray-500); margin-bottom: .35rem; display: block; }
        .sg-stepper { display: grid; grid-template-columns: 4rem 1fr 4rem; gap: .5rem; align-items: stretch; }
        .sg-stepper button { font-size: 1.8rem; border-radius: .7rem; border: 1px solid var(--gray-300); background: transparent; cursor: pointer; color: inherit; min-height: 3.5rem; }
        .dark .sg-stepper button { border-color: var(--gray-600); }
        .sg-stepper input { text-align: center; font-size: 1.6rem; font-weight: 700; width: 100%; border-radius: .7rem; border: 1px solid var(--gray-300); background: transparent; color: inherit; min-width: 0; }
        .dark .sg-stepper input { border-color: var(--gray-600); }
        .sg-chips { display: flex; gap: .4rem; margin-top: .5rem; flex-wrap: wrap; }
        .sg-chips button { border: 1px solid var(--gray-300); border-radius: 999px; padding: .25rem .8rem; background: transparent; cursor: pointer; font-size: .85rem; color: inherit; }
        .dark .sg-chips button { border-color: var(--gray-600); }
        .sg-preview { padding: .75rem 1rem; border-radius: .8rem; background: color-mix(in srgb, var(--primary-500) 12%, transparent); font-size: .95rem; }
        .sg-preview.bad { background: color-mix(in srgb, #dc2626 14%, transparent); color: #b91c1c; }
        .sg-error { color: #dc2626; font-size: .85rem; }
        .sg-actions { display: flex; gap: .6rem; justify-content: flex-end; }
        .sg-history { font-size: .8rem; color: var(--gray-500); border-top: 1px solid var(--gray-200); padding-top: .6rem; }
        .dark .sg-history { border-color: var(--gray-700); }
        .sg-history li { display: flex; justify-content: space-between; gap: .5rem; padding: .1rem 0; }
        .sg-empty { text-align: center; color: var(--gray-500); padding: 3rem 1rem; }
    </style>

    {{-- Filtres --}}
    <div class="sg-toolbar">
        <div class="sg-search">
            <x-filament::input.wrapper prefix-icon="heroicon-m-magnifying-glass">
                <x-filament::input type="search" wire:model.live.debounce.300ms="search" placeholder="Rechercher un bonbon, une marque, un SKU…" />
            </x-filament::input.wrapper>
        </div>

        @if ($this->panels)
            <x-filament::input.wrapper>
                <x-filament::input.select wire:model.live="panelId">
                    <option value="">Tous les panneaux</option>
                    @foreach ($this->panels as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </x-filament::input.select>
            </x-filament::input.wrapper>
        @endif

        <div class="sg-pills">
            <button type="button" class="sg-pill" wire:click="$set('status', 'all')" aria-pressed="{{ $status === 'all' ? 'true' : 'false' }}">Tous ({{ $this->counts['all'] }})</button>
            <button type="button" class="sg-pill" wire:click="$set('status', 'low')" aria-pressed="{{ $status === 'low' ? 'true' : 'false' }}">À réassortir ({{ $this->counts['low'] }})</button>
            <button type="button" class="sg-pill" wire:click="$set('status', 'out')" aria-pressed="{{ $status === 'out' ? 'true' : 'false' }}">Ruptures ({{ $this->counts['out'] }})</button>
        </div>
    </div>

    {{-- Grille --}}
    @if ($this->cards->isEmpty())
        <div class="sg-empty">Aucun bonbon ne correspond à ces filtres.</div>
    @else
        <div class="sg-grid">
            @foreach ($this->cards as $card)
                <button type="button" class="sg-card" wire:key="card-{{ $card['id'] }}" wire:click="openOverlay({{ $card['id'] }})">
                    <span class="sg-thumb">
                        @if ($card['image'])
                            <img src="{{ $card['image'] }}" alt="{{ $card['name'] }}" loading="lazy">
                        @else
                            <span aria-hidden="true">🍬</span>
                        @endif
                        <span class="sg-badge sg-{{ $card['level'] }}">{{ $levelLabels[$card['level']] }}</span>
                        @if ($card['panelIds'])
                            <span class="sg-where">{{ $card['where'] }}</span>
                        @endif
                    </span>
                    <span class="sg-body">
                        <span class="sg-name">{{ $card['name'] }}</span>
                        <span class="sg-brand">{{ $card['brand'] ?: '—' }}</span>
                        <span class="sg-stock">{{ $card['stock'] }} <small>sac{{ $card['stock'] > 1 ? 's' : '' }}</small></span>
                        <span class="sg-sub">
                            @if ($card['perCarton'] > 1)
                                {{ $card['cartons'] }} carton{{ $card['cartons'] > 1 ? 's' : '' }} + {{ $card['loose'] }} sac{{ $card['loose'] > 1 ? 's' : '' }}
                            @else
                                {{ $card['weight'] !== '' && $card['weight'] !== '0' ? $card['weight'].' kg par sac' : ' ' }}
                            @endif
                        </span>
                    </span>
                </button>
            @endforeach
        </div>
    @endif

    {{-- Overlay --}}
    @if ($selected)
        <div class="sg-overlay" wire:click.self="closeOverlay" wire:keydown.escape.window="closeOverlay">
            <div class="sg-modal" role="dialog" aria-modal="true" aria-label="Mouvement de stock : {{ $selected['name'] }}">
                <div class="sg-head">
                    @if ($selected['image'])
                        <img src="{{ $selected['image'] }}" alt="">
                    @else
                        <span class="sg-noimg" aria-hidden="true">🍬</span>
                    @endif
                    <div>
                        <h2>{{ $selected['name'] }}</h2>
                        <div class="sg-brand">{{ $selected['brand'] ?: '—' }}@if ($selected['panelIds']) · {{ $selected['where'] }}@endif</div>
                    </div>
                    <button type="button" class="sg-close" wire:click="closeOverlay" aria-label="Fermer">×</button>
                </div>

                <div class="sg-now">
                    <div>
                        <span class="sg-label" style="margin:0">Stock actuel</span>
                        <strong>{{ $selected['stock'] }}</strong> sac{{ $selected['stock'] > 1 ? 's' : '' }}
                    </div>
                    <div style="text-align:right; font-size:.85rem" class="sg-brand">
                        @if ($selected['perCarton'] > 1)
                            1 carton = {{ $selected['perCarton'] }} sacs @if ($selected['weight'] !== '' && $selected['weight'] !== '0')de {{ $selected['weight'] }} kg @endif
                            <br>soit {{ $plural($selected['cartons'], 'carton') }} + {{ $plural($selected['loose'], 'sac') }}
                        @elseif ($selected['weight'] !== '' && $selected['weight'] !== '0')
                            Sac de {{ $selected['weight'] }} kg
                        @endif
                        <br>Seuil d'alerte : {{ $plural($selected['min'], 'sac') }}
                    </div>
                </div>

                <div>
                    <span class="sg-label">Opération</span>
                    <div class="sg-seg c3">
                        <button type="button" wire:click="setDirection('in')" aria-pressed="{{ $direction === 'in' ? 'true' : 'false' }}">＋ Entrée</button>
                        <button type="button" wire:click="setDirection('out')" aria-pressed="{{ $direction === 'out' ? 'true' : 'false' }}">－ Sortie</button>
                        <button type="button" wire:click="setDirection('set')" aria-pressed="{{ $direction === 'set' ? 'true' : 'false' }}">Inventaire</button>
                    </div>
                </div>

                <div>
                    <span class="sg-label">
                        @if ($direction === 'set') Quantité comptée @else Quantité @endif
                    </span>

                    @if ($direction !== 'set' && $selected['perCarton'] > 1)
                        <div class="sg-seg c2" style="margin-bottom:.5rem">
                            <button type="button" wire:click="setUnit('carton')" aria-pressed="{{ $unit === 'carton' ? 'true' : 'false' }}">Carton{{ '' }} (×{{ $selected['perCarton'] }})</button>
                            <button type="button" wire:click="setUnit('bag')" aria-pressed="{{ $unit === 'bag' ? 'true' : 'false' }}">Sac</button>
                        </div>
                    @endif

                    <div class="sg-stepper">
                        <button type="button" wire:click="adjust(-1)" aria-label="Moins">−</button>
                        <input type="number" inputmode="numeric" min="0" wire:model.live.debounce.200ms="quantity" aria-label="Quantité">
                        <button type="button" wire:click="adjust(1)" aria-label="Plus">＋</button>
                    </div>

                    <div class="sg-chips">
                        @foreach ([5, 10, 20] as $step)
                            <button type="button" wire:click="adjust({{ $step }})">+{{ $step }}</button>
                        @endforeach
                    </div>
                    @error('quantity') <p class="sg-error">{{ $message }}</p> @enderror
                </div>

                @if ($preview)
                    <div class="sg-preview @if (! $preview['valid']) bad @endif">
                        @if ($direction === 'set')
                            Nouveau stock : <strong>{{ $plural($preview['after'], 'sac') }}</strong>
                            ({{ $preview['delta'] >= 0 ? '+' : '−' }}{{ abs($preview['delta']) }} par rapport à maintenant)
                        @else
                            <strong>{{ $preview['delta'] >= 0 ? '+' : '−' }}{{ $plural(abs($preview['delta']), 'sac') }}</strong>
                            → stock : <strong>{{ $plural($preview['after'], 'sac') }}</strong>
                        @endif
                        @if ($selected['perCarton'] > 1 && $preview['valid'])
                            <span class="sg-sub">— soit {{ $plural($preview['cartons'], 'carton') }} + {{ $plural($preview['loose'], 'sac') }}</span>
                        @endif
                        @unless ($preview['valid']) — stock insuffisant @endunless
                    </div>
                @endif

                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:.6rem">
                    <div>
                        <span class="sg-label">Motif</span>
                        <x-filament::input.wrapper>
                            <x-filament::input.select wire:model.live="reason">
                                @foreach (StockReason::forDirection($direction) as $case)
                                    <option value="{{ $case->value }}">{{ $case->getLabel() }}</option>
                                @endforeach
                            </x-filament::input.select>
                        </x-filament::input.wrapper>
                    </div>
                    <div>
                        <span class="sg-label">Note (facultatif)</span>
                        <x-filament::input.wrapper>
                            <x-filament::input type="text" wire:model="note" maxlength="200" placeholder="N° de bon de livraison…" />
                        </x-filament::input.wrapper>
                    </div>
                </div>
                @error('reason') <p class="sg-error">{{ $message }}</p> @enderror

                <div class="sg-actions">
                    <x-filament::button color="gray" wire:click="closeOverlay">Annuler</x-filament::button>
                    <x-filament::button wire:click="submit" wire:loading.attr="disabled" :disabled="$preview && ! $preview['valid']">Valider</x-filament::button>
                </div>

                @if ($this->history->isNotEmpty())
                    <ul class="sg-history" aria-label="Derniers mouvements">
                        @foreach ($this->history as $movement)
                            <li>
                                <span>{{ $movement->created_at->format('d/m H:i') }} · {{ $movement->reason->getLabel() }}@if ($movement->staff) · {{ $movement->staff->first_name }}@endif</span>
                                <span>{{ $movement->quantity > 0 ? '+' : '−' }}{{ abs($movement->quantity) }} → {{ $movement->stock_after }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    @endif
</x-filament-panels::page>
