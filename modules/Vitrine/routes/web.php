<?php

use Illuminate\Support\Facades\Route;
use Modules\Support\Modules;
use Modules\Vitrine\Http\Controllers\CatalogueController;
use Modules\Vitrine\Http\Controllers\LocaleController;
use Modules\Vitrine\Http\Controllers\PhotoSearchController;

Route::middleware(['web', 'locale'])->group(function () {
    Route::get('/langue/{locale}', LocaleController::class)->name('vitrine.locale');

    // Tout le catalogue est réservé aux visiteurs munis d'une invitation valide.
    Route::middleware('invited')->group(function () {
        Route::get('/', [CatalogueController::class, 'index'])->name('vitrine.catalogue');
        Route::get('/produit/{product}', [CatalogueController::class, 'show'])->whereNumber('product')->name('vitrine.product');

        if (Modules::enabled('Ia')) {
            Route::get('/recherche-photo', [PhotoSearchController::class, 'show'])->name('vitrine.photo');
            // Chaque recherche consomme le quota gratuit de l'IA : on limite par visiteur.
            Route::post('/recherche-photo', [PhotoSearchController::class, 'search'])
                ->middleware('throttle:12,1')->name('vitrine.photo.search');
        }
    });
});
