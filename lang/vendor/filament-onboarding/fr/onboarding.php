<?php

declare(strict_types = 1);

return [

    'checklist' => [
        'mark_done'             => 'Marquer comme fait',
        'start_tour'            => 'Démarrer la visite',
        'go'                    => 'Aller',
        'skip'                  => 'Passer',
        'close'                 => 'Fermer',
        'dismiss'               => 'Masquer',
        'done'                  => 'Terminé',
        'completed_title'       => 'Tout est prêt',
        'completed_description' => 'Toutes les étapes sont terminées. Bienvenue à bord.',
        'footer_note'           => 'Vous pouvez reprendre plus tard.',
    ],

    'welcome' => [
        'begin' => 'Commencer',
        'later' => 'Plus tard',
        'never' => 'Ne plus afficher',
        'steps' => '{0}Rien pour le moment|{1}1 étape, à votre rythme|[2,*]:count étapes, à votre rythme',
        'back'  => 'Réactiver le guide',
        'off'   => 'Vous avez désactivé le guide. La checklist et l\'anneau de progression ont disparu — mais cette page reste accessible.',
    ],
    'tour' => [
        'blocked'  => 'L\'écran n\'a pas encore changé. Si un champ est requis, remplissez-le puis cliquez de nouveau sur suivant — la visite vous suit.',
        'start'    => 'Voir le tutoriel',
        'choose'   => 'Quel tutoriel voulez-vous suivre ?',
        'begin'    => 'Démarrer',
        'waiting'  => 'En attente que cet élément apparaisse sur la page — continuez le formulaire, la visite vous suit.',
        'skip'     => 'Passer',
        'previous' => 'Précédent',
        'next'     => 'Suivant',
        'finish'   => 'Terminer',
    ],

    'page' => [
        'collapse' => 'Replier ce parcours',
        'expand'   => 'Ouvrir ce parcours',
        'meta'     => [
            'completed' => ':count sur :total terminées',
            'remaining' => '{1}1 restante|[2,*]:count restantes',
            'skipped'   => '{1}1 ignorée|[2,*]:count ignorées',
        ],
        'open_image'           => 'Ouvrir l\'image : :title',
        'title'                => 'Prise en main',
        'subheading'           => 'Où vous en êtes, et ce qu\'il reste à faire.',
        'next'                 => 'À suivre',
        'hidden'               => 'Masqué',
        'restore'              => 'Réafficher',
        'undo'                 => 'Annuler',
        'replay_tour'          => 'Revoir',
        'replay_video'         => 'Revoir',
        'open_again'           => 'Rouvrir',
        'restart'              => 'Recommencer',
        'restarted'            => 'Parcours recommencé',
        'restarted_reinstated' => ':count étape(s) revenue(s) directement à l\'état terminé : le travail derrière est déjà fait.',
        'restart_confirm'      => 'Recommencer ce parcours ? Ce que vous avez coché, ignoré ou visionné sera réinitialisé.',
        'restart_note'         => 'Les étapes qui se terminent automatiquement reviennent directement à l\'état terminé : elles répondent à l\'application, pas à ce bouton.',

        'stats' => [
            'completed' => 'Terminées',
            'remaining' => 'Restantes',
            'skipped'   => 'Ignorées',
        ],

        'status' => [
            'completed' => 'Terminé',
            'skipped'   => 'Ignoré',
            'next'      => 'À suivre',
            'pending'   => 'À faire',
        ],

        'completed_at'       => 'Terminé :time',
        'tour_progress'      => 'Étape :reached sur :total',
        'awaiting_condition' => 'Se termine automatiquement',
        'empty_title'        => 'Rien à découvrir',
        'empty_description'  => 'Aucun parcours ne vous est proposé pour le moment.',
    ],

    'media' => [
        'watch'   => 'Regarder',
        'resume'  => 'Reprendre',
        'watched' => 'visionné',
    ],

    'enums' => [

        'condition_type' => [
            'aggregate' => [
                'label'       => 'Compte quelque chose qu\'il possède',
                'description' => 'Clients, factures, serveurs — tout ce qui lui appartient. « Au moins un client. »',
            ],
            'attribute' => [
                'label'       => 'Pose une question sur lui',
                'description' => 'Une colonne de l\'utilisateur lui-même. « Son email est vérifié. »',
            ],
        ],

        'condition_operator' => [
            'equals'                => 'est égal à',
            'not_equals'            => 'est différent de',
            'greater_than'          => 'est supérieur à',
            'greater_than_or_equal' => 'est au moins',
            'less_than'             => 'est inférieur à',
            'less_than_or_equal'    => 'est au plus',
            'contains'              => 'contient',
            'is_set'                => 'est renseigné',
            'is_empty'              => 'est vide',
        ],
        'step_type' => [
            'task' => 'Tâche',
            'tour' => 'Visite guidée',
        ],

        'media_type' => [
            'none'  => 'Aucun',
            'image' => 'Image',
            'video' => 'Vidéo',
        ],

        'media_source' => [
            'upload' => [
                'label'       => 'Import',
                'description' => 'Stocké sur le disque configuré (S3, R2, local).',
            ],
            'url' => [
                'label'       => 'URL directe',
                'description' => 'Un fichier hébergé ailleurs.',
            ],
            'youtube' => [
                'label'       => 'YouTube',
                'description' => 'Le temps de visionnage est suivi.',
            ],
            'vimeo' => [
                'label'       => 'Vimeo',
                'description' => 'Le temps de visionnage est suivi.',
            ],
            'embed' => [
                'label'       => 'Autre fournisseur (iframe)',
                'description' => 'Se lit, mais le temps de visionnage ne peut pas être suivi.',
            ],
        ],

        'modal_position' => [
            'center'       => 'Centre',
            'top'          => 'Haut',
            'bottom'       => 'Bas',
            'top-left'     => 'Haut gauche',
            'top-right'    => 'Haut droite',
            'bottom-left'  => 'Bas gauche',
            'bottom-right' => 'Bas droite',
        ],

        'completion_mode' => [
            'manual' => [
                'label'       => 'Manuel',
                'description' => 'L\'utilisateur la coche lui-même.',
            ],
            'condition' => [
                'label'       => 'Condition',
                'description' => 'Se termine automatiquement quand une vérification enregistrée est validée.',
            ],
            'visit' => [
                'label'       => 'Visite de page',
                'description' => 'Se termine quand l\'utilisateur atteint une URL.',
            ],
            'video' => [
                'label'       => 'Visionnage de la vidéo',
                'description' => 'Se termine une fois qu\'une part suffisante de la vidéo de l\'étape a été visionnée.',
            ],
            'programmatic' => [
                'label'       => 'Programmatique',
                'description' => 'Seul le code applicatif la termine.',
            ],
        ],
    ],

    'resource' => [
        'singular'   => 'Parcours d\'onboarding',
        'plural'     => 'Onboarding',
        'all_panels' => 'Tous les panneaux',

        'placeholders' => [
            'flow_title'       => 'Prise en main de …',
            'flow_description' => 'Une ligne sur ce que l\'utilisateur en retire.',
            'step_title'       => 'Connecter votre premier serveur',
            'step_description' => 'Une ou deux lignes. Expliquez pourquoi, pas seulement quoi.',
        ],

        'no_steps_warning' => 'Ce parcours n\'a aucune étape, donc personne ne le voit.',

        'empty' => [
            'heading'     => 'Aucun parcours pour le moment',
            'description' => 'Un parcours est une checklist que vos utilisateurs suivent. Créez-en un et ajoutez ses étapes.',
        ],

        'sections' => [
            'overview'                => 'Vue d\'ensemble',
            'publishing_description'  => 'Qui voit ce parcours, où, et dans quel ordre.',
            'appearance_description'  => 'Comment le parcours est annoncé dans la checklist.',
            'content_description'     => 'Ce que l\'utilisateur lit, dans chaque langue que vous gérez.',
            'publishing'              => 'Publication',
            'appearance'              => 'Apparence',
            'rules'                   => 'Règles',
            'destination'             => 'Destination',
            'destination_description' => 'Où le bouton emmène l\'utilisateur. Choisissez une page du panneau ; une URL sert de repli.',
            'content'                 => 'Contenu',
            'settings'                => 'Paramètres',
            'behaviour'               => 'Comportement',
            'media'                   => 'Média',
            'media_description'       => 'Une image à montrer, ou une vidéo à regarder. S\'ouvre dans une fenêtre au-dessus du panneau.',
            'tour'                    => 'Visite',
            'tour_description'        => 'Les éléments mis en avant par cette visite, dans l\'ordre.',
        ],

        'steps' => [
            'title'             => 'Étapes',
            'empty_heading'     => 'Aucune étape pour le moment',
            'empty_description' => 'Ajoutez la première étape. Sans étape, ce parcours n\'apparaît pour personne.',
        ],

        'fields' => [
            'title'                 => 'Titre',
            'description'           => 'Description',
            'key'                   => 'Clé',
            'key_helper'            => 'Utilisée dans le code. Ne peut pas être modifiée sans casser la progression existante.',
            'step_key_helper'       => 'Unique au sein du parcours. Utilisée par Onboarding::for($user)->complete(...).',
            'panel'                 => 'Panneau',
            'panel_helper'          => 'Laissez vide pour afficher le parcours dans tous les panneaux.',
            'icon'                  => 'Icône',
            'color'                 => 'Couleur',
            'sort_order'            => 'Ordre',
            'is_active'             => 'Actif',
            'is_dismissible'        => 'Peut être masqué',
            'is_dismissible_helper' => 'Permet à l\'utilisateur de masquer la checklist définitivement.',
            'steps'                 => 'Étapes',
            'updated_at'            => 'Mis à jour le',
            'type'                  => 'Type',
            'completion_mode'       => 'Terminée par',
            'condition'             => 'Condition',
            'condition_helper'      => 'Enregistrée par l\'application. Les étapes déjà remplies reviennent terminées.',
            'condition_none'        => 'Aucune condition pour le moment — créez-en une dans Onboarding → Conditions (ou lancez php artisan make:onboarding-condition), elle apparaîtra ici.',
            'visit_url'             => 'URL',
            'visit_url_helper'      => 'Prend en charge le joker * : /app/*/servers/create',
            'cta_label'             => 'Libellé du bouton',
            'cta_url'               => 'URL personnalisée',
            'cta_url_helper'        => 'Seulement quand la destination n\'est pas dans la liste. {tenant} est complété automatiquement.',
            'cta_route'             => 'Destination',
            'cta_route_helper'      => 'Une page du panneau. Préférée à une URL : elle survit à un slug renommé.',
            'is_required'           => 'Obligatoire',
            'is_required_helper'    => 'Les étapes optionnelles peuvent être ignorées.',
            'visibility'            => 'Visible quand',
            'visibility_helper'     => 'Seuls les sujets qui valident cette condition la voient. Utilisez-la pour réserver une étape à un forfait ou une fonctionnalité.',
            'visibility_everyone'   => 'Tout le monde',

            'media_type'             => 'Média',
            'media_source'           => 'Source',
            'media_file'             => 'Fichier',
            'media_url'              => 'URL',
            'media_url_helper'       => 'Collez le lien tel quel : watch, share ou URL courte fonctionnent.',
            'media_caption'          => 'Légende',
            'modal_position'         => 'Position de la fenêtre',
            'modal_position_helper'  => 'Une fenêtre dans un coin laisse la page utilisable derrière elle.',
            'modal_position_default' => 'Par défaut du panneau',
            'video_threshold'        => 'Considérée comme regardée à',
            'video_threshold_helper' => 'Pourcentage qui termine l\'étape, quand elle se termine par visionnage.',
            'is_active_helper'       => 'Les parcours inactifs disparaissent de tous les panneaux.',
            'sort_order_helper'      => 'Le plus petit passe en premier.',
            'type_helper'            => 'Une tâche se coche ; une visite se parcourt.',
            'completion_mode_helper' => 'Ce qui marque cette étape comme terminée.',
        ],

        'tour' => [
            'optional'        => 'Ignorer si absent',
            'optional_helper' => 'Pour un élément qui peut ne pas encore exister — une étiquette sur un tableau vide, un graphique sans données. La visite continue au lieu d\'attendre.',
            'advance'         => 'Avancer avec',
            'advance_helper'  => 'Le contrôle qui amène l\'application à cette étape — le bouton suivant d\'un assistant, un onglet. Cliqué quand le sujet avance et que l\'élément n\'est pas encore à l\'écran.',
            'add'             => 'Ajouter une étape',
            'target'          => 'Élément à mettre en avant',
            'target_helper'   => 'Lu directement depuis le panneau — les champs du formulaire de cette page, ses boutons, son tableau. Certains formulaires ne peuvent pas être lus de l\'extérieur (ceux qui dépendent de l\'enregistrement en cours d\'édition, ou de qui le consulte) : leurs champs ne seront pas listés, un sélecteur CSS est alors la solution.',
            'targets'         => [
                'on_this_page' => 'Sur cette page',
                'widgets'      => 'Widgets',
                'advanced'     => 'Avancé',
                'custom'       => 'Un sélecteur CSS personnalisé…',
                'table'        => 'Le tableau',
                'search'       => 'Le champ de recherche',
                'submit'       => 'Le bouton d\'enregistrement',
                'column'       => 'Colonne : :label',
                'new_record'   => 'Le bouton « Nouveau :label »',
            ],
            'selector'        => 'Sélecteur CSS',
            'selector_helper' => 'L\'élément à mettre en avant, ex. [data-onboarding="create-server"]. Laissez vide si un widget est choisi.',
            'widget'          => 'Widget',
            'widget_helper'   => 'Un widget du panneau. Trouvé automatiquement sur la page — aucun sélecteur requis.',
            'placement'       => 'Placement',
            'placements'      => [
                'auto'   => 'Automatique',
                'top'    => 'Au-dessus',
                'bottom' => 'En dessous',
            ],
            'route'             => 'Page',
            'route_helper'      => 'Seulement quand cette étape se trouve ailleurs. La visite y navigue et continue.',
            'url'               => 'URL personnalisée',
            'url_helper'        => 'Seulement quand la page n\'est pas dans la liste.',
            'body'              => 'Texte',
            'visibility'        => 'Visible quand',
            'visibility_helper' => 'Laissez vide pour montrer cette étape à tout le monde. Une étape pointant vers une fonctionnalité absente du forfait ne mettrait rien en avant.',
        ],

        'targets' => [
            'resources'      => 'Resources',
            'pages'          => 'Pages',
            'tenant_profile' => 'Profil du tenant',
            'page_names'     => [
                'index'  => 'liste',
                'create' => 'création',
            ],
        ],
    ],

    'conditions' => [
        'singular'   => 'Condition',
        'plural'     => 'Conditions',
        'subheading' => 'Les questions que vos étapes peuvent poser sur quelqu\'un — écrites ici, pas dans le code.',
        'at_least'   => 'au moins :count',

        'sections' => [
            'question'             => 'La question',
            'question_description' => 'Ce qui doit être vrai chez quelqu\'un pour que l\'étape soit terminée.',
            'naming'               => 'Comment elle s\'appelle',
            'naming_description'   => 'Le nom que la personne qui écrit une étape choisit dans la liste déroulante.',
        ],

        'fields' => [
            'type'                     => 'Ce qu\'elle demande',
            'model'                    => 'Ce qui est compté',
            'model_helper'             => 'Ce qu\'ils doivent posséder : clients, factures, serveurs.',
            'minimum'                  => 'Au moins',
            'minimum_helper'           => 'Combien. Un, presque toujours.',
            'subject_column'           => 'Rattaché à eux via',
            'subject_column_helper'    => 'La colonne contenant l\'identifiant de la personne en cours d\'onboarding.',
            'scope_column'             => 'Et au tenant via',
            'scope_column_helper'      => 'Seulement quand le modèle est rattaché à un tenant. Laissez vide sinon.',
            'scope_column_none'        => 'Non rattaché à un tenant',
            'filters'                  => 'Seulement ceux où…',
            'filters_helper'           => 'Optionnel. Sans filtre, tous comptent.',
            'filters_attribute'        => 'Vrai quand…',
            'filters_attribute_helper' => 'Toutes ces conditions doivent être vraies.',
            'add_filter'               => 'Ajouter une règle',
            'column'                   => 'Champ',
            'operator'                 => 'Est',
            'value'                    => 'Valeur',
            'label'                    => 'Nom',
            'label_helper'             => 'Comment elle s\'appelle dans l\'éditeur d\'étape.',
            'key_helper'               => 'Comment les étapes s\'y réfèrent. Ne peut plus être modifiée une fois utilisée par des étapes.',
            'key_taken'                => 'La clé [:key] appartient à une condition enregistrée dans le code.',
            'is_active_helper'         => 'Une condition inactive ne passe jamais.',
            'question'                 => 'Demande',
        ],

        'placeholders' => [
            'label' => 'A ajouté un client',
        ],

        'empty' => [
            'heading'     => 'Aucune condition pour le moment',
            'description' => 'Une condition permet à une étape de se terminer d\'elle-même — y compris pour ceux qui ont fait l\'action il y a longtemps. Écrivez-en une ici, ou lancez php artisan make:onboarding-condition pour une question qu\'un formulaire ne peut pas poser.',
        ],
    ],

];
