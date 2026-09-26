<?php

namespace App\Lunar\FieldTypes;

use App\Lunar\Forms\Components\TranslatedText as TranslatedTextComponent;
use Filament\Schemas\Components\Component;
use Lunar\Admin\Support\FieldTypes\TranslatedText as BaseTranslatedText;
use Lunar\Models\Attribute;

/**
 * Remplace le mappage standard de Lunar pour les attributs traduits, en ajoutant la clé `textarea` à la
 * configuration de l'attribut (à côté de `richtext` qui existe déjà) : voir App\Lunar\Forms\Components\TranslatedText.
 * Enregistré dans AppServiceProvider via AttributeData::registerFieldType().
 */
class TranslatedText extends BaseTranslatedText
{
    public static function getFilamentComponent(Attribute $attribute): Component
    {
        return TranslatedTextComponent::make($attribute->handle)
            ->optionRichtext((bool) $attribute->configuration->get('richtext'))
            ->optionTextarea((bool) $attribute->configuration->get('textarea'))
            ->when(filled($attribute->validation_rules), fn (TranslatedTextComponent $component) => $component->rules($attribute->validation_rules))
            ->required((bool) $attribute->required)
            ->helperText($attribute->translate('description'));
    }
}
