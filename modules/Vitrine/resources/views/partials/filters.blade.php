{{-- Variables : $action (URL), $filters (CatalogueQuery), $brands, $panels, $blocks, $allergens --}}
<details class="filters" id="filters">
    <summary>{{ __('vitrine::ui.filters') }} <span aria-hidden="true">⚙</span></summary>

    <form method="GET" action="{{ $action }}">
        <fieldset>
            <legend><label for="q">{{ __('vitrine::ui.search') }}</label></legend>
            <input id="q" type="search" name="q" value="{{ $filters->q }}" placeholder="{{ __('vitrine::ui.search_placeholder') }}">
        </fieldset>

        @if ($panels->isNotEmpty())
            <fieldset>
                <legend><label for="panel">{{ __('vitrine::ui.panel') }}</label></legend>
                <select id="panel" name="panel" onchange="this.form.requestSubmit()">
                    <option value="">{{ __('vitrine::ui.all_panels') }}</option>
                    @foreach ($panels as $panel)
                        <option value="{{ $panel->id }}" @selected($filters->panel === $panel->id)>{{ $panel->name }}</option>
                    @endforeach
                </select>
            </fieldset>
        @endif

        @if (($types ?? collect())->isNotEmpty())
            <fieldset>
                <legend>{{ __('vitrine::ui.type') }}</legend>
                @foreach ($types as $type)
                    <label class="opt">
                        <input type="checkbox" name="types[]" value="{{ $type->id }}" @checked(in_array($type->id, $filters->types, true)) onchange="this.form.requestSubmit()">
                        @if ($type->color)<span class="dot" style="background:{{ $type->color }}"></span>@endif{{ $type->name }}
                    </label>
                @endforeach
            </fieldset>
        @endif

        @if ($allergens->isNotEmpty())
            <fieldset>
                <legend>{{ __('vitrine::ui.allergen_free') }}</legend>
                <p class="hint">{{ __('vitrine::ui.allergen_hint') }}</p>
                @foreach ($allergens as $allergen)
                    <label class="opt">
                        <input type="checkbox" name="sans[]" value="{{ $allergen->id }}" @checked(in_array($allergen->id, $filters->without, true)) onchange="this.form.requestSubmit()">
                        {{ $allergen->name }}
                    </label>
                @endforeach
                <input type="hidden" name="traces" value="0">
                <label class="opt" style="margin-top:.4rem">
                    <input type="checkbox" name="traces" value="1" @checked($filters->traces) onchange="this.form.requestSubmit()">
                    {{ __('vitrine::ui.exclude_traces') }}
                </label>
            </fieldset>
        @endif

        @if ($brands->isNotEmpty())
            <fieldset>
                <legend>{{ __('vitrine::ui.brands') }}</legend>
                @foreach ($brands as $brand)
                    <label class="opt">
                        <input type="checkbox" name="brands[]" value="{{ $brand->id }}" @checked(in_array($brand->id, $filters->brands, true)) onchange="this.form.requestSubmit()">
                        {{ $brand->name }}
                    </label>
                @endforeach
            </fieldset>
        @endif

        <fieldset>
            <legend><label for="sort">{{ __('vitrine::ui.sort') }}</label></legend>
            <select id="sort" name="sort" onchange="this.form.requestSubmit()">
                @foreach (['name', 'brand'] as $sort)
                    <option value="{{ $sort }}" @selected($filters->sort === $sort)>{{ __('vitrine::ui.sort_'.$sort) }}</option>
                @endforeach
            </select>
        </fieldset>

        <div class="row" style="margin-bottom:1rem">
            <button class="btn" type="submit">{{ __('vitrine::ui.apply') }}</button>
            <a class="btn ghost" href="{{ $action }}">{{ __('vitrine::ui.reset') }}</a>
        </div>
    </form>
</details>
<script>
    // Filtres ouverts d'office sur grand écran, repliés sur mobile.
    if (window.matchMedia('(min-width: 900px)').matches) document.getElementById('filters').open = true;
</script>
