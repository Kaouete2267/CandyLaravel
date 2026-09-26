<?php

namespace Modules\Support;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;

class TranslatedFields
{
    /**
     * Un onglet par langue (config bonbon.locales), chacun avec les champs demandés (`nom.fr`, `nom.nl`, …).
     * Seule la langue par défaut est obligatoire quand `required` est demandé.
     *
     * @param  array<string, array{label: string, type?: 'input'|'textarea', required?: bool}>  $fields
     */
    public static function tabs(array $fields, string $label = 'Traductions'): Tabs
    {
        $locales = config('bonbon.locales');
        $default = array_key_first($locales);

        $tabs = [];
        foreach ($locales as $locale => $language) {
            $components = [];

            foreach ($fields as $name => $definition) {
                $component = ($definition['type'] ?? 'input') === 'textarea'
                    ? Textarea::make("{$name}.{$locale}")->rows(3)
                    : TextInput::make("{$name}.{$locale}")->maxLength(255);

                $component->label($definition['label']);

                if (($definition['required'] ?? false) && $locale === $default) {
                    $component->required();
                }

                $components[] = $component;
            }

            $tabs[] = Tab::make($language)->schema($components);
        }

        return Tabs::make($label)->tabs($tabs)->columnSpanFull();
    }
}
