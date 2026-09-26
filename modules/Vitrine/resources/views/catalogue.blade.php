@extends('vitrine::layout')

@section('title', __('vitrine::ui.catalogue'))

@section('content')
    <h1>{{ __('vitrine::ui.catalogue') }}</h1>

    <div class="layout">
        @include('vitrine::partials.filters', ['action' => route('vitrine.catalogue')])

        <section aria-live="polite">
            <div class="bar">
                <span class="count">{{ trans_choice('vitrine::ui.results', $products->count()) }}</span>
            </div>

            @if ($products->isEmpty())
                <div class="empty">{{ __('vitrine::ui.no_results') }}</div>
            @else
                <div class="grid">
                    @foreach ($products as $product)
                        @include('vitrine::partials.card', ['product' => $product])
                    @endforeach
                </div>
            @endif
        </section>
    </div>
@endsection
