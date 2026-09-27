<?php

// Permissions créées par les modules (voir Modules\Support\StaffPermissions), affichées dans
// Paramètres › Personnel › Contrôle d'accès.
return [

    'permissions.dashboard.label' => 'Tableau de bord',
    'permissions.dashboard.description' => 'Accès au tableau de bord (statistiques de ventes). Sans lui, la connexion mène à la première page accessible du menu.',

    'permissions.settings:manage-taxes.label' => 'Taxes',
    'permissions.settings:manage-taxes.description' => 'Gérer les classes, zones et taux de taxe.',
    'permissions.settings:manage-ai.label' => 'Réglages IA',
    'permissions.settings:manage-ai.description' => 'Modifier la clé API et le modèle Gemini utilisés par les fonctionnalités IA.',

    'permissions.confiserie.label' => 'Confiserie',
    'permissions.confiserie.description' => 'Accès à la section Confiserie ; chaque page s\'active ensuite séparément.',
    'permissions.confiserie:create-product-with-ai.label' => 'Créer avec l\'IA',
    'permissions.confiserie:create-product-with-ai.description' => 'Créer une fiche produit à partir de photos analysées par l\'IA.',
    'permissions.confiserie:manage-panels.label' => 'Panneaux',
    'permissions.confiserie:manage-panels.description' => 'Gérer les panneaux et leurs étiquettes.',
    'permissions.confiserie:manage-candy-types.label' => 'Types de bonbon',
    'permissions.confiserie:manage-candy-types.description' => 'Gérer les types de bonbon.',
    'permissions.confiserie:manage-stock.label' => 'Stock',
    'permissions.confiserie:manage-stock.description' => 'Entrer, sortir et inventorier le stock.',
    'permissions.confiserie:view-stock-history.label' => 'Historique du stock',
    'permissions.confiserie:view-stock-history.description' => 'Consulter les mouvements de stock.',
    'permissions.confiserie:manage-allergens.label' => 'Allergènes',
    'permissions.confiserie:manage-allergens.description' => 'Gérer la liste des allergènes.',
    'permissions.confiserie:manage-ingredient-terms.label' => 'Glossaire d\'ingrédients',
    'permissions.confiserie:manage-ingredient-terms.description' => 'Gérer le glossaire d\'ingrédients utilisé par l\'IA.',
    'permissions.confiserie:manage-invitations.label' => 'Invitations',
    'permissions.confiserie:manage-invitations.description' => 'Gérer les liens d\'invitation donnant accès au site vitrine.',

];
