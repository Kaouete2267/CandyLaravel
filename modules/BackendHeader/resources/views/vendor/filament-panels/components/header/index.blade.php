{{--
    Surcharge de vendor/filament/filament/resources/views/components/header/index.blade.php, enregistrée par
    Modules\BackendHeader\BackendHeaderServiceProvider via prependNamespace() (voir ce fichier pour le pourquoi).

    Changements par rapport à l'original :
    - l'en-tête (titre + actions de la page) reste visible en défilant au lieu de disparaître avec le contenu,
      pour garder les actions de formulaire accessibles sans remonter en haut de page ;
    - titre et actions tiennent sur une seule ligne à toutes les tailles d'écran (l'original les empile en
      mobile et les fait passer à la ligne dès que les actions sont larges) : le titre (.fi-header-heading-ctn,
      classe propre à cette surcharge, ciblée aussi par modules/Menu/resources/css/partials/menu.css, min-w-0 flex-1) prend
      la place restante et revient à la ligne dans sa propre colonne, les actions (au plus 60 % de la largeur)
      restent à droite. En mobile (< sm), s'il y a
      plusieurs actions, elles sont regroupées dans une liste déroulante (ActionGroup, rendu en
      `filamentDropdown` comme les actions de masse des tables) au lieu d'être affichées en boutons.
      Le menu travaille sur des clones : ActionGroup::make() rattache ses actions au groupe et leur impose la
      vue « élément de liste », ce qui casserait l'affichage en boutons des mêmes instances au-dessus de sm.
      Les clones gardent leur nom, donc le clic monte bien l'action d'origine côté Livewire ;
    - sur les pages à sous-navigation (ex. édition de produit), le menu déroulant de sous-navigation mobile
      (< md) est rendu ici, en dernière ligne de l'en-tête, pour être épinglé avec lui. Filament le rend
      normalement avant l'en-tête, dans la vue de page : cet appel-là ne rend plus rien (voir la surcharge
      components/page/sub-navigation/mobile-menu.blade.php).

    Les classes sticky/top-0/z-20/pt-4/... ci-dessous sont de vraies classes Tailwind, compilées via le thème
    Filament officiel (resources/css/filament/lunar/theme.css, enregistré avec ->viteTheme() dans
    AppServiceProvider) qui passe par le pipeline Vite/Tailwind de l'app — contrairement au bundle CSS livré
    par le package filament/filament, qui est pré-purgé en JIT et ne contient QUE les classes utilitaires
    littéralement présentes dans les templates de Filament lui-même (d'où l'absence totale d'effet à réutiliser
    ces noms de classe avant la mise en place de ce thème).

    `top-0` (pas `var(--topbar-height)`, essayé d'abord : ça laissait un espace vide au-dessus de l'en-tête une
    fois épinglé — la barre du haut, elle-même sticky en z-30, recouvre alors la portion de l'en-tête qui
    chevauche, sans rien à ajouter côté offset). `py-4` est l'espace intérieur de la bande épinglée (il reste
    visible une fois épinglé, contrairement à celui d'un parent). En mobile, le module Menu réduit son
    padding-top pour aligner le titre sur le bouton burger (modules/Menu/resources/css/partials/menu.css).

    L'espace autour de l'en-tête (padding du conteneur, pleine largeur) est géré par son conteneur, dans
    modules/BackendHeader/resources/css/partials/backend-header.css.

    À reporter manuellement si une future mise à jour de filament/filament modifie ce fichier d'origine.
--}}
@props([
    'actions' => [],
    'actionsAlignment' => null,
    'breadcrumbs' => [],
    'heading' => null,
    'subheading' => null,
])

@php
    use Filament\Actions\Action;
    use Filament\Actions\ActionGroup;
    use Filament\Support\Facades\FilamentView;
    use Filament\View\PanelsRenderHook;

    use function Filament\Support\is_slot_empty;

    $visibleActions = is_array($actions)
        ? array_values(array_filter($actions, fn (Action | ActionGroup $action): bool => $action->isVisible()))
        : [];

    $subNavigation = method_exists($this, 'getCachedSubNavigation') ? $this->getCachedSubNavigation() : [];

    $mobileActionGroup = count($visibleActions) > 1
        ? ActionGroup::make(array_map(fn (Action | ActionGroup $action): Action | ActionGroup => $action->getClone(), $visibleActions))
            ->livewire($this)
            ->button()
            ->dropdownPlacement('bottom-end')
        : null;
@endphp

<header
    {{
        $attributes->class([
            'fi-header',
            'sticky top-0 z-20 py-4 bg-gray-50 border-b border-gray-950/5 dark:bg-gray-950 dark:border-white/10',
            'flex-row flex-wrap items-center justify-between',
            'fi-header-has-breadcrumbs' => $breadcrumbs,
            'fi-header-has-subheading' => filled($subheading),
        ])
    }}
>
    @capture($headingContent)
        @if ($breadcrumbs)
            <x-filament::breadcrumbs :breadcrumbs="$breadcrumbs" />
        @endif

        {{ FilamentView::renderHook(PanelsRenderHook::PAGE_HEADER_HEADING_BEFORE, scopes: $this->getRenderHookScopes()) }}

        @if (filled($heading))
            <h1 class="fi-header-heading">
                {{ $heading }}
            </h1>
        @endif

        {{ FilamentView::renderHook(PanelsRenderHook::PAGE_HEADER_HEADING_AFTER, scopes: $this->getRenderHookScopes()) }}

        @if (filled($subheading))
            <p class="fi-header-subheading">
                {{ $subheading }}
            </p>
        @endif
    @endcapture

    @php
        $headingContent = $headingContent();
    @endphp

    @if (! is_slot_empty($headingContent))
        <div class="fi-header-heading-ctn min-w-0 flex-1 sm:w-auto">{{ $headingContent }}</div>
    @else
        {{ $headingContent }}
    @endif

    @php
        $beforeActions = FilamentView::renderHook(PanelsRenderHook::PAGE_HEADER_ACTIONS_BEFORE, scopes: $this->getRenderHookScopes());
        $afterActions = FilamentView::renderHook(PanelsRenderHook::PAGE_HEADER_ACTIONS_AFTER, scopes: $this->getRenderHookScopes());
    @endphp

    @capture($actionsContent)
        {{ $beforeActions }}

        @if ($mobileActionGroup)
            <div class="fi-header-actions-dropdown sm:hidden">
                {{ $mobileActionGroup }}
            </div>

            <x-filament::actions
                :actions="$actions"
                :alignment="$actionsAlignment"
                class="hidden flex-wrap justify-end sm:flex"
            />
        @elseif ($actions)
            <x-filament::actions
                :actions="$actions"
                :alignment="$actionsAlignment"
                class="flex-wrap justify-end"
            />
        @endif

        {{ $afterActions }}
    @endcapture

    @php
        $actionsContent = $actionsContent();
    @endphp

    @if (! is_slot_empty($actionsContent))
        <div class="fi-header-actions-ctn max-w-[60%] self-center sm:self-center">
            {{ $actionsContent }}
        </div>
    @else
        {{ $actionsContent }}
    @endif

    @if ($subNavigation)
        <x-filament-panels::page.sub-navigation.mobile-menu
            :navigation="$subNavigation"
            in-header
            class="basis-full"
        />
    @endif
</header>
