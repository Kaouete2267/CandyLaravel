@extends('vitrine::layout')

@section('title', __('vitrine::ui.photo_search'))

@section('content')
    <h1>{{ __('vitrine::ui.photo_title') }}</h1>
    <p style="color:var(--muted); margin-top:-.5rem">{{ __('vitrine::ui.photo_intro') }}</p>

    <form class="dropzone" method="POST" action="{{ route('vitrine.photo.search') }}" enctype="multipart/form-data" id="photo-form">
        @csrf
        <label class="btn ghost" for="photo">📷 {{ __('vitrine::ui.photo_choose') }}</label>
        <input class="sr" id="photo" type="file" name="photo" accept="image/*" capture="environment" required>
        <img class="preview" id="preview" alt="">
        <button class="btn" type="submit" id="go">{{ __('vitrine::ui.photo_go') }}</button>
        @error('photo') <span style="color:var(--bad)">{{ $message }}</span> @enderror
    </form>

    @if ($error)
        <div class="note err" role="alert">{{ $error }}</div>
    @endif

    @if ($searched)
        <section aria-live="polite">
            @if ($seen)
                <div class="note">{{ __('vitrine::ui.photo_seen', ['text' => $seen]) }}</div>
            @endif

            <h2 style="font-size:1.15rem">{{ __('vitrine::ui.photo_matches') }}</h2>
            @if ($matches->isEmpty())
                <div class="empty">{{ __('vitrine::ui.photo_none') }}</div>
            @else
                <div class="grid">
                    @foreach ($matches as $product)
                        @include('vitrine::partials.card', ['product' => $product, 'confidence' => $confidences[$product->id] ?? 0])
                    @endforeach
                </div>
            @endif
        </section>
    @endif

    <h2 style="font-size:1.15rem; margin-top:2rem">{{ $searched ? __('vitrine::ui.photo_rest') : __('vitrine::ui.catalogue') }}</h2>
    <div class="layout">
        @include('vitrine::partials.filters', ['action' => route('vitrine.photo')])

        <section>
            <div class="bar"><span class="count">{{ trans_choice('vitrine::ui.results', $products->count()) }}</span></div>
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

    <script>
        const input = document.getElementById('photo'), preview = document.getElementById('preview'), form = document.getElementById('photo-form');
        input.addEventListener('change', () => {
            const file = input.files[0];
            if (!file) return;
            preview.src = URL.createObjectURL(file);
            preview.style.display = 'block';
        });
        form.addEventListener('submit', () => {
            const go = document.getElementById('go');
            go.disabled = true;
            go.textContent = @json(__('vitrine::ui.photo_analyzing'));
        });
    </script>
@endsection
