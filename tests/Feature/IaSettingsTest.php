<?php

namespace Tests\Feature;

use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Lunar\Admin\Models\Staff;
use Modules\Ia\Filament\Pages\IaSettings;
use Modules\Ia\Models\IaSetting;
use Modules\Ia\Services\IngredientsAiWriter;
use Tests\TestCase;

class IaSettingsTest extends TestCase
{
    use RefreshDatabase;

    private const REPLACEMENTS = 'features.ingredients_writer.sections.remplacements';

    private function settingsPage(): mixed
    {
        return Livewire::actingAs(Staff::factory()->create(['admin' => true]), 'staff')->test(IaSettings::class);
    }

    public function test_the_save_button_of_a_field_saves_that_field_only(): void
    {
        $this->settingsPage()
            ->fillForm([
                self::REPLACEMENTS => "E330 => acide citrique\n# commentaire",
                'features.ingredients_writer.sections.role' => 'Rôle modifié mais pas enregistré.',
                'gemini_model' => 'modele-non-enregistre',
            ])
            ->callAction(TestAction::make('saveField')->schemaComponent(self::REPLACEMENTS, schema: 'form'))
            ->assertHasNoFormErrors();

        $setting = IaSetting::current();
        $this->assertSame(['ingredients_writer' => ['sections' => ['remplacements' => "E330 => acide citrique\n# commentaire"]]], $setting->features);
        $this->assertNull($setting->gemini_model);
    }

    public function test_saving_a_field_keeps_what_others_saved_since_the_page_was_opened(): void
    {
        $page = $this->settingsPage()->fillForm(['features.ingredients_writer.temperature' => '0.3']);

        // Someone else saves another field in the meantime, from their own page.
        IaSetting::current()->update(['gemini_model' => 'modele-collegue', 'features' => [
            'ingredients_writer' => ['sections' => ['role' => 'Rôle du collègue.']],
        ]]);

        $page->call('saveField', 'features.ingredients_writer.temperature');

        $setting = IaSetting::current()->fresh();
        $this->assertSame('modele-collegue', $setting->gemini_model);
        $this->assertSame(['ingredients_writer' => [
            'sections' => ['role' => 'Rôle du collègue.'],
            'temperature' => 0.3,
        ]], $setting->features);
    }

    public function test_a_field_emptied_or_reset_to_its_default_is_no_longer_stored(): void
    {
        IaSetting::current()->update(['features' => [
            'ingredients_writer' => ['sections' => ['role' => 'Rôle modifié.', 'regles_generales' => 'Règles modifiées.']],
        ]]);
        $defaultRole = IngredientsAiWriter::aiFeature()->section('role')->default;

        $this->settingsPage()
            ->fillForm([
                'features.ingredients_writer.sections.role' => $defaultRole,
                'features.ingredients_writer.sections.regles_generales' => '',
            ])
            ->call('saveField', 'features.ingredients_writer.sections.role')
            ->call('saveField', 'features.ingredients_writer.sections.regles_generales')
            // The emptied field shows the default text again, since that is what the AI now receives.
            ->assertFormSet(['features.ingredients_writer.sections.regles_generales' => IngredientsAiWriter::aiFeature()->section('regles_generales')->default]);

        $this->assertNull(IaSetting::current()->features);
    }

    public function test_a_replacement_line_without_separator_is_rejected(): void
    {
        $this->settingsPage()
            ->fillForm([self::REPLACEMENTS => "E330 => acide citrique\nE296 acide malique"])
            ->call('saveField', self::REPLACEMENTS)
            ->assertHasFormErrors([self::REPLACEMENTS]);

        $this->assertNull(IaSetting::current()->features);
    }

    public function test_an_unknown_or_self_insertion_in_a_prompt_section_is_rejected(): void
    {
        $this->settingsPage()
            ->fillForm([
                'features.ingredients_writer.sections.plan' => "{{role}}\n{{regles_inconnues}}",
                'features.ingredients_writer.sections.role' => 'Rôle {{role}}',
            ])
            ->call('saveField', 'features.ingredients_writer.sections.plan')
            ->call('saveField', 'features.ingredients_writer.sections.role')
            ->assertHasFormErrors([
                'features.ingredients_writer.sections.plan',
                'features.ingredients_writer.sections.role',
            ]);

        $this->assertNull(IaSetting::current()->features);
    }
}
