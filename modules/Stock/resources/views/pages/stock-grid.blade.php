@php
    use Modules\Stock\Enums\StockReason;

    $levelLabels = ['ok' => 'En stock', 'low' => 'Stock bas', 'out' => 'Rupture'];
    $selected = $this->selected;
    $preview = $this->preview;
    $plural = fn (int $n, string $word) => $n.' '.$word.($n > 1 ? 's' : '');
@endphp

<x-filament-panels::page>
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
                        <span class="sg-stock">{{ $card['stock'] }} <small>kg</small></span>
                        <span class="sg-sub">
                            soit {{ $plural($card['cartons'], 'carton') }}
                            @if ($card['loose'])
                                + {{ $card['loose'] }} kg
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
                        <strong>{{ $selected['stock'] }}</strong> kg
                    </div>
                    <div style="text-align:right; font-size:.85rem" class="sg-brand">
                        Sac de {{ $selected['perBag'] }} kg · carton de {{ $selected['perCarton'] }} kg
                        <br>soit {{ $plural($selected['cartons'], 'carton') }} + {{ $selected['loose'] }} kg
                        <br>Seuil d'alerte : {{ $selected['min'] }} kg
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

                    @if ($direction !== 'set')
                        <div class="sg-seg c3" style="margin-bottom:.5rem">
                            <button type="button" wire:click="setUnit('bag')" aria-pressed="{{ $unit === 'bag' ? 'true' : 'false' }}">Sac ({{ $selected['perBag'] }} kg)</button>
                            <button type="button" wire:click="setUnit('carton')" aria-pressed="{{ $unit === 'carton' ? 'true' : 'false' }}">Carton ({{ $selected['perCarton'] }} kg)</button>
                            <button type="button" wire:click="setUnit('kg')" aria-pressed="{{ $unit === 'kg' ? 'true' : 'false' }}">kg</button>
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
                            Nouveau stock : <strong>{{ $preview['after'] }} kg</strong>
                            ({{ $preview['delta'] >= 0 ? '+' : '−' }}{{ abs($preview['delta']) }} kg par rapport à maintenant)
                        @else
                            <strong>{{ $preview['delta'] >= 0 ? '+' : '−' }}{{ abs($preview['delta']) }} kg</strong>
                            → stock : <strong>{{ $preview['after'] }} kg</strong>
                        @endif
                        @if ($preview['valid'])
                            <span class="sg-sub">— soit {{ $plural($preview['cartons'], 'carton') }} + {{ $preview['loose'] }} kg</span>
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
                                <span>{{ $movement->quantity > 0 ? '+' : '−' }}{{ abs($movement->quantity) }} kg → {{ $movement->stock_after }} kg</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    @endif
</x-filament-panels::page>
