<?php

namespace Modules\Ia\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Réglages IA saisis depuis le backoffice (ligne unique). La clé Gemini n'est plus seulement dans le
 * .env : elle prend le pas ici si elle est renseignée, sinon GeminiClient retombe sur GEMINI_API_KEY.
 */
class IaSetting extends Model
{
    protected $table = 'ia_settings';

    protected $fillable = ['gemini_api_key', 'gemini_model'];

    protected function casts(): array
    {
        return [
            'gemini_api_key' => 'encrypted',
        ];
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate(['id' => 1]);
    }
}
