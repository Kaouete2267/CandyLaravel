<?php

namespace Tests\Feature;

use App\Http\Middleware\DropContentLengthWhenPhpCompresses;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class DropContentLengthWhenPhpCompressesTest extends TestCase
{
    private function livewireScriptUri(): string
    {
        return collect(Route::getRoutes()->getRoutes())
            ->first(fn (RoutingRoute $route) => preg_match('#^livewire[^/]*/livewire(\.min)?\.js$#', $route->uri()))
            ->uri();
    }

    public function test_livewire_script_has_no_content_length_when_php_compresses_output(): void
    {
        $this->app->instance(DropContentLengthWhenPhpCompresses::class, new DropContentLengthWhenPhpCompresses(phpCompressesOutput: true));

        $this->get($this->livewireScriptUri())
            ->assertOk()
            ->assertHeaderMissing('Content-Length');
    }

    public function test_content_length_is_kept_when_php_does_not_compress_output(): void
    {
        $this->app->instance(DropContentLengthWhenPhpCompresses::class, new DropContentLengthWhenPhpCompresses(phpCompressesOutput: false));

        $this->get($this->livewireScriptUri())
            ->assertOk()
            ->assertHeader('Content-Length');
    }
}
