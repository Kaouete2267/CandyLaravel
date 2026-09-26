{{-- Une page du panneau de sous-menu (voir vendor/filament-panels/livewire/sidebar.blade.php), avec ses éventuelles sous-pages. --}}
@php
    use Filament\Support\Enums\IconSize;
    use Filament\Support\View\ComponentAttributeBag;

    use function Filament\Support\generate_href_html;
    use function Filament\Support\generate_icon_html;

    $isChild ??= false;
    $isItemActive = (! $item->isChildItemsActive()) && $item->isActive();
    $itemIcon = $isItemActive ? ($item->getActiveIcon() ?? $item->getIcon()) : $item->getIcon();
    $itemBadge = $item->getBadge();
    $itemChildItems = $item->getChildItems();
@endphp

<li>
    <a
        {{ generate_href_html($item->getUrl(), $item->shouldOpenUrlInNewTab()) }}
        @if ($isItemActive) aria-current="page" @endif
        @class([
            'flex items-center gap-x-3 px-3 text-sm outline-hidden transition duration-75',
            'py-2.5' => ! $isChild,
            'py-2 ps-10' => $isChild,
            'bg-white font-medium text-gray-950 dark:bg-white/10 dark:text-white' => $isItemActive,
            'text-gray-700 hover:bg-white/60 hover:text-gray-950 focus-visible:bg-white/60 dark:text-gray-300 dark:hover:bg-white/5 dark:hover:text-white' => ! $isItemActive,
        ])
    >
        @if ($itemIcon && ! $isChild)
            {{ generate_icon_html($itemIcon, size: IconSize::Medium, attributes: new ComponentAttributeBag(['class' => $isItemActive ? 'text-gray-950 dark:text-white' : 'text-gray-500 dark:text-gray-400'])) }}
        @endif

        <span class="flex-1 truncate">
            {{ $item->getLabel() }}
        </span>

        @if (filled($itemBadge))
            <x-filament::badge
                :color="$item->getBadgeColor($itemBadge)"
                :tooltip="$item->getBadgeTooltip($itemBadge)"
                size="sm"
            >
                {{ $itemBadge }}
            </x-filament::badge>
        @endif
    </a>

    @if (filled($itemChildItems))
        <ul class="mt-1 flex flex-col gap-y-1">
            @foreach ($itemChildItems as $childItem)
                @include('menu::item', ['item' => $childItem, 'isChild' => true])
            @endforeach
        </ul>
    @endif
</li>
