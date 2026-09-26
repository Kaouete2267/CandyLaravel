<?php

namespace Modules\Onboarding;

use Filament\Contracts\Plugin;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Modules\Onboarding\Policies\OnboardingConditionPolicy;
use Modules\Onboarding\Policies\OnboardingFlowPolicy;
use Modules\Onboarding\Policies\OnboardingStepPolicy;
use Wallacemartinss\FilamentOnboarding\FilamentOnboardingPlugin;

class OnboardingServiceProvider extends ServiceProvider
{
    public static function filamentPlugin(): Plugin
    {
        return FilamentOnboardingPlugin::make()
            ->launcher()
            ->tours()
            ->progressPage()
            ->manageFlows()
            ->navigationGroup('Aide')
            ->navigationIcon('heroicon-o-light-bulb')
            ->navigationSort(90);
    }

    public function register(): void {}

    /**
     * Boote après le provider du package (auto-découvert), donc ces policies
     * remplacent celles par défaut du package (voir config/filament-onboarding.php).
     * Seul le staff admin (Staff::admin) peut créer/éditer les parcours d'onboarding ;
     * le reste du staff garde le checklist, les tours guidés et la page de progression.
     */
    public function boot(): void
    {
        Gate::policy(config('filament-onboarding.models.flow'), OnboardingFlowPolicy::class);
        Gate::policy(config('filament-onboarding.models.step'), OnboardingStepPolicy::class);
        Gate::policy(config('filament-onboarding.models.condition'), OnboardingConditionPolicy::class);
    }
}
