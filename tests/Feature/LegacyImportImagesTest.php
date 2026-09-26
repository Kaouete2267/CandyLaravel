<?php

namespace Tests\Feature;

use Database\Seeders\BonbonBaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Lunar\Models\ProductVariant;
use Tests\TestCase;

class LegacyImportImagesTest extends TestCase
{
    use RefreshDatabase;

    private string $dataDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(BonbonBaseSeeder::class);
        Storage::fake('public');

        $this->dataDir = storage_path('framework/testing/legacy-'.uniqid());
        File::ensureDirectoryExists($this->dataDir.'/images');
        File::ensureDirectoryExists($this->dataDir.'/logos');
        File::put($this->dataDir.'/source.json', json_encode([
            'TYPE' => [['id' => 0, 'name' => 'LISSE', 'key' => 0]],
            'MARK' => [['id' => 7, 'name' => 'HARIBO']],
            'BONBON' => [['id' => 30, 'nom' => 'VIOLETTES', 'marque' => 7, 'type' => 0, 'ing' => 'sucre']],
        ]));

        UploadedFile::fake()->image('b.jpg', 40, 40)->move($this->dataDir.'/images', 'BB-030-violette-dos.jpg');
        UploadedFile::fake()->image('a.jpg', 40, 40)->move($this->dataDir.'/images', 'BB-030-violette.jpg');
        UploadedFile::fake()->image('c.jpg', 40, 40)->move($this->dataDir.'/images', 'BB-031-autre.jpg');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dataDir);

        parent::tearDown();
    }

    /** @param  array<string, mixed>  $options */
    private function import(array $options = []): void
    {
        $this->artisan('bonbon:import-legacy', [
            '--source' => $this->dataDir.'/source.json',
            '--logos' => $this->dataDir.'/logos',
            '--images' => $this->dataDir.'/images',
            ...$options,
        ])->assertSuccessful();
    }

    public function test_imported_candy_gets_its_photos_with_the_first_one_as_primary(): void
    {
        $this->import();

        $media = ProductVariant::where('sku', 'BB-030')->first()->product->getMedia('images');

        $this->assertSame(['violette-dos.jpg', 'violette.jpg'], $media->pluck('file_name')->all());
        $this->assertSame([true, false], $media->map(fn ($m) => $m->getCustomProperty('primary'))->all());
        $this->assertFileExists($this->dataDir.'/images/BB-030-violette.jpg');
    }

    public function test_reimport_adds_photos_only_to_candies_that_have_none(): void
    {
        $this->import(['--images' => $this->dataDir.'/empty']);
        $product = ProductVariant::where('sku', 'BB-030')->first()->product;
        $this->assertCount(0, $product->getMedia('images'));

        $this->import();
        $this->import();

        $this->assertCount(2, $product->refresh()->getMedia('images'));
    }
}
