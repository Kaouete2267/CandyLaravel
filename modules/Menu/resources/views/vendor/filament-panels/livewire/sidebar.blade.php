{{--
    Surcharge de vendor/filament/filament/resources/views/livewire/sidebar.blade.php, enregistrée par
    Modules\Menu\MenuServiceProvider via prependNamespace().

    Menu à deux colonnes :
    - colonne étroite : un bouton par groupe de navigation (icône + titre) — les pages sans groupe
      (ex. tableau de bord) y sont des liens directs ; menu utilisateur en bas ;
    - panneau : pages du groupe cliqué, affiché par-dessus le contenu de la page ; refermé par la croix, un nouveau
      clic sur le groupe, un clic en dehors ou Échap.

    À reporter manuellement si une future mise à jour de filament/filament modifie ce fichier d'origine
    (render hooks, pied de barre latérale, modales d'actions…).
--}}
<div>
    @php
        use Filament\Enums\DatabaseNotificationsPosition;
        use Filament\Enums\UserMenuPosition;
        use Filament\Support\Enums\IconSize;
        use Filament\Support\Facades\FilamentView;
        use Filament\Support\Icons\Heroicon;
        use Filament\View\PanelsRenderHook;
        use Modules\Menu\Filament\MenuPlugin;

        use function Filament\Support\generate_href_html;
        use function Filament\Support\generate_icon_html;

        $menuPlugin = MenuPlugin::get();
        $navigation = filament()->getNavigation();

        $ungroupedItems = [];
        $groups = [];

        foreach ($navigation as $group) {
            if (blank($group->getLabel())) {
                array_push($ungroupedItems, ...$group->getItems());

                continue;
            }

            $groups[] = [
                'key' => 'menu-group-' . md5($group->getLabel()),
                'label' => $group->getLabel(),
                'icon' => $menuPlugin->getGroupIcon($group),
                'isActive' => $group->isActive(),
                'items' => $group->getItems(),
            ];
        }

        $isAuthenticated = filament()->auth()->check();
        $hasDatabaseNotificationsInSidebar = $isAuthenticated && filament()->hasDatabaseNotifications() && filament()->getDatabaseNotificationsPosition() === DatabaseNotificationsPosition::Sidebar;
        $hasUserMenuInSidebar = $isAuthenticated && filament()->hasUserMenu() && filament()->getUserMenuPosition() === UserMenuPosition::Sidebar;

        // Le groupe ouvert prend le fond du panneau (bg-gray-100 / dark:bg-gray-800) pour s'y raccorder comme un onglet ;
        // la catégorie de la page courante est en couleur primaire et en gras, qu'elle soit ouverte ou non.
        $railEntryClasses = 'flex w-full flex-col items-center gap-y-1 px-1 py-2.5 text-center outline-hidden transition duration-75';
        $railEntryOpenBackgroundClasses = 'bg-gray-100 dark:bg-gray-800';
        $railEntryClosedBackgroundClasses = 'hover:bg-gray-100/60 focus-visible:bg-gray-100/60 dark:hover:bg-white/5';
        $railEntryCurrentTextClasses = 'font-bold text-primary-600 dark:text-primary-400';
        $railEntryOpenTextClasses = 'font-semibold text-gray-950 dark:text-white';
        $railEntryClosedTextClasses = 'font-medium text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white';
    @endphp

    {{-- format-ignore-start --}}
    <div
        x-data="{
            openGroup: null,
            toggleGroup(key) {
                this.openGroup = this.openGroup === key ? null : key
            },
            isGroupShown(key) {
                return this.openGroup === key
            },
        }"
        x-on:click.outside="openGroup = null"
        x-on:keydown.escape.window="openGroup = null"
        x-cloak="-lg"
        x-bind:class="{ 'fi-sidebar-open': $store.sidebar.isOpen }"
        id="fi-main-sidebar"
        class="fi-sidebar fi-main-sidebar w-auto! flex-row! py-2 ps-2 lg:z-30! lg:ps-3 border-e-2 border-gray-950/10 dark:border-white/10"
    >
        {{ FilamentView::renderHook(PanelsRenderHook::SIDEBAR_START) }}

        {{-- Colonne des groupes --}}
        <div class="flex w-20 shrink-0 flex-col items-center gap-y-2 py-2">
            {{ FilamentView::renderHook(PanelsRenderHook::SIDEBAR_LOGO_BEFORE) }}

            {{-- En mobile, le menu est replié hors écran et s'ouvre avec le bouton burger de Filament ; celui-ci le referme. --}}
            <x-filament::icon-button
                color="gray"
                :icon="Heroicon::XMark"
                icon-size="lg"
                :label="__('filament-panels::layout.actions.sidebar.collapse.label')"
                x-on:click="$store.sidebar.close()"
                class="mb-2 lg:hidden"
            />

            {{ FilamentView::renderHook(PanelsRenderHook::SIDEBAR_LOGO_AFTER) }}

            <nav
                aria-label="{{ __('filament-panels::layout.navigation.label') }}"
                class="flex w-full flex-1 flex-col items-center gap-y-1 overflow-y-auto"
            >
                {{ FilamentView::renderHook(PanelsRenderHook::SIDEBAR_NAV_START) }}

                @foreach ($ungroupedItems as $item)
                    @php
                        $isItemActive = $item->isActive();
                        $itemIcon = $isItemActive ? ($item->getActiveIcon() ?? $item->getIcon()) : $item->getIcon();
                    @endphp

                    <a
                        {{ generate_href_html($item->getUrl(), $item->shouldOpenUrlInNewTab()) }}
                        @if ($isItemActive) aria-current="page" @endif
                        @class([
                            $railEntryClasses,
                            $isItemActive ? $railEntryCurrentTextClasses : $railEntryClosedTextClasses,
                            $railEntryClosedBackgroundClasses,
                        ])
                    >
                        {{ generate_icon_html($itemIcon ?? Heroicon::OutlinedHome, size: IconSize::Large) }}

                        <span class="line-clamp-2 text-[11px] leading-tight">
                            {{ $item->getLabel() }}
                        </span>
                    </a>
                @endforeach

                @if (filled($ungroupedItems) && filled($groups))
                    <hr class="my-2 w-10 border-gray-950/10 dark:border-white/10" />
                @endif

                @foreach ($groups as $group)
                    <button
                        type="button"
                        x-on:click="toggleGroup(@js($group['key']))"
                        x-bind:aria-expanded="isGroupShown(@js($group['key']))"
                        aria-controls="{{ $group['key'] }}"
                        @if ($group['isActive'])
                            x-bind:class="isGroupShown(@js($group['key'])) ? @js($railEntryOpenBackgroundClasses) : @js($railEntryClosedBackgroundClasses)"
                            class="{{ $railEntryClasses }} {{ $railEntryCurrentTextClasses }}"
                        @else
                            x-bind:class="isGroupShown(@js($group['key'])) ? @js($railEntryOpenBackgroundClasses . ' ' . $railEntryOpenTextClasses) : @js($railEntryClosedBackgroundClasses . ' ' . $railEntryClosedTextClasses)"
                            class="{{ $railEntryClasses }}"
                        @endif
                    >
                        {{ generate_icon_html($group['icon'], size: IconSize::Large) }}

                        <span class="line-clamp-2 text-[11px] leading-tight">
                            {{ $group['label'] }}
                        </span>
                    </button>
                @endforeach

                {{ FilamentView::renderHook(PanelsRenderHook::SIDEBAR_NAV_END) }}
            </nav>

            @if ($hasDatabaseNotificationsInSidebar || $hasUserMenuInSidebar)
                <div class="mt-2 flex w-full flex-col items-center gap-y-2 border-t border-gray-950/10 pt-3 dark:border-white/10">
                    @if ($hasDatabaseNotificationsInSidebar)
                        @livewire(filament()->getDatabaseNotificationsLivewireComponent(), [
                            'lazy' => filament()->hasLazyLoadedDatabaseNotifications(),
                        ])
                    @endif

                    @if ($hasUserMenuInSidebar)
                        {{-- La position « topbar » n'affiche que l'avatar, adapté à la colonne étroite. --}}
                        <x-filament-panels::user-menu :position="UserMenuPosition::Topbar" />
                    @endif
                </div>
            @endif

            {{ FilamentView::renderHook(PanelsRenderHook::SIDEBAR_FOOTER) }}
        </div>

        {{--
            Panneau des pages du groupe sélectionné : positionné en absolu à droite de la colonne, il passe
            au-dessus du contenu (fi-main-content) sans modifier la largeur de la barre latérale, donc sans
            redimensionner la page à l'ouverture.
        --}}
        @foreach ($groups as $group)
            <div
                id="{{ $group['key'] }}"
                x-show="isGroupShown(@js($group['key']))"
                x-transition:enter="transition duration-150 ease-out"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="transition duration-100 ease-in"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                style="display: none"
                class="absolute inset-y-0 start-full flex h-full w-64 flex-col overflow-hidden bg-gray-100 shadow-[12px_0_24px_-12px_rgb(0_0_0/0.15)] dark:bg-gray-800"
            >
                <header class="flex items-center justify-between gap-x-3 ps-4 pe-2 pt-4 pb-2">
                    <h2 class="truncate text-base font-semibold text-gray-950 dark:text-white">
                        {{ $group['label'] }}
                    </h2>

                    <x-filament::icon-button
                        color="gray"
                        :icon="Heroicon::XMark"
                        :label="__('filament-panels::layout.actions.sidebar.collapse.label')"
                        x-on:click="openGroup = null"
                    />
                </header>

                <ul class="flex flex-1 flex-col gap-y-1 overflow-y-auto px-3 pt-2 pb-3">
                    @foreach ($group['items'] as $item)
                        @include('menu::item', ['item' => $item])
                    @endforeach
                </ul>
            </div>
        @endforeach
    </div>
    {{-- format-ignore-end --}}

    <x-filament-actions::modals />
</div>
