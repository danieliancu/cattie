<?php

namespace App\Domain\Catalogue\Actions;

use App\Models\Product;
use App\Models\ProductVariant;
use DomainException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class SyncProductMarketingAssets
{
    private const SUPPORTED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

    /**
     * @return array<int, array{variant:string,filename:string,role:string,storage_key:string}>
     */
    public function handle(Product $product, string $sourceDirectory, string $variantOption = 'colour', ?string $publicPrefix = null): array
    {
        if (! is_dir($sourceDirectory)) {
            throw new RuntimeException("Marketing asset directory [{$sourceDirectory}] is unavailable.");
        }

        $publicPrefix ??= "products/{$product->slug}/catalogue";
        $publicPrefix = trim(str_replace('\\', '/', $publicPrefix), '/');
        $variants = $product->variants()->active()->ordered()->get();
        $managedPrefixes = $variants
            ->map(fn (ProductVariant $variant) => mb_strtolower(trim((string) ($variant->options[$variantOption] ?? ''))))
            ->filter()
            ->unique()
            ->map(fn (string $value) => "{$publicPrefix}/{$value}")
            ->values();
        $folders = collect(scandir($sourceDirectory) ?: [])
            ->reject(fn (string $name) => $name === '' || $name[0] === '.')
            ->filter(fn (string $name) => is_dir($sourceDirectory.DIRECTORY_SEPARATOR.$name))
            ->sortBy(fn (string $name) => mb_strtolower($name), SORT_NATURAL)
            ->values();
        $discovered = [];

        foreach ($folders as $folder) {
            $matchingVariants = $variants->filter(fn (ProductVariant $variant) => mb_strtolower((string) ($variant->options[$variantOption] ?? '')) === mb_strtolower($folder));
            if ($matchingVariants->count() !== 1) {
                throw new DomainException("Marketing asset folder [{$folder}] must match exactly one active [{$variantOption}] variant; [{$matchingVariants->count()}] found.");
            }

            $variant = $matchingVariants->first();
            $files = collect(scandir($sourceDirectory.DIRECTORY_SEPARATOR.$folder) ?: [])
                ->reject(fn (string $name) => $name === '' || $name[0] === '.' || str_starts_with($name, '~'))
                ->filter(function (string $name) use ($sourceDirectory, $folder) {
                    $path = $sourceDirectory.DIRECTORY_SEPARATOR.$folder.DIRECTORY_SEPARATOR.$name;

                    return is_file($path) && in_array(mb_strtolower(pathinfo($name, PATHINFO_EXTENSION)), self::SUPPORTED_EXTENSIONS, true);
                })
                ->sortBy(fn (string $name) => mb_strtolower($name), SORT_NATURAL)
                ->values();

            if ($files->isEmpty()) {
                continue;
            }

            $primaryCandidates = $files
                ->filter(fn (string $name) => preg_match('/(?:^|[-_])(product|primary|hero)(?:[-_.]|$)/i', pathinfo($name, PATHINFO_FILENAME)) === 1)
                ->sortBy(fn (string $name) => sprintf('%06d:%s', mb_strlen(pathinfo($name, PATHINFO_FILENAME)), mb_strtolower($name)))
                ->values();
            if ($primaryCandidates->count() > 1) {
                Log::warning('Multiple marketing images claim primary role; using the first deterministic candidate.', [
                    'product_id' => $product->id, 'variant_id' => $variant->id, 'files' => $primaryCandidates->all(),
                ]);
            }
            $primary = $primaryCandidates->first() ?? $files->first();
            if ($primaryCandidates->isEmpty()) {
                Log::warning('Marketing images have no explicit primary; using the first deterministic image.', [
                    'product_id' => $product->id, 'variant_id' => $variant->id, 'file' => $primary,
                ]);
            }

            $variantOffset = $variants->search(fn (ProductVariant $candidate) => $candidate->is($variant)) * 100;
            $ranked = $files->map(function (string $filename) use ($primary) {
                if ($filename === $primary) {
                    return ['filename' => $filename, 'role' => 'primary', 'rank' => 0];
                }
                $stem = mb_strtolower(pathinfo($filename, PATHINFO_FILENAME));
                $role = match (true) {
                    preg_match('/(?:^|[-_])school(?:[-_]|$)/', $stem) === 1 => 'school',
                    preg_match('/(?:^|[-_])home(?:[-_]|$)/', $stem) === 1 => 'home',
                    preg_match('/(?:^|[-_])(street|outdoor)(?:[-_]|$)/', $stem) === 1 => 'street',
                    default => 'gallery',
                };

                return ['filename' => $filename, 'role' => $role, 'rank' => ['school' => 10, 'home' => 20, 'street' => 30, 'gallery' => 40][$role]];
            })->sortBy([['rank', 'asc'], ['filename', 'asc']])->values();

            foreach ($ranked as $index => $asset) {
                $sourcePath = $sourceDirectory.DIRECTORY_SEPARATOR.$folder.DIRECTORY_SEPARATOR.$asset['filename'];
                $storageKey = "{$publicPrefix}/".mb_strtolower($folder).'/'.$asset['filename'];
                $bytes = file_get_contents($sourcePath);
                if ($bytes === false) {
                    throw new RuntimeException("Marketing image [{$sourcePath}] could not be read.");
                }
                if (! Storage::disk('public')->exists($storageKey) || hash('sha256', Storage::disk('public')->get($storageKey)) !== hash('sha256', $bytes)) {
                    Storage::disk('public')->put($storageKey, $bytes);
                }

                $colour = Str::title((string) ($variant->options[$variantOption] ?? $folder));
                $description = match ($asset['role']) {
                    'primary' => 'personalised product example',
                    'school' => 'personalised school lifestyle example',
                    'home' => 'personalised home lifestyle example',
                    'street' => 'personalised outdoor lifestyle example',
                    default => 'personalised product inspiration',
                };
                $sortOrder = $variantOffset + $asset['rank'] + ($asset['role'] === 'gallery' ? $index : 0);
                $product->images()->updateOrCreate(
                    ['storage_key' => $storageKey],
                    ['product_variant_id' => $variant->id, 'disk' => 'public', 'alt_text' => "{$colour} {$product->name}, {$description}", 'role' => $asset['role'], 'is_product' => $asset['role'] === 'primary' && $variant->is($variants->first()), 'sort_order' => $sortOrder],
                );
                $discovered[] = ['variant' => mb_strtolower($folder), 'filename' => $asset['filename'], 'role' => $asset['role'], 'storage_key' => $storageKey];
            }
        }

        $activeKeys = collect($discovered)->pluck('storage_key');
        $managedImages = $managedPrefixes->isEmpty()
            ? collect()
            : $product->images()->where(function ($query) use ($managedPrefixes) {
                foreach ($managedPrefixes as $index => $prefix) {
                    $method = $index === 0 ? 'where' : 'orWhere';
                    $query->{$method}('storage_key', 'like', $prefix.'/%');
                }
            })->get();
        foreach ($managedImages->whereNotIn('storage_key', $activeKeys) as $stale) {
            Storage::disk($stale->disk)->delete($stale->storage_key);
            $stale->delete();
        }

        return $discovered;
    }
}
