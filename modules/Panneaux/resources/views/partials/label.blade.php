{{-- Variables : $l (étiquette de BoardData), $s (réglages du panneau) --}}
@php
    $head = ($l['color'] ? 'background:'.e($l['color']).';' : '').Modules\Panneaux\Support\BoardData::font($s['bonbon']['name']).($l['ink'] ? 'color:'.e($l['ink']).';' : '');
@endphp
<div class="etq etq_bb" style="border-color: {{ $s['bonbon']['bordercolor'] }}">
    @if ($l['logo'])
        <table class="etq_bb_entete" style="{{ $head }}"><tr>
            <td>{{ $l['name'] }}</td>
            <td><span class="etq_bb_logo"><img src="{{ $l['logo'] }}" alt="{{ $l['brand'] }}"></span></td>
        </tr></table>
    @else
        <div class="etq_bb_entete" style="{{ $head }}">{{ $l['name'] }}@if ($l['brand']) <small style="font-weight:normal">— {{ $l['brand'] }}</small>@endif</div>
    @endif
    <div class="etq_bb_corp" style="{{ Modules\Panneaux\Support\BoardData::font($s['bonbon']['ing']) }}">
        @if ($l['ingredients'] !== '')
            <span class="label">{{ __('panneaux::board.ingredients') }} : </span>{!! $l['ingredients'] !!}
        @else
            <span style="color:#999;font-style:italic">{{ __('panneaux::board.no_ingredients') }}</span>
        @endif
    </div>
</div>
