<?php

namespace Modules\Panneaux\Http\Controllers;

use Illuminate\Routing\Controller;
use Modules\Panneaux\Models\Panel;
use Modules\Panneaux\Support\BoardData;

/** Page d'impression d'un panneau : sans l'interface d'administration, pages A4 prêtes à imprimer. */
class PrintPanelController extends Controller
{
    public function __invoke(Panel $panel)
    {
        return view('panneaux::print', ['panel' => $panel, 'data' => BoardData::for($panel)]);
    }
}
