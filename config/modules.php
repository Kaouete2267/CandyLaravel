<?php

use Modules\Admin\AdminServiceProvider;
use Modules\Allergenes\AllergenesServiceProvider;
use Modules\Analyse\AnalyseServiceProvider;
use Modules\BackendHeader\BackendHeaderServiceProvider;
use Modules\Fournisseurs\FournisseursServiceProvider;
use Modules\Ia\IaServiceProvider;
use Modules\Invitations\InvitationsServiceProvider;
use Modules\Legacy\LegacyServiceProvider;
use Modules\Menu\MenuServiceProvider;
use Modules\Panneaux\PanneauxServiceProvider;
use Modules\Stock\StockServiceProvider;
use Modules\Types\TypesServiceProvider;
use Modules\Vitrine\VitrineServiceProvider;

/*
| Modules actifs. Chaque module est un dossier de `modules/` (namespace Modules\<Nom>) avec son propre
| provider : migrations, routes, vues, traductions, ressources/pages Filament.
| Un module peut fournir `public static function filamentPlugin(): ?Filament\Contracts\Plugin`
| pour se brancher sur le panneau d'administration Lunar.
| Ordre = ordre de chargement ; désactiver un module = retirer sa ligne.
*/
return [
    'enabled' => [
        AdminServiceProvider::class,
        BackendHeaderServiceProvider::class,
        MenuServiceProvider::class,
        PanneauxServiceProvider::class,
        TypesServiceProvider::class,
        AllergenesServiceProvider::class,
        StockServiceProvider::class,
        FournisseursServiceProvider::class,
        AnalyseServiceProvider::class,
        InvitationsServiceProvider::class,
        IaServiceProvider::class,
        VitrineServiceProvider::class,
        LegacyServiceProvider::class,
    ],
];
