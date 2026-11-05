<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\Post;
use App\Models\Product;
use App\Models\Song;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImportWordPressTest extends TestCase
{
    use RefreshDatabase;

    public function test_imports_fixture_idempotently_and_downloads_media(): void
    {
        Storage::fake('public');
        Http::fake(['www.vatertagstour.de/*' => Http::response('bytes')]);
        $file = base_path('tests/Fixtures/wordpress.xml');

        $this->artisan('import:wordpress', ['file' => $file])->assertSuccessful();
        $this->artisan('import:wordpress', ['file' => $file, '--media' => true])->assertSuccessful();

        $product = Product::sole();
        $this->assertSame('Trinkhelm BEER – Prost', $product->title);
        $this->assertSame('Hand frei!', $product->verdict);
        $this->assertSame('https://m.media-amazon.com/images/I/a.jpg', $product->image);
        $this->assertSame([['url' => 'https://m.media-amazon.com/images/I/b.jpg', 'alt' => 'Helm – 2']], $product->gallery);
        $this->assertSame(['rund-ums-bier'], $product->categories->pluck('slug')->all());
        // 'none' portal skipped, offers not duplicated by the second run
        $this->assertSame(['B002IKFPCS'], $product->offers->pluck('asin')->all());
        $this->assertStringContainsString('href="/tourausruestung/"', $product->body);
        $this->assertStringContainsString('src="/storage/wordpress/helm.jpg"', $product->body);

        $post = Post::sole();
        $this->assertSame('Die Tour ums Meer.', $post->body);
        $this->assertSame(['wordpress/goe025.jpg'], $post->images->pluck('path')->all());

        $this->assertSame('Lieder zum Vatertag', Page::where('slug', 'vatertagslieder')->value('meta_title'));
        $this->assertFalse(Page::where('slug', 'entwurf-13')->value('is_published'));

        $this->assertSame('wordpress/videothek.mp3', Song::sole()->file);

        Storage::disk('public')->assertExists(['wordpress/helm.jpg', 'wordpress/goe025.jpg', 'wordpress/videothek.mp3']);
        Storage::disk('public')->assertMissing('wordpress/goe026.jpg');
    }
}
