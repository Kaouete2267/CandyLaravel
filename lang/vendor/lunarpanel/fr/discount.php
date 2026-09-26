<?php

return [

    'form' => [
        'stop' => [
            'helper_text' => 'Quand cette réduction s\'applique, toute réduction de priorité inférieure sera ignorée. Attribuez des priorités différentes aux réductions pour contrôler leur ordre d\'application.',
        ],
    ],

    'table' => [
        'created_at' => [
            'label' => 'Créé le',
        ],
        'coupon' => [
            'label' => 'Coupon',
        ],
    ],

    'relationmanagers' => [
        'customers' => [
            'title' => 'Clients',
            'description' => 'Sélectionnez les clients auxquels cette réduction doit être limitée.',
            'actions' => [
                'attach' => [
                    'label' => 'Associer un client',
                ],
            ],
            'table' => [
                'name' => [
                    'label' => 'Nom',
                ],
            ],
        ],
        'collection_conditions' => [
            'title' => 'Conditions de collection',
            'description' => 'Sélectionnez les conditions de collection requises pour que la réduction s\'applique.',
            'actions' => [
                'attach' => [
                    'label' => 'Ajouter une condition',
                ],
            ],
            'table' => [
                'name' => [
                    'label' => 'Nom',
                ],
            ],
        ],
    ],

];
