<?php

namespace Modules\Ia\Filament\FieldTypes;

use App\Lunar\FieldTypes\TranslatedText;
use Filament\Actions\Action;
use Filament\Forms\Components\Field;
use Filament\Schemas\Components\Component;
use Filament\Support\Icons\Heroicon;
use Lunar\Admin\Filament\Resources\ProductResource\Pages\EditProduct;
use Lunar\Models\Attribute;
use Lunar\Models\Product;
use Modules\Ia\Filament\Actions\UpdateIngredientsWithAi;
use Modules\Ia\Filament\Extensions\ProductIngredientsAiExtension;

/**
 * Prolonge le type de champ « texte traduit » de l'application ({@see TranslatedText}, lui-même substitué à
 * celui de Lunar) uniquement pour y greffer, sur l'attribut produit
 * `ingredients`, une copie du bouton « Mettre à jour avec l'IA ». Passer par ici plutôt que par
 * {@see ProductIngredientsAiExtension::extendForm()} est obligatoire : Lunar reconstruit les champs
 * d'attributs après les extensions, toute modification faite depuis celles-ci serait perdue.
 */
class TranslatedTextWithIngredientsAi extends TranslatedText
{
    public static function getFilamentComponent(Attribute $attribute): Component
    {
        /** @var Field $component */
        $component = parent::getFilamentComponent($attribute);

        if ($attribute->handle !== 'ingredients' || $attribute->attribute_type !== Product::morphName()) {
            return $component;
        }

        return $component->hintAction(self::updateIngredientsShortcut());
    }

    /**
     * Ouvre simplement l'action de la section IA ({@see UpdateIngredientsWithAi}), plutôt qu'une seconde
     * instance dont les `Get`/`Set` seraient relatifs à ce champ au lieu de la page.
     */
    private static function updateIngredientsShortcut(): Action
    {
        return Action::make('updateIngredientsWithAiShortcut')
            ->label('Mettre à jour avec l\'IA')
            ->icon(Heroicon::Sparkles)
            ->visible(fn (Action $action) => $action->getLivewire() instanceof EditProduct)
            ->action(fn (Action $action) => $action->getLivewire()?->mountAction('updateIngredientsWithAi', context: [
                'schemaComponent' => 'form.ingredientsAiActions',
            ]));
    }
}
