<?php

namespace Modules\Vitrine\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Modules\Ia\Services\GeminiException;
use Modules\Ia\Services\ImagePayload;
use Modules\Ia\Services\PhotoMatcher;
use Modules\Vitrine\Support\CatalogueQuery;

/**
 * Page « recherche par photo » : le catalogue complet, avec en tête les bonbons
 * que l'IA reconnaît sur la photo envoyée.
 */
class PhotoSearchController extends Controller
{
    public function show(Request $request)
    {
        return $this->render($request);
    }

    public function search(Request $request, PhotoMatcher $matcher)
    {
        $request->validate([
            'photo' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
        ]);

        try {
            $result = $matcher->match(ImagePayload::fromPath($request->file('photo')->getRealPath()));
        } catch (GeminiException $e) {
            Log::warning('Recherche par photo impossible', ['error' => $e->getMessage()]);

            return $this->render($request, error: config('app.debug') ? $e->getMessage() : __('vitrine::ui.photo_error'));
        }

        return $this->render($request, $result);
    }

    /** @param array{description: string, matches: array<int, float>}|null $result */
    private function render(Request $request, ?array $result = null, ?string $error = null)
    {
        $filters = CatalogueQuery::fromRequest($request);
        $products = $filters->products();
        $matchIds = array_keys($result['matches'] ?? []);

        return view('vitrine::photo', [
            'filters' => $filters,
            'matches' => $products->filter(fn ($p) => in_array($p->id, $matchIds, true))->sortBy(fn ($p) => array_search($p->id, $matchIds, true))->values(),
            'confidences' => $result['matches'] ?? [],
            'products' => $products->reject(fn ($p) => in_array($p->id, $matchIds, true))->values(),
            'searched' => $result !== null,
            'seen' => $result['description'] ?? null,
            'error' => $error,
            ...CatalogueController::filterOptions($filters),
        ]);
    }
}
