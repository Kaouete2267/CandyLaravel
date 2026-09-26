<?php

namespace App\Lunar\Forms\Components;

use Filament\Forms\Components\Textarea;

class TranslatedTextarea extends Textarea
{
    public function setUp(): void
    {
        parent::setUp();

        $this->hiddenLabel();
    }
}
