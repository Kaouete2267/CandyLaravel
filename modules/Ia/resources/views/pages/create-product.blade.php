<x-filament-panels::page>
    {{ $this->startForm }}

    @if ($analyzed)
        {{ $this->productForm }}
    @endif
</x-filament-panels::page>
