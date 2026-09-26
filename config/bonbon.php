<?php

return [

    /*
    | Langues du frontend et des champs traduisibles (nom, description, ingrédients, allergènes).
    | La première est la langue par défaut.
    */
    'locales' => [
        'fr' => 'Français',
        'nl' => 'Nederlands',
        'en' => 'English',
    ],

    /*
    | Invitations temporaires pour accéder au frontend.
    */
    'invitations' => [
        'default_days' => 7,
        'session_key' => 'invitation_id',
    ],

    /*
    | Google Gemini (offre gratuite) : création assistée de produits/allergènes et recherche par photo.
    */
    'gemini' => [
        'key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-3.6-flash'),
        'endpoint' => env('GEMINI_ENDPOINT', 'https://generativelanguage.googleapis.com/v1beta'),
        'timeout' => 60,
        'image_max_side' => 1280,
    ],

];
