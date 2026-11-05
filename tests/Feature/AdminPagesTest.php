<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\Post;
use App\Models\Product;
use App\Models\Song;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_list_and_edit_pages_render(): void
    {
        $this->artisan('import:wordpress', ['file' => base_path('tests/Fixtures/wordpress.xml')]);
        $this->actingAs(User::factory()->create());

        $urls = ['/admin/product-categories', '/admin/products', '/admin/posts', '/admin/pages', '/admin/songs'];
        foreach ([Product::class => 'products', Post::class => 'posts', Page::class => 'pages', Song::class => 'songs'] as $model => $slug) {
            $urls[] = "/admin/{$slug}/".$model::first()->id.'/edit';
        }

        foreach ($urls as $url) {
            $this->get($url)->assertOk();
        }
    }
}
