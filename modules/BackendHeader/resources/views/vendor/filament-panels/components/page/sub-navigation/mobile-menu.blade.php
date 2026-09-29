{{--
    Surcharge de vendor/filament/filament/resources/views/components/page/sub-navigation/mobile-menu.blade.php,
    enregistrée par Modules\BackendHeader\BackendHeaderServiceProvider.

    Seul changement par rapport à l'original : le menu n'est rendu que lorsqu'il est appelé avec `in-header`,
    c'est-à-dire depuis l'en-tête surchargé (components/header/index.blade.php), pour qu'il soit épinglé avec
    lui. L'appel d'origine, fait par la vue de page de Filament juste avant l'en-tête, ne rend donc plus rien.

    À reporter manuellement si une future mise à jour de filament/filament modifie ce fichier d'origine.
--}}
@props([
    'navigation',
    'inHeader' => false,
])

@php
    use Filament\Support\Icons\Heroicon;
    use Filament\View\PanelsIconAlias;
@endphp

@if ($inHeader)
<x-filament::dropdown
    placement="bottom-start"
    width="xs"
    :attributes="
        \Filament\Support\prepare_inherited_attributes($attributes)
            ->class(['fi-page-sub-navigation-dropdown'])
    "
>
    <x-slot name="trigger">
        @php
            $activeItem = null;

            foreach ($navigation as $navigationGroup) {
                foreach ($navigationGroup->getItems() as $navigationItem) {
                    foreach ([$navigationItem, ...$navigationItem->getChildItems()] as $navigationItemChild) {
                        if ($navigationItemChild->isActive()) {
                            $activeItem = $navigationItemChild;

                            break 3;
                        }
                    }
                }
            }
        @endphp

        <x-filament::button
            color="gray"
            :icon="Heroicon::ChevronDown"
            :icon-alias="PanelsIconAlias::SUB_NAVIGATION_MOBILE_MENU_BUTTON"
            icon-position="after"
        >
            {{ $activeItem?->getLabel() }}
        </x-filament::button>
    </x-slot>

    @foreach ($navigation as $navigationGroup)
        @if (filled($navigationGroupLabel = $navigationGroup->getLabel()))
            <x-filament::dropdown.header>
                {{ $navigationGroupLabel }}
            </x-filament::dropdown.header>
        @endif

        <x-filament::dropdown.list>
            @foreach ($navigationGroup->getItems() as $navigationItem)
                @foreach ([$navigationItem, ...$navigationItem->getChildItems()] as $navigationItemChild)
                    @php
                        $navigationItemBadge = $navigationItem->getBadge();
                        $navigationItemBadgeColor = $navigationItem->getBadgeColor($navigationItemBadge);
                        $navigationItemIcon = $navigationItem->isActive() ? ($navigationItem->getActiveIcon() ?? $navigationItem->getIcon()) : $navigationItem->getIcon();
                        $navigationItemUrl = $navigationItem->getUrl();
                        $shouldNavigationItemOpenUrlInNewTab = $navigationItem->shouldOpenUrlInNewTab();
                        $navigationItemExtraAttributes = $navigationItemChild->getExtraAttributeBag();
                    @endphp

                    <x-filament::dropdown.list.item
                        :badge="$navigationItemBadge"
                        :badge-color="$navigationItemBadgeColor"
                        :href="$navigationItemUrl"
                        :icon="$navigationItemIcon"
                        tag="a"
                        :target="$shouldNavigationItemOpenUrlInNewTab ? '_blank' : null"
                        :aria-current="$navigationItemChild->isActive() ? 'page' : null"
                        :attributes="\Filament\Support\prepare_inherited_attributes($navigationItemExtraAttributes)"
                    >
                        {{ $navigationItemChild->getLabel() }}
                    </x-filament::dropdown.list.item>
                @endforeach
            @endforeach
        </x-filament::dropdown.list>
    @endforeach
</x-filament::dropdown>
@endif
