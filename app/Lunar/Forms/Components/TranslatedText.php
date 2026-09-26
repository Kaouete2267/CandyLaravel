<?php

namespace App\Lunar\Forms\Components;

use Lunar\Admin\Support\Forms\Components\TranslatedText as BaseTranslatedText;

/**
 * Ajoute un troisième rendu au champ traduit de Lunar (qui ne propose nativement qu'une ligne ou un
 * RichEditor) : un textarea texte brut multi-lignes. Nécessaire pour les ingrédients, dont le surlignage
 * des allergènes (Modules\Allergenes\Support\AllergenTerms::highlight) traite le texte caractère par
 * caractère et ne comprend pas le HTML que produirait un RichEditor.
 */
class TranslatedText extends BaseTranslatedText
{
    public bool $optionTextarea = false;

    protected int $textareaRows = 4;

    public function optionTextarea(bool $condition = true): static
    {
        $this->optionTextarea = $condition;

        return $this;
    }

    public function textareaRows(int $rows): static
    {
        $this->textareaRows = $rows;

        return $this;
    }

    public function prepareChildComponents()
    {
        if (! $this->optionTextarea) {
            parent::prepareChildComponents();

            return;
        }

        $this->components = collect(
            $this->getLanguages()->map(fn ($lang) => $this->getTranslatedTextareaComponent($lang->code))
        );
    }

    protected function getTranslatedTextareaComponent(string $langCode): TranslatedTextarea
    {
        return TranslatedTextarea::make($langCode)
            ->statePath($langCode)
            ->rows($this->textareaRows)
            ->maxLength($this->maxLength);
    }
}
