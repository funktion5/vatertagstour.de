<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Page;
use App\Models\Post;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Song;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use SimpleXMLElement;

#[Signature('import:wordpress {file? : WXR export, defaults to the *.WordPress.*.xml in the project root} {--media : Download referenced uploads from the live site}')]
#[Description('Import products, posts, pages and songs from a WordPress WXR export (idempotent)')]
class ImportWordPress extends Command
{
    private const UPLOADS = 'https://www.vatertagstour.de/wp-content/uploads/';

    private const WP = 'http://wordpress.org/export/1.2/';

    /** @var array<int, SimpleXMLElement> */
    private array $items = [];

    /** @var array<string, true> relative upload paths to download */
    private array $media = [];

    public function handle(): int
    {
        $file = $this->argument('file') ?? (glob(base_path('*.WordPress.*.xml'))[0] ?? null);
        if (! $file || ! is_file($file)) {
            $this->error('No WXR export found.');

            return self::FAILURE;
        }

        $channel = simplexml_load_file($file, options: LIBXML_NOCDATA)->channel;
        foreach ($channel->item as $item) {
            $this->items[(int) $this->wp($item, 'post_id')] = $item;
        }

        DB::transaction(function () use ($channel) {
            $categories = $this->importCategories($channel);
            $this->importProducts($categories);
            $this->importPosts();
            $this->importPages();
        });

        if ($this->option('media')) {
            $this->downloadMedia();
        } elseif ($this->media) {
            $this->warn(count($this->media).' uploads referenced; re-run with --media to download them.');
        }

        $this->info(sprintf(
            'Imported %d products, %d categories, %d posts, %d pages, %d songs.',
            Product::count(), ProductCategory::count(), Post::count(), Page::count(), Song::count(),
        ));

        return self::SUCCESS;
    }

    /** @return array<string, int> slug => id */
    private function importCategories(SimpleXMLElement $channel): array
    {
        $map = [];
        foreach ($channel->children(self::WP)->term as $term) {
            if ((string) $term->term_taxonomy !== 'tourausruestung') {
                continue;
            }
            $category = ProductCategory::updateOrCreate(
                ['wp_id' => (int) $term->term_id],
                ['name' => html_entity_decode((string) $term->term_name), 'slug' => (string) $term->term_slug],
            );
            $map[$category->slug] = $category->id;
        }

        return $map;
    }

    /** @param array<string, int> $categories */
    private function importProducts(array $categories): void
    {
        foreach ($this->ofType('product') as $id => $item) {
            $meta = $this->meta($item);

            // ACF external gallery repeater: product_gallery_external_N_{url,alt,hide}
            $gallery = [];
            for ($n = 0; $n < (int) ($meta['product_gallery_external'] ?? 0); $n++) {
                if (($meta["product_gallery_external_{$n}_url"] ?? '') !== '' && empty($meta["product_gallery_external_{$n}_hide"])) {
                    $gallery[] = [
                        'url' => $meta["product_gallery_external_{$n}_url"],
                        'alt' => html_entity_decode($meta["product_gallery_external_{$n}_alt"] ?? ''),
                    ];
                }
            }

            $image = ($meta['_thumbnail_id'] ?? null) === 'by_url'
                ? ($meta['_thumbnail_ext_url'] ?? null)
                : $this->attachmentPath($meta['_thumbnail_id'] ?? null);

            $product = Product::updateOrCreate(['wp_id' => $id], [
                ...$this->common($item),
                'verdict' => $meta['vatertagstour.de_meint:'] ?? null ?: null,
                'body' => $this->body($item),
                'rating' => is_numeric($meta['product_rating'] ?? null) ? $meta['product_rating'] : null,
                'image' => $image,
                'gallery' => $gallery ?: null,
                'meta_description' => $meta['_yoast_wpseo_metadesc'] ?? null ?: null,
            ]);

            $product->categories()->sync(array_values(array_filter(array_map(
                fn ($c) => $categories[(string) $c['nicename']] ?? null,
                iterator_to_array($item->xpath('category[@domain="tourausruestung"]')),
            ))));

            // Prices are deliberately not imported, see the migration.
            $product->offers()->delete();
            for ($n = 0; $n < (int) ($meta['product_shops'] ?? 0); $n++) {
                $portal = $meta["product_shops_{$n}_portal"] ?? '';
                $url = $meta["product_shops_{$n}_link"] ?? '';
                if ($url === '' || in_array($portal, ['', 'none'], true)) {
                    continue;
                }
                $product->offers()->create([
                    'portal' => $portal,
                    'asin' => $meta["product_shops_{$n}_amazon_asin"] ?? null ?: null,
                    'url' => $url,
                    'position' => $n,
                ]);
            }
        }
    }

