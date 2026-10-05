<?php

namespace Modules\Ia\Support;

use InvalidArgumentException;
use Modules\Ia\Filament\Pages\IaSettings;

/**
 * Registre des fonctions IA ({@see AiFeature}) : chaque module y enregistre les siennes depuis son service
 * provider (après avoir vérifié que le module Ia est activé), et {@see IaSettings} en affiche les réglages.
 */
class AiFeatures
{
    /** @var array<string, AiFeature> */
    private array $features = [];

    public function register(AiFeature $feature): void
    {
        $this->features[$feature->key] = $feature;
    }

    public function get(string $key): AiFeature
    {
        return $this->features[$key] ?? throw new InvalidArgumentException("Fonction IA non enregistrée : {$key}");
    }

    /** @return array<string, AiFeature> */
    public function all(): array
    {
        return $this->features;
    }
}
