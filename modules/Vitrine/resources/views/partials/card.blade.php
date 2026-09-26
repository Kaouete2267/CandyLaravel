{{-- Variables : $product, $confidence (optionnel, 0-1) --}}
@php
    $image = $product->thumbnail?->getUrl('medium');
    $name = $product->translateAttribute('name');
    $onPanels = Modules\Support\Modules::enabled('Panneaux') ? $product->panels->filter(fn ($p) => $p->pivot->active) : collect();
    $allergens = Modules\Support\Modules::enabled('Allergenes') ? $product->allergens : collect();
    $contains = $allergens->filter(fn ($a) => $a->pivot->type === 'contains');
    $traces = $allergens->filter(fn ($a) => $a->pivot->type === 'may_contain');
    $shown = $contains->take(3);
    $type = Modules\Support\Modules::enabled('Types') ? $product->candyType->first() : null;
@endphp
<a class="card @isset($confidence) match @endisset" href="{{ route('vitrine.product', $product) }}">
    @isset($confidence)
        <span class="pill">{{ __('vitrine::ui.photo_match_level', ['percent' => (int) round($confidence * 100)]) }}</span>
    @endisset
    <span class="thumb">
        @if ($image)
            <img src="{{ $image }}" alt="{{ $name }}" loading="lazy">
        @else
            <span aria-hidden="true">🍬</span>
        @endif
    </span>
    <span class="body">
        <span class="name">{{ $name }}</span>
        @if ($product->brand)
            <span class="brand">{{ $product->brand->name }}</span>
        @endif
        <span class="chips">
            @if ($type)
                <span class="chip type" title="{{ __('vitrine::ui.type') }}">@if ($type->color)<span class="dot" style="background:{{ $type->color }}"></span>@endif{{ $type->name }}</span>
            @endif
            @foreach ($onPanels as $panel)
                <span class="chip where" title="{{ __('vitrine::ui.panel') }}">{{ $panel->name }}</span>
            @endforeach
            @foreach ($shown as $allergen)
                <span class="chip" title="{{ __('vitrine::ui.contains') }}">{{ $allergen->name }}</span>
            @endforeach
            @if ($contains->count() > $shown->count())
                <span class="chip more">+{{ $contains->count() - $shown->count() }}</span>
            @endif
            @if ($traces->isNotEmpty())
                <span class="chip trace" title="{{ __('vitrine::ui.may_contain') }} {{ $traces->pluck('name')->implode(', ') }}">~ {{ $traces->count() }}</span>
            @endif
        </span>
    </span>
</a>
