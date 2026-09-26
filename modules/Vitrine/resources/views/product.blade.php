@extends('vitrine::layout')

@php
    $name = $product->translateAttribute('name');
    $image = $product->thumbnail?->getUrl('large');
    $onPanels = Modules\Support\Modules::enabled('Panneaux') ? $product->panels->filter(fn ($p) => $p->pivot->active) : collect();
    $description = $product->translateAttribute('description');
    $ingredients = $product->translateAttribute('ingredients');
@endphp

@section('title', $name)

@section('content')
    <p><a href="{{ url()->previous() !== url()->current() && str_starts_with(url()->previous(), route('vitrine.catalogue')) ? url()->previous() : route('vitrine.catalogue') }}">{{ __('vitrine::ui.back') }}</a></p>

    <div class="detail">
        <div class="photo">
            @if ($image)
                <img src="{{ $image }}" alt="{{ $name }}">
            @else
                <span aria-hidden="true">🍬</span>
            @endif
        </div>

        <article>
            <h1 style="margin-bottom:.25rem">{{ $name }}</h1>
            @if ($product->brand)
                <div class="brand" style="color:var(--muted)">{{ $product->brand->name }}</div>
            @endif

            @if (Modules\Support\Modules::enabled('Types') && ($type = $product->candyType->first()))
                <p style="margin:.6rem 0 0"><span class="chip type" style="font-size:.9rem">@if ($type->color)<span class="dot" style="background:{{ $type->color }}"></span>@endif{{ $type->name }}</span></p>
            @endif

            @if (filled($description))
                <p style="margin-top:1rem">{{ $description }}</p>
            @endif

            @if ($onPanels->isNotEmpty())
                <h2>{{ __('vitrine::ui.panel') }}</h2>
                <p>
                    @foreach ($onPanels as $panel)
                        <span class="chip where">{{ $panel->name }}</span>
                    @endforeach
                </p>
            @endif

            @if (Modules\Support\Modules::enabled('Allergenes'))
                <h2>{{ __('vitrine::ui.allergens') }}</h2>
                @if ($contains->isEmpty() && $traces->isEmpty())
                    <p style="color:var(--muted)">{{ __('vitrine::ui.no_allergens') }}</p>
                @endif
                @if ($contains->isNotEmpty())
                    <p><strong>{{ __('vitrine::ui.contains') }} :</strong></p>
                    <div class="chips">
                        @foreach ($contains as $allergen)
                            <span class="chip" style="font-size:.9rem">{{ $allergen->name }}</span>
                        @endforeach
                    </div>
                @endif
                @if ($traces->isNotEmpty())
                    <p style="margin-top:1rem"><strong>{{ __('vitrine::ui.may_contain') }} :</strong></p>
                    <div class="chips">
                        @foreach ($traces as $allergen)
                            <span class="chip trace" style="font-size:.9rem">{{ $allergen->name }}</span>
                        @endforeach
                    </div>
                @endif
            @endif

            @if (filled($ingredients))
                <h2>{{ __('vitrine::ui.ingredients') }}</h2>
                <p>{{ $ingredients }}</p>
            @endif
        </article>
    </div>
@endsection
