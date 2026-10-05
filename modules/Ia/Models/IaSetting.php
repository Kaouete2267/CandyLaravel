<?php

namespace Modules\Ia\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Ia\Support\AiFeature;

/**
 * Réglages IA saisis depuis le backoffice (ligne unique). La clé Gemini n'est plus seulement dans le
 * .env : elle prend le pas ici si elle est renseignée, sinon GeminiClient retombe sur GEMINI_API_KEY.
 * `features` garde les surcharges de chaque fonction IA ({@see AiFeature}) :
 * [clé de la fonction => ['temperature' => ?float, 'sections' => [clé de section => texte]]].
 */
class IaSetting extends Model
{
    protected $table = 'ia_settings';

    protected $fillable = ['gemini_api_key', 'gemini_model', 'features'];

    protected function casts(): array
    {
        return [
            'gemini_api_key' => 'encrypted',
            'features' => 'array',
        ];
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate(['id' => 1]);
    }
}
