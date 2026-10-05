<?php

namespace Modules\Ia\Support;

use InvalidArgumentException;
use Modules\Ia\Models\IaSetting;

/**
 * Une fonction IA (réécriture des ingrédients, lecture d'une photo…) et ce qui s'y règle depuis Réglages IA :
 * sa température et ses sections de prompt. Le module Ia ne fournit que ce cadre ; chaque module déclare ses
 * propres fonctions et les enregistre dans {@see AiFeatures} depuis son service provider.
 *
 * Les défauts vivent dans le code ; seules les valeurs modifiées sont gardées en base (`ia_settings.features`),
 * pour qu'une amélioration d'un défaut profite aux fonctions qui ne l'ont pas surchargé.
 */
final class AiFeature
{
    private const PLACEHOLDER = '/\{\{\s*([a-z0-9_]+)\s*\}\}/';

    /**
     * @param  array<int, AiPromptSection>  $sections  dans l'ordre d'affichage
     * @param  array<string, string>  $variables  variables fournies à l'exécution : nom => description
     */
    public function __construct(
        public readonly string $key,
        public readonly string $module,
        public readonly string $label,
        public readonly string $description,
        public readonly array $sections,
        public readonly array $variables = [],
        public readonly float $temperature = 0.2,
    ) {}

    public function section(string $key): ?AiPromptSection
    {
        foreach ($this->sections as $section) {
            if ($section->key === $key) {
                return $section;
            }
        }

        return null;
    }

    public function temperature(): float
    {
        $override = $this->overrides()['temperature'] ?? null;

        return is_numeric($override) ? (float) $override : $this->temperature;
    }

    /** Texte en vigueur de la section, avant insertion des variables. */
    public function text(string $sectionKey): string
    {
        $section = $this->section($sectionKey) ?? throw new InvalidArgumentException("Section IA inconnue : {$this->key}.{$sectionKey}");

        return $this->overrides()['sections'][$sectionKey] ?? $section->default;
    }

    /**
     * Texte final de la section : chaque `{{nom}}` est remplacé par la variable d'exécution du même nom, sinon
     * par la section du même nom (rendue à son tour). Un `{{nom}}` inconnu est laissé tel quel ; une section
     * qui s'insère elle-même (directement ou non) est ignorée plutôt que de boucler.
     *
     * @param  array<string, string>  $variables
     */
    public function render(string $sectionKey, array $variables = []): string
    {
        return $this->renderSection($sectionKey, $variables, $this->overrides()['sections'] ?? [], []);
    }

    /**
     * Les `{{nom}}` du texte qui ne désignent ni une variable ni une autre section, pour la validation.
     *
     * @return array<int, string>
     */
    public function unknownPlaceholders(string $sectionKey, string $text): array
    {
        preg_match_all(self::PLACEHOLDER, $text, $matches);

        return array_values(array_unique(array_filter($matches[1], fn (string $name) => $name === $sectionKey
            || (! array_key_exists($name, $this->variables) && $this->section($name) === null))));
    }

    /** @return array<string, string> nom => description, pour l'aide affichée sous chaque section */
    public function placeholders(string $exceptSectionKey): array
    {
        $sections = [];
        foreach ($this->sections as $section) {
            if ($section->key !== $exceptSectionKey) {
                $sections[$section->key] = $section->label;
            }
        }

        return [...$this->variables, ...$sections];
    }

    /** @return array{temperature?: float|int|string|null, sections?: array<string, string>} */
    private function overrides(): array
    {
        return IaSetting::current()->features[$this->key] ?? [];
    }

    /**
     * @param  array<string, string>  $variables
     * @param  array<string, string>  $overrides
     * @param  array<int, string>  $stack
     */
    private function renderSection(string $sectionKey, array $variables, array $overrides, array $stack): string
    {
        $section = $this->section($sectionKey) ?? throw new InvalidArgumentException("Section IA inconnue : {$this->key}.{$sectionKey}");
        $text = $overrides[$sectionKey] ?? $section->default;

        if ($section->isReplacementList) {
            return AiReplacements::toPrompt($text);
        }

        $stack[] = $sectionKey;

        return (string) preg_replace_callback(self::PLACEHOLDER, function (array $match) use ($variables, $overrides, $stack) {
            $name = $match[1];

            if (array_key_exists($name, $variables)) {
                return $variables[$name];
            }

            if ($this->section($name) === null) {
                return $match[0];
            }

            return in_array($name, $stack, true) ? '' : $this->renderSection($name, $variables, $overrides, $stack);
        }, $text);
    }
}
