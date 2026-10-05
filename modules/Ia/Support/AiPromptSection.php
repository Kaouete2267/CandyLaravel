<?php

namespace Modules\Ia\Support;

/**
 * Un morceau de prompt réglable depuis Réglages IA. Son texte peut insérer, via `{{nom}}`, une autre section
 * de la même fonction ({@see AiFeature}) ou une variable fournie à l'exécution : c'est ce qui permet à une
 * section « plan » d'assembler les autres dans l'ordre voulu.
 *
 * Une section « liste de remplacements » ne contient pas de prompt mais une règle par ligne
 * (« terme => remplacement ») : elle est insérée dans le prompt mise en forme par {@see AiReplacements}.
 */
final class AiPromptSection
{
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly string $default,
        public readonly ?string $helperText = null,
        public readonly bool $isReplacementList = false,
    ) {}

    public static function replacements(string $key, string $label, ?string $helperText = null): self
    {
        return new self($key, $label, '', $helperText, isReplacementList: true);
    }
}
