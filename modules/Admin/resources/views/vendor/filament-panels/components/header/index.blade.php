{{--
    Surcharge de vendor/filament/filament/resources/views/components/header/index.blade.php, enregistrée par
    Modules\Admin\AdminServiceProvider via prependNamespace() (voir ce fichier pour le pourquoi).

    Seul changement par rapport à l'original : l'en-tête (titre + actions de la page) reste visible en
    défilant au lieu de disparaître avec le contenu, pour garder les actions de formulaire accessibles sans
    remonter en haut de page.

    Les classes sticky/top-0/z-20/pt-8/... ci-dessous sont de vraies classes Tailwind, compilées via le thème
    Filament officiel (resources/css/filament/lunar/theme.css, enregistré avec ->viteTheme() dans
    AppServiceProvider) qui passe par le pipeline Vite/Tailwind de l'app — contrairement au bundle CSS livré
    par le package filament/filament, qui est pré-purgé en JIT et ne contient QUE les classes utilitaires
    littéralement présentes dans les templates de Filament lui-même (d'où l'absence totale d'effet à réutiliser
    ces noms de classe avant la mise en place de ce thème).

    `top-0` (pas `var(--topbar-height)`, essayé d'abord : ça laissait un espace vide au-dessus de l'en-tête une
    fois épinglé — la barre du haut, elle-même sticky en z-30, recouvre alors la portion de l'en-tête qui
    chevauche, sans rien à ajouter côté offset). `pt-8` conserve l'espacement visuel du padding du parent
    (.fi-page-header-main-ctn, classe Tailwind py-8) qui, lui, n'est pas repris une fois l'en-tête épinglé
    puisqu'il appartient au parent et non à .fi-header.

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
    use Filament\Support\Facades\FilamentView;
    use Filament\View\PanelsRenderHook;

    use function Filament\Support\is_slot_empty;
@endphp

<header
    {{
        $attributes->class([
            'fi-header',
            'sticky top-0 z-20 pt-8 bg-gray-50 border-b border-gray-950/5 dark:bg-gray-950 dark:border-white/10',
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
        <div>{{ $headingContent }}</div>
    @else
        {{ $headingContent }}
    @endif

    @php
        $beforeActions = FilamentView::renderHook(PanelsRenderHook::PAGE_HEADER_ACTIONS_BEFORE, scopes: $this->getRenderHookScopes());
        $afterActions = FilamentView::renderHook(PanelsRenderHook::PAGE_HEADER_ACTIONS_AFTER, scopes: $this->getRenderHookScopes());
    @endphp

    @capture($actionsContent)
        {{ $beforeActions }}

        @if ($actions)
            <x-filament::actions
                :actions="$actions"
                :alignment="$actionsAlignment"
            />
        @endif

        {{ $afterActions }}
    @endcapture

    @php
        $actionsContent = $actionsContent();
    @endphp

    @if (! is_slot_empty($actionsContent))
        <div class="fi-header-actions-ctn">
            {{ $actionsContent }}
        </div>
    @else
        {{ $actionsContent }}
    @endif
</header>
