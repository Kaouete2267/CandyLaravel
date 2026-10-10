<?php

namespace Modules\Onboarding\Database\Seeders;

use Illuminate\Database\Seeder;
use Wallacemartinss\FilamentOnboarding\Facades\Onboarding;

/** Parcours de base (checklist modifiable ensuite dans Aide → Onboarding). */
class OnboardingSeeder extends Seeder
{
    public function run(): void
    {
        $flows = [
            [
                'key' => 'produit',
                'title' => ['fr' => 'Créer une fiche bonbon'],
                'description' => ['fr' => 'De la création à la mise en ligne : les étapes pour publier un bonbon complet.'],
                'icon' => 'heroicon-o-cube',
                'sort_order' => 10,
                'steps' => [
                    [
                        'key' => 'creer_bonbon',
                        'title' => ['fr' => 'Créer un nouveau bonbon'],
                        'description' => ['fr' => 'Depuis la liste des produits, cliquez sur « Nouveau » : renseignez le nom, le type de produit et un prix de départ.'],
                        'icon' => 'heroicon-o-plus-circle',
                        'cta_label' => ['fr' => 'Ouvrir la liste des produits'],
                        'cta_route' => 'filament.lunar.resources.products.index',
                        'sort_order' => 10,
                    ],
                    [
                        'key' => 'traduire_fiche',
                        'title' => ['fr' => 'Traduire la fiche (FR / NL / EN)'],
                        'description' => ['fr' => 'Dans l\'onglet « Modifier » du bonbon, complétez le nom, la description et les ingrédients dans les trois langues : français, néerlandais, anglais.'],
                        'icon' => 'heroicon-o-language',
                        'cta_label' => ['fr' => 'Voir mes produits'],
                        'cta_route' => 'filament.lunar.resources.products.index',
                        'sort_order' => 20,
                    ],
                    [
                        'key' => 'prix_et_photos',
                        'title' => ['fr' => 'Ajouter le prix et les photos'],
                        'description' => ['fr' => 'Toujours sur la fiche du bonbon : l\'onglet « Tarification » fixe le prix, l\'onglet « Médias » accueille les photos.'],
                        'icon' => 'heroicon-o-photo',
                        'cta_label' => ['fr' => 'Voir mes produits'],
                        'cta_route' => 'filament.lunar.resources.products.index',
                        'sort_order' => 30,
                    ],
                    [
                        'key' => 'creer_avec_ia',
                        'title' => ['fr' => 'Essayer la création assistée par IA'],
                        'description' => ['fr' => 'Une photo du bonbon suffit : l\'IA propose un nom, une description et les ingrédients à valider.'],
                        'icon' => 'heroicon-o-sparkles',
                        'cta_label' => ['fr' => 'Créer avec IA'],
                        'cta_route' => 'filament.lunar.pages.creer-avec-ia',
                        'is_required' => false,
                        'sort_order' => 40,
                    ],
                ],
            ],
            [
                'key' => 'confiserie',
                'title' => ['fr' => 'Paramétrer la confiserie'],
                'description' => ['fr' => 'Types, allergènes, panneaux et stock : ce qui fait tourner le rayon au quotidien.'],
                'icon' => 'heroicon-o-squares-2x2',
                'sort_order' => 20,
                'steps' => [
                    [
                        'key' => 'types_de_bonbon',
                        'title' => ['fr' => 'Définir les types de bonbon'],
                        'description' => ['fr' => 'Chaque type a sa couleur d\'étiquette (acidulé, chocolat, sans sucre…). Créez ou ajustez-les avant d\'assigner un type aux bonbons.'],
                        'icon' => 'heroicon-o-tag',
                        'cta_label' => ['fr' => 'Gérer les types'],
                        'cta_route' => 'filament.lunar.resources.candy-types.index',
                        'sort_order' => 10,
                    ],
                    [
                        'key' => 'allergenes',
                        'title' => ['fr' => 'Tenir la liste des allergènes à jour'],
                        'description' => ['fr' => 'La liste des allergènes est commune à tous les bonbons. Sur chaque fiche produit, cochez ensuite « Contient » / « Peut contenir ».'],
                        'icon' => 'heroicon-o-exclamation-triangle',
                        'cta_label' => ['fr' => 'Gérer les allergènes'],
                        'cta_route' => 'filament.lunar.resources.allergens.index',
                        'sort_order' => 20,
                    ],
                    [
                        'key' => 'panneau',
                        'title' => ['fr' => 'Créer un panneau et y placer les bonbons'],
                        'description' => ['fr' => 'Un panneau représente un présentoir en magasin. Placez-y les bonbons puis imprimez les étiquettes depuis la liste des panneaux.'],
                        'icon' => 'heroicon-o-squares-2x2',
                        'cta_label' => ['fr' => 'Créer un panneau'],
                        'cta_route' => 'filament.lunar.resources.panels.create',
                        'sort_order' => 30,
                    ],
                    [
                        'key' => 'stock',
                        'title' => ['fr' => 'Suivre le stock au quotidien'],
                        'description' => ['fr' => 'Depuis la grille photo, enregistrez chaque entrée ou sortie (en kg ou en cartons) directement sur le bonbon concerné.'],
                        'icon' => 'heroicon-o-archive-box',
                        'cta_label' => ['fr' => 'Ouvrir la gestion du stock'],
                        'cta_route' => 'filament.lunar.pages.stock',
                        'sort_order' => 40,
                    ],
                    [
                        'key' => 'historique_stock',
                        'title' => ['fr' => 'Consulter l\'historique des mouvements'],
                        'description' => ['fr' => 'Chaque entrée, sortie et correction de stock est tracée : utile pour retrouver l\'origine d\'un écart.'],
                        'icon' => 'heroicon-o-clipboard-document-list',
                        'cta_label' => ['fr' => 'Voir l\'historique'],
                        'cta_route' => 'filament.lunar.resources.stock-movements.index',
                        'is_required' => false,
                        'sort_order' => 50,
                    ],
                ],
            ],
        ];

        foreach ($flows as $flow) {
            $steps = $flow['steps'];
            unset($flow['steps']);

            $flowModel = Onboarding::flowModel()::updateOrCreate(['key' => $flow['key']], $flow);

            foreach ($steps as $step) {
                $flowModel->steps()->updateOrCreate(['flow_id' => $flowModel->id, 'key' => $step['key']], $step);
            }
        }
    }
}