    private function importPosts(): void
    {
        foreach ($this->ofType('post') as $id => $item) {
            $meta = $this->meta($item);
            $body = $this->body($item);

            $galleryId = preg_match('/\[envira-gallery id="(\d+)"\]/', $body, $m) ? (int) $m[1] : null;
            $body = trim(preg_replace('/\[envira-gallery[^\]]*\]/', '', $body));

            $post = Post::updateOrCreate(['wp_id' => $id], [
                ...$this->common($item),
                'body' => $body,
                'image' => $this->attachmentPath($meta['_thumbnail_id'] ?? null),
                'meta_description' => $meta['_yoast_wpseo_metadesc'] ?? null ?: null,
                'published_at' => $this->date($item),
            ]);

            $post->images()->delete();
            if ($galleryId && isset($this->items[$galleryId])) {
                $data = unserialize($this->meta($this->items[$galleryId])['_eg_gallery_data'] ?? '', ['allowed_classes' => false]);
                $position = 0;
                foreach ($data['gallery'] ?? [] as $image) {
                    if (($image['status'] ?? '') !== 'active' || ! ($path = $this->uploadPath($image['src'] ?? ''))) {
                        continue;
                    }
                    $post->images()->create([
                        'path' => $path,
                        'alt' => $image['alt'] ?? null ?: null,
                        'caption' => $image['caption'] ?? null ?: null,
                        'position' => $position++,
                    ]);
                }
            }
        }
    }

    private function importPages(): void
    {
        foreach ($this->ofType('page') as $id => $item) {
            $meta = $this->meta($item);
            $body = $this->body($item);

            Page::updateOrCreate(['wp_id' => $id], [
                ...array_diff_key($this->common($item), ['published_at' => 0]),
                'body' => $body,
                'meta_title' => $meta['_yoast_wpseo_title'] ?? null ?: null,
                'meta_description' => $meta['_yoast_wpseo_metadesc'] ?? null ?: null,
            ]);

            if (preg_match('/\[playlist ids="([\d,]+)"\]/', $body, $m)) {
                foreach (explode(',', $m[1]) as $position => $songId) {
                    $song = $this->items[(int) $songId] ?? null;
                    if (! $song || ! ($file = $this->attachmentPath($songId))) {
                        continue;
                    }
                    Song::updateOrCreate(['wp_id' => (int) $songId], [
                        'title' => html_entity_decode((string) $song->title),
                        'description' => trim(strip_tags($this->content($song))) ?: null,
                        'file' => $file,
                        'position' => $position,
                    ]);
                }
            }
        }
    }

    private function downloadMedia(): void
    {
        $disk = Storage::disk('public');
        $bar = $this->output->createProgressBar(count($this->media));
        $failed = [];

        foreach (array_keys($this->media) as $path) {
            $target = 'wordpress/'.$path;
            if (! $disk->exists($target)) {
                $response = Http::timeout(30)->retry(2, 1000, throw: false)->get(self::UPLOADS.$path);
                $response->successful() ? $disk->put($target, $response->body()) : $failed[] = $path;
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        foreach ($failed as $path) {
            $this->warn("Download failed: {$path}");
        }
    }

    /** @return iterable<int, SimpleXMLElement> */
    private function ofType(string $type): iterable
    {
        foreach ($this->items as $id => $item) {
            if ($this->wp($item, 'post_type') === $type && in_array($this->wp($item, 'status'), ['publish', 'draft'], true)) {
                yield $id => $item;
            }
        }
    }

    /** @return array<string, mixed> */
    private function common(SimpleXMLElement $item): array
    {
        $published = $this->wp($item, 'status') === 'publish';

        return [
            'title' => html_entity_decode((string) $item->title),
            'slug' => $this->wp($item, 'post_name') ?: 'entwurf-'.$this->wp($item, 'post_id'),
            'is_published' => $published,
            'published_at' => $published ? $this->date($item) : null,
        ];
    }

    /** Content with own-site absolute URLs made relative and uploads pointed at local storage. */
    private function body(SimpleXMLElement $item): string
    {
        $body = preg_replace_callback(
            '#https?://(?:www\.)?vatertagstour\.de/wp-content/uploads/([^"\'\s)]+)#',
            function ($m) {
                $this->media[$m[1]] = true;

                return '/storage/wordpress/'.$m[1];
            },
            $this->content($item),
        );

        return trim(preg_replace('#https?://(?:www\.)?vatertagstour\.de(?=/)#', '', $body));
    }

    private function content(SimpleXMLElement $item): string
    {
        return (string) $item->children('http://purl.org/rss/1.0/modules/content/')->encoded;
    }

    private function attachmentPath(int|string|null $id): ?string
    {
        $attachment = is_numeric($id) ? ($this->items[(int) $id] ?? null) : null;

        return $attachment ? $this->uploadPath($this->wp($attachment, 'attachment_url')) : null;
    }

    /** Maps a live-site upload URL to its path on the public disk and queues it for download. */
    private function uploadPath(string $url): ?string
    {
        if (! Str::contains($url, '/wp-content/uploads/')) {
            return null;
        }
        $relative = Str::after($url, '/wp-content/uploads/');
        $this->media[$relative] = true;

        return 'wordpress/'.$relative;
    }

    private function date(SimpleXMLElement $item): ?string
    {
        $gmt = $this->wp($item, 'post_date_gmt');

        return $gmt && ! str_starts_with($gmt, '0000') ? $gmt : null;
    }

    /** @return array<string, string> */
    private function meta(SimpleXMLElement $item): array
    {
        $meta = [];
        foreach ($item->children(self::WP)->postmeta as $row) {
            $meta[(string) $row->meta_key] = (string) $row->meta_value;
        }

        return $meta;
    }

    private function wp(SimpleXMLElement $item, string $field): string
    {
        return (string) $item->children(self::WP)->{$field};
    }
}
