<?php

namespace Modules\Support;

class Modules
{
    /** Le module est-il activé dans config/modules.php ? (ex. Modules::enabled('Ia')) */
    public static function enabled(string $name): bool
    {
        return collect(config('modules.enabled', []))
            ->contains(fn (string $provider) => str_starts_with($provider, "Modules\\{$name}\\"));
    }
}
