<?php

namespace Database\Seeders;

use App\Domain\Catalogue\Actions\SyncProductMarketingAssets;
use App\Enums\ProductStatus;
use App\Models\ArtworkStyle;
use App\Models\Product;
use App\Models\ProductDesignTemplate;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class CatalogueSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(ArtworkStyleSeeder::class);
        $styles = ArtworkStyle::query()->get()->keyBy('slug');

        foreach (glob(database_path('seeders/assets/catalogue/*')) as $asset) {
            Storage::disk('public')->put('demo/catalogue/'.basename($asset), file_get_contents($asset));
        }

        $products = [
            ['name' => "Children's Storybook Wall Print", 'slug' => 'childrens-storybook-wall-print', 'short' => 'Turn one favourite photo into a joyful storybook moment made for their room.', 'description' => 'A colourful personalised art print that celebrates their imagination. Choose a size, add their name and we will guide you through creating the artwork before you order.', 'meta' => 'Create a personalised children’s storybook wall print from a favourite photo, made with care in the UK.', 'price' => 2495, 'image' => 'storybook-print.svg', 'styles' => ['storybook-cartoon', 'hand-drawn'], 'recommended' => 'storybook-cartoon', 'variants' => [['A4', 'PRINT-KIDS-A4', 2495, ['size' => 'A4']], ['A3', 'PRINT-KIDS-A3', 3495, ['size' => 'A3']]], 'fields' => [['child_name', "Child's name", 'text', true, ['max' => 30], []], ['dedication', 'Optional dedication', 'textarea', false, ['max' => 120], []]]],
            ['name' => 'Our Family Art Print', 'slug' => 'our-family-art-print', 'short' => 'Bring everyone together in a warm portrait made to feel unmistakably yours.', 'description' => 'A premium family portrait print created around the people and moments you treasure. Available in two generous sizes and both signature artwork styles.', 'meta' => 'Transform a family photo into a personalised premium family art print in Storybook or Hand Drawn style.', 'price' => 2995, 'image' => 'family-print.svg', 'styles' => ['storybook-cartoon', 'hand-drawn'], 'recommended' => 'hand-drawn', 'variants' => [['A4', 'PRINT-FAMILY-A4', 2995, ['size' => 'A4']], ['A3', 'PRINT-FAMILY-A3', 3995, ['size' => 'A3']]], 'fields' => [['family_name', 'Family name', 'text', false, ['max' => 40], []], ['caption', 'Short message', 'text', false, ['max' => 60], []]]],
            ['name' => 'Little Moments Mug', 'slug' => 'little-moments-mug', 'short' => 'A cheerful everyday reminder of someone wonderfully special.', 'description' => 'A personalised ceramic mug featuring your approved artwork, with a choice of colour accent and room for a short name.', 'meta' => 'Design a personalised photo artwork mug with a name and your choice of warm colour accent.', 'price' => 1895, 'image' => 'mug.svg', 'styles' => ['storybook-cartoon'], 'recommended' => 'storybook-cartoon', 'variants' => [['Blush', 'MUG-BLUSH', 1895, ['colour' => 'Blush']], ['Sage', 'MUG-SAGE', 1895, ['colour' => 'Sage']]], 'fields' => [['name', 'Name', 'text', true, ['max' => 24], []], ['message', 'Small message', 'text', false, ['max' => 48], []]]],
            ['name' => 'Cuddle Close Cushion', 'slug' => 'cuddle-close-cushion', 'short' => 'A soft, meaningful gift made from a photograph you already love.', 'description' => 'A cosy square cushion printed with your personalised portrait. Choose a gentle colour palette and add an optional dedication.', 'meta' => 'Create a soft personalised portrait cushion from a treasured family or child photograph.', 'price' => 3295, 'image' => 'cushion.svg', 'styles' => ['storybook-cartoon', 'hand-drawn'], 'recommended' => 'storybook-cartoon', 'variants' => [['Blush · 40 cm', 'CUSHION-BLUSH-40', 3295, ['colour' => 'Blush', 'size' => '40 cm']], ['Sage · 40 cm', 'CUSHION-SAGE-40', 3295, ['colour' => 'Sage', 'size' => '40 cm']]], 'fields' => [['dedication', 'Dedication', 'textarea', false, ['max' => 100], []], ['palette', 'Colour palette', 'select', true, [], ['options' => ['Warm blush', 'Soft sage', 'Natural']]]]],
            ['name' => 'Best Friend Pet Portrait', 'slug' => 'best-friend-pet-portrait', 'short' => 'Celebrate the four-legged character who makes your house a home.', 'description' => 'A characterful pet portrait print created from your favourite clear photo. Add their name and choose the finish that suits your space.', 'meta' => 'Turn your pet photo into a personalised Hand Drawn or Storybook pet portrait print.', 'price' => 2695, 'image' => 'pet-print.svg', 'styles' => ['hand-drawn', 'storybook-cartoon'], 'recommended' => 'hand-drawn', 'variants' => [['A4', 'PRINT-PET-A4', 2695, ['size' => 'A4']], ['A3', 'PRINT-PET-A3', 3695, ['size' => 'A3']]], 'fields' => [['pet_name', "Pet's name", 'text', true, ['max' => 30], []], ['special_date', 'Special date', 'date', false, [], []]]],
        ];

        foreach ($products as $position => $data) {
            $product = Product::query()->updateOrCreate(['slug' => $data['slug']], ['name' => $data['name'], 'short_description' => $data['short'], 'description' => $data['description'], 'meta_description' => $data['meta'], 'is_active' => true, 'sort_order' => $position + 1, 'base_price_minor' => $data['price'], 'currency' => 'GBP', 'artwork_requirements' => ['source_photo' => 'required', 'orientation' => 'portrait_preferred'], 'preview_configuration' => []]);
            $styleIds = collect($data['styles'])->map(fn ($slug) => $styles[$slug]->id);
            $product->artworkStyles()->sync($styleIds);
            $product->update(['recommended_artwork_style_id' => $styles[$data['recommended']]->id]);
            $product->images()->delete();
            $product->images()->create(['disk' => 'public', 'storage_key' => 'demo/catalogue/'.$data['image'], 'alt_text' => $data['name'].' personalised gift mockup', 'sort_order' => 0]);
            $variantSkus = collect($data['variants'])->pluck(1)->all();
            $product->variants()->whereNotIn('sku', $variantSkus)->delete();
            foreach ($data['variants'] as $index => [$name,$sku,$price,$options]) {
                $variant = $product->variants()->withTrashed()->updateOrCreate(['sku' => $sku], ['name' => $name, 'price_minor' => $price, 'currency' => 'GBP', 'options' => $options, 'is_active' => true, 'sort_order' => $index]);
                if ($variant->trashed()) {
                    $variant->restore();
                }
            }
            $product->personalisationFields()->delete();
            foreach ($data['fields'] as $index => [$key,$label,$type,$required,$rules,$configuration]) {
                $product->personalisationFields()->create(['key' => $key, 'label' => $label, 'type' => $type, 'is_required' => $required, 'validation_rules' => $rules, 'configuration' => $configuration, 'sort_order' => $index]);
            }
        }

        $this->seedProdigiWaterBottle();
        $this->seedTreatPodWaterBottle();
        $this->seedSmallPlasticLunchbox();
        $this->seedStationeryPencilTin();
        $this->seedCustomWallPrints();
        // Runs last: the taxonomy assigns the products seeded above.
        $this->call(CategoryTaxonomySeeder::class);

        // Products carry is_active, but storefront visibility also depends on
        // `status` (Product::scopeActive requires status=published). On a fresh
        // migrate:fresh + seed, the one-time migration that back-filled status
        // ran against an empty table, so sync it here to keep seeded products
        // visible instead of stuck on the default draft status.
        Product::query()->where('is_active', true)->update(['status' => ProductStatus::Published->value]);
        Product::query()->where('is_active', false)->update(['status' => ProductStatus::Archived->value]);
    }

    private function seedProdigiWaterBottle(): void
    {
        $designTemplate = ProductDesignTemplate::query()->updateOrCreate(
            ['key' => 'bottle-wrap-v1'],
            ['version' => 4, 'definition_path' => 'bottle-wrap-v1/template.json'],
        );

        $product = Product::query()->updateOrCreate(
            ['slug' => 'cattie-water-bottle'],
            [
                'name' => 'Kattie Water Bottle',
                'short_description' => 'A future personalised 650 ml insulated bottle.',
                'description' => 'Inactive catalogue placeholder for the first Prodigi-backed Kattie bottle.',
                'meta_description' => null,
                'is_active' => false,
                'sort_order' => 999,
                'base_price_minor' => 1650,
                'currency' => 'GBP',
                'artwork_requirements' => ['source_photo' => 'required', 'orientation' => 'portrait_preferred'],
                'preview_configuration' => [
                    'default_variant_options' => ['colour' => 'black'],
                    'mockup_mode' => 'static_boundary',
                    'mockup_asset' => [
                        'disk' => 'public',
                        'storage_key' => 'products/cattie-water-bottle/mockup/blank.jpg',
                        'role' => 'compositing_base',
                    ],
                    'compositing' => [
                        'base_has_alpha' => false,
                        'base_contains_background' => true,
                        'artwork_layer' => 'above_base',
                        'requires_bottle_mask' => true,
                        'requires_cylindrical_transform' => true,
                    ],
                ],
                'product_design_template_id' => $designTemplate->id,
                'recommended_artwork_style_id' => ArtworkStyle::query()->where('slug', 'storybook-cartoon')->value('id'),
            ],
        );

        $colours = ['black', 'grey', 'navy', 'red'];
        $printResolutions = [
            'black / translucent' => [2498, 1828],
            'lime' => [2716, 2125],
            'white / clear' => [2498, 1828],
        ];
        $internalSkus = [];
        foreach ($colours as $index => $colour) {
            $internalSku = 'BOTTLE-650-'.str($colour)->upper()->replace([' / ', ' '], '-');
            $internalSkus[] = $internalSku;
            $variant = $product->variants()->withTrashed()->updateOrCreate(
                ['sku' => $internalSku],
                ['name' => ($colour === 'grey' ? 'Gray' : str($colour)->title()).' · 650 ml', 'options' => ['colour' => $colour, 'size' => '650ml / 22oz'], 'price_minor' => 1650, 'currency' => 'GBP', 'is_active' => true, 'sort_order' => $index],
            );
            if ($variant->trashed()) {
                $variant->restore();
            }
            $variant->fulfilmentMappings()->updateOrCreate(
                ['provider' => 'prodigi'],
                [
                    'provider_sku' => '650ML-WATER-BOTTLE',
                    'configuration' => [
                        'attributes' => ['color' => $colour, 'size' => '650ml / 22oz'],
                        'print_areas' => [
                            'default' => [
                                'width' => ($printResolutions[$colour] ?? [2750, 2279])[0],
                                'height' => ($printResolutions[$colour] ?? [2750, 2279])[1],
                                'dpi' => 300,
                            ],
                        ],
                    ],
                    'is_active' => true,
                ],
            );
        }
        $product->variants()->whereNotIn('sku', $internalSkus)->delete();

        $product->artworkStyles()->sync(ArtworkStyle::query()->whereIn('slug', ['storybook-cartoon', 'hand-drawn'])->pluck('id'));
        $product->personalisationFields()->delete();
        $product->personalisationFields()->create([
            'key' => 'name',
            'label' => 'Name',
            'type' => 'text',
            'is_required' => true,
            'validation_rules' => ['max' => 12],
            'configuration' => [],
            'sort_order' => 0,
        ]);

        $supplierAssets = config('product-assets.suppliers.prodigi.650ML-WATER-BOTTLE');
        $sourceDirectory = resource_path($supplierAssets['source_directory']);
        foreach ($supplierAssets['assets'] as $asset) {
            Storage::disk($asset['public']['disk'])->put(
                $asset['public']['storage_key'],
                file_get_contents($sourceDirectory.'/'.$asset['filename']),
            );
        }

        $variantsByColour = $product->variants()->get()->keyBy(fn ($variant) => $variant->options['colour']);
        $product->images()->delete();
        foreach (collect($supplierAssets['assets'])->whereIn('role', ['primary', 'gallery', 'detail'])->sortBy('sort_order') as $asset) {
            $colour = $asset['variant_options']['colour'];
            $product->images()->create([
                'product_variant_id' => $variantsByColour->get($colour)?->id,
                'disk' => $asset['public']['disk'],
                'storage_key' => $asset['public']['storage_key'],
                'alt_text' => $asset['alt_text'],
                'sort_order' => $asset['sort_order'],
            ]);
        }
    }

    private function seedTreatPodWaterBottle(): void
    {
        $designTemplate = ProductDesignTemplate::query()->updateOrCreate(
            ['key' => 'red-flip-bottle-wrap-v1'],
            ['version' => 1, 'definition_path' => 'red-flip-bottle-wrap-v1/template.json'],
        );

        $product = Product::query()->updateOrCreate(
            ['slug' => 'water-bottle-with-red-flip-lid'],
            [
                'name' => 'Water Bottle with Red Flip Lid',
                'short_description' => 'A personalised 750 ml aluminium bottle with a bright red flip lid, made unique with their name and artwork.',
                'description' => 'A lightweight 750 ml aluminium water bottle with a red flip lid, personalised with their name and Kattie artwork. The wraparound design makes it a colourful everyday bottle for school, sports and days out. The Silver option has a subtle shimmering finish.',
                'meta_description' => 'Create a personalised 750 ml aluminium water bottle with a red flip lid, name and custom Kattie artwork.',
                'is_active' => true,
                'sort_order' => 6,
                'base_price_minor' => 1650,
                'currency' => 'GBP',
                'artwork_requirements' => ['source_photo' => 'required', 'orientation' => 'portrait', 'framing' => 'full_body', 'isolated_subject' => true, 'transparent_background' => true],
                'preview_configuration' => [
                    'default_variant_options' => ['colour' => 'white'],
                    'design_surfaces_by_variant' => [
                        'white' => '#ffffff',
                        'silver' => '#d9dde2',
                    ],
                ],
                'product_design_template_id' => $designTemplate->id,
                'recommended_artwork_style_id' => ArtworkStyle::query()->where('slug', 'storybook-cartoon')->value('id'),
            ],
        );

        $variants = [
            ['White · 750 ml', 'CATTIE-WB-750-WHITE', 'white', 'WB-750MLWHT-FLIPRED'],
            ['Silver · 750 ml', 'CATTIE-WB-750-SILVER', 'silver', 'WB-750MLSLV-FLIPRED'],
        ];
        $internalSkus = [];
        foreach ($variants as $index => [$name, $sku, $colour, $providerSku]) {
            $internalSkus[] = $sku;
            $variant = $product->variants()->withTrashed()->updateOrCreate(
                ['sku' => $sku],
                ['name' => $name, 'options' => ['colour' => $colour, 'size' => '750ml'], 'price_minor' => 1650, 'currency' => 'GBP', 'is_active' => true, 'sort_order' => $index],
            );
            if ($variant->trashed()) {
                $variant->restore();
            }
            $variant->fulfilmentMappings()->updateOrCreate(
                ['provider' => 'treatpod'],
                [
                    'provider_sku' => $providerSku,
                    'configuration' => [
                        'attributes' => ['colour' => $colour, 'size' => '750ml', 'capacity_ml' => 750, 'material' => 'aluminium', 'lid_colour' => 'red'],
                        'print_method' => 'sublimation',
                        'placement' => 'wraparound',
                        'physical_print_area' => ['width' => 230, 'height' => 170, 'unit' => 'mm'],
                        'print_areas' => [
                            'default' => ['width' => 2717, 'height' => 2008, 'dpi' => 300, 'derived_from' => '230x170mm_at_300dpi', 'supplier_template_validated' => false],
                        ],
                    ],
                    'is_active' => true,
                ],
            );
        }
        $product->variants()->whereNotIn('sku', $internalSkus)->update(['is_active' => false]);

        $product->artworkStyles()->sync(ArtworkStyle::query()->whereIn('slug', ['storybook-cartoon', 'hand-drawn'])->pluck('id'));
        $product->personalisationFields()->delete();
        $product->personalisationFields()->create([
            'key' => 'name', 'label' => 'Name', 'type' => 'text', 'is_required' => true,
            'validation_rules' => ['max' => 12], 'configuration' => [], 'sort_order' => 0,
        ]);

        $supplierAssets = config('product-assets.suppliers.treatpod.WB-750ML-FLIP.assets');
        $product->images()->whereIn('storage_key', collect($supplierAssets)->pluck('public.storage_key'))->delete();

        app(SyncProductMarketingAssets::class)->handle(
            $product,
            resource_path('product-assets/cattie/water-bottle-with-red-flip-lid'),
        );
    }

    private function seedSmallPlasticLunchbox(): void
    {
        $designTemplate = ProductDesignTemplate::query()->updateOrCreate(
            ['key' => 'small-lunchbox-v1'],
            ['version' => 1, 'definition_path' => 'small-lunchbox-v1/template.json'],
        );
        $product = Product::query()->updateOrCreate(
            ['slug' => 'small-plastic-lunchbox'],
            [
                'name' => 'Small Plastic Lunchbox',
                'short_description' => 'A personalised lunchbox made for little hands, printed with their name and Kattie artwork.',
                'description' => 'A compact personalised lunchbox with a white lid and colourful base, designed for school lunches, snacks and days out. It is made from food-safe BPA-free plastic and includes a child-friendly opening tab for easy everyday use.',
                'meta_description' => 'Create a compact personalised children’s lunchbox with their name and unique Kattie artwork, ideal for school lunches, snacks and days out.',
                'is_active' => true,
                'sort_order' => 7,
                'base_price_minor' => 1950,
                'currency' => 'GBP',
                'artwork_requirements' => ['source_photo' => 'required', 'orientation' => 'portrait', 'framing' => 'full_body', 'isolated_subject' => true, 'transparent_background' => true],
                'preview_configuration' => [
                    'default_variant_options' => ['colour' => 'blue'],
                    'design_surfaces_by_variant' => ['blue' => '#ffffff', 'pink' => '#ffffff', 'white' => '#ffffff'],
                    'variant_label' => 'Lunchbox colour',
                    'design_heading' => 'Your lunchbox design',
                    'specifications' => [
                        ['label' => 'Material', 'value' => 'Food-safe BPA-free plastic with a printable aluminium lid insert'],
                        ['label' => 'Features', 'value' => 'White lid and child-friendly opening tab'],
                        ['label' => 'Dimensions', 'value' => '18 × 12.4 × 6 cm'],
                        ['label' => 'Printable area', 'value' => '16.5 × 10.2 cm'],
                        ['label' => 'Weight', 'value' => '283 g'],
                    ],
                ],
                'product_design_template_id' => $designTemplate->id,
                'recommended_artwork_style_id' => ArtworkStyle::query()->where('slug', 'storybook-cartoon')->value('id'),
            ],
        );

        $variants = [
            ['Blue', 'CATTIE-LUNCHBOX-SMALL-BLUE', 'blue', 'LUNCHBOX-BLUE'],
            ['Pink', 'CATTIE-LUNCHBOX-SMALL-PINK', 'pink', 'LUNCHBOX-PINK'],
            ['White', 'CATTIE-LUNCHBOX-SMALL-WHITE', 'white', null],
        ];
        foreach ($variants as $index => [$name, $sku, $colour, $providerSku]) {
            $variant = $product->variants()->withTrashed()->updateOrCreate(
                ['sku' => $sku],
                ['name' => $name, 'options' => ['colour' => $colour, 'size' => 'small'], 'price_minor' => 1950, 'currency' => 'GBP', 'is_active' => true, 'sort_order' => $index],
            );
            if ($variant->trashed()) {
                $variant->restore();
            }
            if ($providerSku === null) {
                $variant->fulfilmentMappings()->where('provider', 'treatpod')->delete();

                continue;
            }
            $variant->fulfilmentMappings()->updateOrCreate(
                ['provider' => 'treatpod'],
                [
                    'provider_sku' => $providerSku,
                    'configuration' => [
                        'attributes' => ['colour' => $colour, 'size' => 'small', 'material' => 'food-safe BPA-free plastic', 'weight_g' => 283],
                        'physical_product_dimensions' => ['width' => 180, 'depth' => 124, 'height' => 60, 'unit' => 'mm'],
                        'physical_print_area' => ['width' => 165, 'height' => 102, 'unit' => 'mm'],
                        'print_method' => 'printable aluminium insert',
                        'placement' => 'lid',
                        'print_areas' => ['default' => ['width' => 1949, 'height' => 1205, 'derived_from' => '165x102mm_at_300dpi', 'dpi' => 300, 'supplier_template_validated' => false]],
                    ],
                    'is_active' => true,
                ],
            );
        }
        $product->variants()->whereNotIn('sku', collect($variants)->pluck(1))->update(['is_active' => false]);
        $product->artworkStyles()->sync(ArtworkStyle::query()->whereIn('slug', ['storybook-cartoon', 'hand-drawn'])->pluck('id'));
        $product->personalisationFields()->delete();
        $product->personalisationFields()->create(['key' => 'name', 'label' => 'Name', 'type' => 'text', 'is_required' => true, 'validation_rules' => ['max' => 12], 'configuration' => [], 'sort_order' => 0]);

        $marketing = app(SyncProductMarketingAssets::class)->handle($product, resource_path('product-assets/cattie/small-plastic-lunchbox'));
        $marketingColours = collect($marketing)->pluck('variant')->unique();
        $assets = config('product-assets.suppliers.treatpod.LUNCHBOX-SMALL.assets');
        foreach ($assets as $asset) {
            $colour = $asset['variant_options']['colour'];
            $source = resource_path('product-assets/treatpod/LUNCHBOX-SMALL/'.$asset['filename']);
            Storage::disk($asset['public']['disk'])->put($asset['public']['storage_key'], file_get_contents($source));
            if ($marketingColours->contains($colour)) {
                $product->images()->where('storage_key', $asset['public']['storage_key'])->delete();

                continue;
            }
            $variant = $product->variants()->where('options->colour', $colour)->firstOrFail();
            $product->images()->updateOrCreate(
                ['storage_key' => $asset['public']['storage_key']],
                ['product_variant_id' => $variant->id, 'disk' => $asset['public']['disk'], 'alt_text' => $asset['alt_text'], 'sort_order' => $asset['sort_order']],
            );
        }
    }

    private function seedStationeryPencilTin(): void
    {
        $designTemplate = ProductDesignTemplate::query()->updateOrCreate(
            ['key' => 'stationery-pencil-tin-v1'],
            ['version' => 1, 'definition_path' => 'stationery-pencil-tin-v1/template.json'],
        );
        $product = Product::query()->updateOrCreate(
            ['slug' => 'personalised-stationery-pencil-tin'],
            [
                'name' => 'Personalised Stationery & Pencil Tin',
                'short_description' => 'A personalised metal pencil tin made for school, homework and creative little minds.',
                'description' => 'A sturdy personalised stationery tin with plenty of room for pencils, pens and small school essentials. Add their name and unique Kattie artwork to create an everyday school accessory made especially for them.',
                'meta_description' => 'Create a personalised pencil tin for children featuring their name and unique Kattie artwork â€” perfect for school, homework and creative time.',
                'is_active' => true,
                'sort_order' => 8,
                'base_price_minor' => 1795,
                'currency' => 'GBP',
                'artwork_requirements' => ['source_photo' => 'required', 'orientation' => 'portrait', 'framing' => 'full_body', 'isolated_subject' => true, 'transparent_background' => true],
                'preview_configuration' => [
                    'default_variant_options' => ['colour' => 'blue'],
                    'design_surfaces_by_variant' => ['blue' => '#ffffff', 'pink' => '#ffffff', 'silver' => '#ffffff'],
                    'variant_label' => 'Tin colour',
                    'design_heading' => 'Your pencil tin design',
                    'specifications' => [
                        ['label' => 'Material', 'value' => 'Metal stationery tin with a white glossy printable insert'],
                        ['label' => 'Dimensions', 'value' => '18.8 Ã— 8 Ã— 2.4 cm'],
                        ['label' => 'Print area', 'value' => '18.5 Ã— 7.6 cm'],
                        ['label' => 'Weight', 'value' => 'Approximately 170 g'],
                        ['label' => 'Suitable for', 'value' => 'Pencils, pens and school essentials'],
                        ['label' => 'Personalisation', 'value' => 'Name and unique Kattie artwork'],
                    ],
                ],
                'product_design_template_id' => $designTemplate->id,
                'recommended_artwork_style_id' => ArtworkStyle::query()->where('slug', 'storybook-cartoon')->value('id'),
            ],
        );

        $variants = [
            ['Blue', 'CATTIE-PENCIL-TIN-BLUE', 'blue', 'SUBSTATIONERYTIN-BLU'],
            ['Pink', 'CATTIE-PENCIL-TIN-PINK', 'pink', 'SUBSTATIONERYTIN-PNK'],
            ['Silver', 'CATTIE-PENCIL-TIN-SILVER', 'silver', 'SUBSTATIONERYTIN'],
        ];
        foreach ($variants as $index => [$name, $sku, $colour, $providerSku]) {
            $variant = $product->variants()->withTrashed()->updateOrCreate(
                ['sku' => $sku],
                ['name' => $name, 'options' => ['colour' => $colour], 'price_minor' => 1795, 'currency' => 'GBP', 'is_active' => true, 'sort_order' => $index],
            );
            if ($variant->trashed()) {
                $variant->restore();
            }
            $variant->fulfilmentMappings()->updateOrCreate(
                ['provider' => 'treatpod'],
                [
                    'provider_sku' => $providerSku,
                    'configuration' => [
                        'attributes' => ['colour' => $colour, 'material' => 'stainless steel', 'weight_g' => 170],
                        'physical_product_dimensions' => ['width' => 188, 'depth' => 80, 'height' => 24, 'unit' => 'mm'],
                        'physical_print_area' => ['width' => 185, 'height' => 76, 'unit' => 'mm'],
                        'print_method' => 'printable white glossy metal insert',
                        'placement' => 'lid insert',
                        'print_areas' => ['default' => ['width' => 2185, 'height' => 898, 'derived_from' => '185x76mm_at_300dpi', 'dpi' => 300, 'supplier_template_validated' => false]],
                    ],
                    'is_active' => true,
                ],
            );
        }
        $product->variants()->whereNotIn('sku', collect($variants)->pluck(1))->update(['is_active' => false]);
        $product->artworkStyles()->sync(ArtworkStyle::query()->whereIn('slug', ['storybook-cartoon', 'hand-drawn'])->pluck('id'));
        $product->personalisationFields()->delete();
        $product->personalisationFields()->create(['key' => 'name', 'label' => 'Name', 'type' => 'text', 'is_required' => true, 'validation_rules' => ['max' => 12], 'configuration' => [], 'sort_order' => 0]);

        $marketing = app(SyncProductMarketingAssets::class)->handle($product, resource_path('product-assets/cattie/stationery-pencil-tin'));
        $marketingColours = collect($marketing)->pluck('variant')->unique();
        foreach (config('product-assets.suppliers.treatpod.STATIONERY-PENCIL-TIN.assets') as $asset) {
            $colour = $asset['variant_options']['colour'];
            $source = resource_path('product-assets/treatpod/STATIONERY-PENCIL-TIN/'.$asset['filename']);
            Storage::disk($asset['public']['disk'])->put($asset['public']['storage_key'], file_get_contents($source));
            if ($marketingColours->contains($colour)) {
                $product->images()->where('storage_key', $asset['public']['storage_key'])->delete();

                continue;
            }
            $variant = $product->variants()->where('options->colour', $colour)->firstOrFail();
            $product->images()->updateOrCreate(
                ['storage_key' => $asset['public']['storage_key']],
                ['product_variant_id' => $variant->id, 'disk' => $asset['public']['disk'], 'alt_text' => $asset['alt_text'], 'sort_order' => $asset['sort_order']],
            );
        }
    }

    /**
     * The Custom Wall Print range: six public products (three sizes × two
     * orientations), each offering the same three frame colours as variants.
     *
     * The structure is deliberate — size and orientation each define a separate
     * product, frame colour is the only varying dimension per product, so the
     * existing single-dimension variant picker renders it cleanly with a "Frame"
     * legend. Nothing here needs a migration: sizing/orientation/frame all live
     * in the variant `options` JSON, and the £-per-size price sits on each variant.
     */
    private function seedCustomWallPrints(): void
    {
        $styleIds = ArtworkStyle::query()->whereIn('slug', ['storybook-cartoon', 'hand-drawn'])->pluck('id');
        $recommendedStyleId = ArtworkStyle::query()->where('slug', 'storybook-cartoon')->value('id');

        // Wall Prints render through the same ProductDesignTemplate → ComposedDesign →
        // RenderComposedDesign pipeline as the other products, via the character_over_context
        // templates. One template per orientation (size is carried by the variant print area).
        $portraitTemplate = ProductDesignTemplate::query()->updateOrCreate(
            ['key' => 'wall-print-v1-portrait'],
            ['version' => 1, 'definition_path' => 'wall-print-v1-portrait/template.json', 'name' => 'Wall Print (Portrait)'],
        );
        $landscapeTemplate = ProductDesignTemplate::query()->updateOrCreate(
            ['key' => 'wall-print-v1-landscape'],
            ['version' => 1, 'definition_path' => 'wall-print-v1-landscape/template.json', 'name' => 'Wall Print (Landscape)'],
        );

        // Publish the frame-mockup overlays (transparent-window frames) the Product tab lays
        // over the composed design. Placeholders — admin can replace them per variant later.
        foreach (glob(database_path('seeders/assets/wall-print-frames/*.svg')) as $asset) {
            Storage::disk('public')->put('wall-print-frames/'.basename($asset), file_get_contents($asset));
        }

        // Selling price = TreatPod framed price + £10, identical across frames and
        // across portrait/landscape. Keyed by size.
        $priceBySize = ['A4' => 2250, 'A3' => 2695, 'A2' => 2995];

        // Real TreatPod supplier SKUs, keyed "{orientation}-{size}-{frame}". The
        // internal Cattie SKU (WP-…) is a different thing and is never surfaced in
        // the storefront; this only feeds the fulfilment mapping. Verified from the
        // individual TreatPod product pages — not derived.
        $supplierSkus = [
            'portrait-A4-black' => 'PORT-PRINT-WOOD-BLK-A4-WPR749',
            'portrait-A4-natural' => 'PORT-PRINT-WOOD-NAT-A4-WPR750',
            'portrait-A4-white' => 'PORT-PRINT-WOOD-WHT-A4-WPR751',
            'portrait-A3-black' => 'PORT-PRINT-WOOD-BLK-A3-WPR756',
            'portrait-A3-natural' => 'PORT-PRINT-WOOD-NAT-A3-WPR757',
            'portrait-A3-white' => 'PORT-PRINT-WOOD-WHT-A3-WPR758',
            'portrait-A2-black' => 'PORT-PRINT-WOOD-BLK-A2-WPR763',
            'portrait-A2-natural' => 'PORT-PRINT-WOOD-NAT-A2-WPR764',
            'portrait-A2-white' => 'PORT-PRINT-WOOD-WHT-A2-WPR765',
            'landscape-A4-black' => 'LS-PRINT-WOOD-BLK-A4-WPR563',
            'landscape-A4-natural' => 'LS-PRINT-WOOD-NAT-A4-WPR564',
            'landscape-A4-white' => 'LS-PRINT-WOOD-WHT-A4-WPR565',
            'landscape-A3-black' => 'LS-PRINT-WOOD-BLK-A3-WPR567',
            'landscape-A3-natural' => 'LS-PRINT-WOOD-NAT-A3-WPR568',
            'landscape-A3-white' => 'LS-PRINT-WOOD-WHT-A3-WPR569',
            'landscape-A2-black' => 'LS-PRINT-WOOD-BLK-A2-WPR571',
            'landscape-A2-natural' => 'LS-PRINT-WOOD-NAT-A2-WPR572',
            'landscape-A2-white' => 'LS-PRINT-WOOD-WHT-A2-WPR573',
        ];

        // [frame key, variant name (also the picker label), internal SKU token]
        $frames = [
            ['black', 'Black Frame', 'BLACK'],
            ['natural', 'Natural Frame', 'NATURAL'],
            ['white', 'White Frame', 'WHITE'],
        ];

        $products = [
            [
                'name' => 'Custom A4 Wall Print', 'slug' => 'custom-a4-wall-print', 'size' => 'A4', 'orientation' => 'portrait',
                'short' => 'Turn a favourite little face, family moment or beloved pet into a beautifully framed A4 wall print — just the right size to treasure on a shelf, desk or gallery wall.',
                'description' => "Your favourite photograph, made into personalised wall art you'll want to keep forever. Printed on premium 230gsm matte photo paper and finished in a choice of solid black, natural or white frame, our A4 wall print is wonderfully versatile — perfect for a bedside shelf, a hallway gallery or a heartfelt gift. Each one is made to order here in the UK and arrives ready to hang or stand, so a moment you love is on display in minutes.",
                'meta_title' => 'Custom A4 Wall Print | Personalised Framed Wall Art | Kattie.uk',
                'meta_description' => 'Turn a favourite photo into a personalised A4 wall print, framed in black, natural or white. Premium matte print, made to order in the UK.',
            ],
            [
                'name' => 'Custom A3 Wall Print', 'slug' => 'custom-a3-wall-print', 'size' => 'A3', 'orientation' => 'portrait',
                'short' => 'A bigger, bolder personalised print. Turn a treasured photo into striking A3 framed wall art with real presence on the wall.',
                'description' => "When a photograph deserves to be seen, our A3 wall print gives it room to shine. A generous step up from A4, it makes a confident statement above a bed, along a hallway or as the centrepiece of a family gallery. Printed on premium 230gsm matte photo paper and framed in solid black, natural or white, each one is made to order in the UK and arrives ready to hang — turning a favourite face, family moment or pet into art you'll be proud to display.",
                'meta_title' => 'Custom A3 Wall Print | Personalised Framed Wall Art | Kattie.uk',
                'meta_description' => 'Create a personalised A3 wall print from a favourite photo — a bigger framed statement in black, natural or white. Premium matte, made in the UK.',
            ],
            [
                'name' => 'Custom A2 Wall Print', 'slug' => 'custom-a2-wall-print', 'size' => 'A2', 'orientation' => 'portrait',
                'short' => 'Our largest portrait print. Turn an unforgettable photo into a show-stopping A2 framed piece that anchors the whole room.',
                'description' => "For the photographs that mean the most, nothing beats the impact of A2. Our largest portrait wall print becomes a true focal point — above a sofa, in a hallway, or anywhere you want a moment you love to take centre stage. Printed on premium 230gsm matte photo paper and framed in solid black, natural or white, each A2 print is made to order in the UK and arrives ready to hang, transforming a favourite face, family or pet into a striking centrepiece.",
                'meta_title' => 'Custom A2 Wall Print | Large Personalised Framed Art | Kattie.uk',
                'meta_description' => 'Turn a favourite photo into a large personalised A2 wall print, framed in black, natural or white. Premium matte statement piece, made in the UK.',
            ],
            [
                'name' => 'Custom Landscape A4 Wall Print', 'slug' => 'custom-landscape-a4-wall-print', 'size' => 'A4', 'orientation' => 'landscape',
                'short' => 'A wide, horizontal take on personalised wall art — perfect for family groups, side-by-side pets and wider moments, framed beautifully in A4 landscape.',
                'description' => "Some moments are simply wider than they are tall — a whole family together, two pets side by side, a sweeping afternoon by the sea. Our A4 landscape wall print is made for exactly those. Printed on premium 230gsm matte photo paper and finished in a choice of solid black, natural or white frame, it's a versatile size for a shelf, desk or gallery wall. Made to order in the UK and ready to hang or stand, it turns a favourite horizontal photo into art you'll treasure.",
                'meta_title' => 'Custom Landscape A4 Wall Print | Personalised Wall Art | Kattie.uk',
                'meta_description' => 'Personalised A4 landscape wall print from your favourite wide photo, framed in black, natural or white. Premium matte, made to order in the UK.',
            ],
            [
                'name' => 'Custom Landscape A3 Wall Print', 'slug' => 'custom-landscape-a3-wall-print', 'size' => 'A3', 'orientation' => 'landscape',
                'short' => 'A bigger horizontal statement. Give family groups, pets and wide moments room to breathe in a striking A3 landscape frame.',
                'description' => "Wide moments deserve a wider canvas. Our A3 landscape wall print gives family line-ups, side-by-side pets and panoramic memories real presence on the wall — a bold step up from A4. Printed on premium 230gsm matte photo paper and framed in solid black, natural or white, each is made to order in the UK and arrives ready to hang. It's the perfect way to turn a favourite horizontal photograph into a confident centrepiece.",
                'meta_title' => 'Custom Landscape A3 Wall Print | Personalised Wall Art | Kattie.uk',
                'meta_description' => 'Create a personalised A3 landscape wall print from a favourite wide photo — framed in black, natural or white. Premium matte, made in the UK.',
            ],
            [
                'name' => 'Custom Landscape A2 Wall Print', 'slug' => 'custom-landscape-a2-wall-print', 'size' => 'A2', 'orientation' => 'landscape',
                'short' => 'Our largest landscape print. Turn a sweeping family photo or panoramic moment into a show-stopping A2 framed centrepiece.',
                'description' => "For wide moments that deserve to be seen from across the room, our A2 landscape wall print delivers real impact. It's our largest horizontal size — ideal above a sofa or sideboard, where a family gathering, a pair of beloved pets or a favourite landscape can take centre stage. Printed on premium 230gsm matte photo paper and framed in solid black, natural or white, each A2 print is made to order in the UK and arrives ready to hang, turning a treasured photograph into a striking centrepiece.",
                'meta_title' => 'Custom Landscape A2 Wall Print | Large Personalised Art | Kattie.uk',
                'meta_description' => 'Turn a favourite wide photo into a large personalised A2 landscape wall print, framed in black, natural or white. Premium matte, made in the UK.',
            ],
        ];

        foreach ($products as $position => $data) {
            $isPortrait = $data['orientation'] === 'portrait';
            $template = $isPortrait ? $portraitTemplate : $landscapeTemplate;

            $product = Product::query()->updateOrCreate(
                ['slug' => $data['slug']],
                [
                    'name' => $data['name'],
                    'short_description' => $data['short'],
                    'description' => $data['description'],
                    'meta_title' => $data['meta_title'],
                    'meta_description' => $data['meta_description'],
                    'status' => ProductStatus::Published,
                    'is_active' => true,
                    'sort_order' => 10 + $position,
                    'base_price_minor' => $priceBySize[$data['size']],
                    'currency' => 'GBP',
                    'product_design_template_id' => $template->id,
                    // One cohesive illustration: the subject stays together with its background
                    // (the whole photo is stylised), never cut out — so it never looks torn.
                    'artwork_requirements' => [
                        'source_photo' => 'required',
                        'orientation' => $data['orientation'],
                        'framing' => 'full_body',
                        'isolated_subject' => false,
                        'transparent_background' => false,
                        'composition_target' => 'wall_print',
                    ],
                    'preview_configuration' => [
                        'variant_label' => 'Frame',
                        'default_variant_options' => ['frame' => 'black'],
                        'specifications' => $this->wallPrintSpecifications($data['size'], $data['orientation']),
                        // Product-tab frame mockups per frame colour (presentation only).
                        'frame_overlays' => [
                            'black' => ['disk' => 'public', 'storage_key' => 'wall-print-frames/wall-print-frame-'.$data['orientation'].'-black.svg'],
                            'natural' => ['disk' => 'public', 'storage_key' => 'wall-print-frames/wall-print-frame-'.$data['orientation'].'-natural.svg'],
                            'white' => ['disk' => 'public', 'storage_key' => 'wall-print-frames/wall-print-frame-'.$data['orientation'].'-white.svg'],
                        ],
                    ],
                ],
            );

            $product->artworkStyles()->sync($styleIds);
            $product->update(['recommended_artwork_style_id' => $recommendedStyleId]);

            $orientationToken = $data['orientation'] === 'portrait' ? 'P' : 'L';
            $skus = [];
            foreach ($frames as $index => [$frame, $frameName, $frameToken]) {
                $sku = 'WP-'.$data['size'].'-'.$orientationToken.'-'.$frameToken;
                $skus[] = $sku;
                $variant = $product->variants()->withTrashed()->updateOrCreate(
                    ['sku' => $sku],
                    [
                        'name' => $frameName,
                        'options' => ['size' => $data['size'], 'orientation' => $data['orientation'], 'frame' => $frame],
                        'price_minor' => $priceBySize[$data['size']],
                        'currency' => 'GBP',
                        'is_active' => true,
                        'sort_order' => $index,
                    ],
                );
                if ($variant->trashed()) {
                    $variant->restore();
                }

                $supplierSku = $supplierSkus[$data['orientation'].'-'.$data['size'].'-'.$frame] ?? null;
                if ($supplierSku !== null) {
                    // print_areas drives the print-ready canvas (real TreatPod physical size
                    // × 300 DPI) via requiredPrintResolution(); the preview is downscaled
                    // separately. Frame colour never changes the print geometry.
                    $variant->fulfilmentMappings()->updateOrCreate(
                        ['provider' => 'treatpod'],
                        [
                            'provider_sku' => $supplierSku,
                            'configuration' => [
                                'attributes' => [
                                    'orientation' => $data['orientation'],
                                    'size' => $data['size'],
                                    'frame_colour' => $frame,
                                    'frame_material' => '2.5mm MDF',
                                    'paper' => '230gsm matte photo paper',
                                    'glazing' => '2mm float glass or 1.2mm styrene',
                                    'made_in' => 'United Kingdom',
                                ],
                                ...$this->wallPrintPrintGeometry($data['size'], $isPortrait),
                            ],
                            'is_active' => true,
                        ],
                    );
                }
            }
            // Retire any stray variant no longer in the matrix (idempotent cleanup).
            $product->variants()->whereNotIn('sku', $skus)->update(['is_active' => false]);

            // Placeholder marketing image — added only when the product has none, so
            // a later admin upload is never overwritten on re-seed. The SVG is
            // published to demo/catalogue by the run() asset loop.
            if ($product->images()->count() === 0) {
                $product->images()->create([
                    'disk' => 'public',
                    'storage_key' => 'demo/catalogue/wall-print-placeholder.svg',
                    'alt_text' => $data['name'].' — personalised framed wall art',
                    'sort_order' => 0,
                ]);
            }
        }
    }

    /**
     * Real, TreatPod-sourced technical specs for a wall print, shaped for
     * preview_configuration.specifications. Size drives the print dimensions and
     * the hanging hardware; everything else is common to the range.
     *
     * @return array<int, array{label: string, value: string}>
     */
    private function wallPrintSpecifications(string $size, string $orientation): array
    {
        $printSize = ['A4' => 'A4 · 29.7 × 21 cm', 'A3' => 'A3 · 42 × 29.7 cm', 'A2' => 'A2 · 59.4 × 42 cm'][$size];
        $hanging = $size === 'A4'
            ? 'Strut back — hang or stand, both ways'
            : 'Hinged cobra hangers — hang both ways';

        return [
            ['label' => 'Orientation', 'value' => $orientation === 'portrait' ? 'Portrait' : 'Landscape'],
            ['label' => 'Print size', 'value' => $printSize],
            ['label' => 'Paper', 'value' => '230gsm matte photo paper'],
            ['label' => 'Frame', 'value' => '2.5mm MDF — choose black, natural or white'],
            ['label' => 'Glazing', 'value' => '2mm float glass or 1.2mm styrene'],
            ['label' => 'Hanging', 'value' => $hanging],
            ['label' => 'Made in', 'value' => 'United Kingdom'],
        ];
    }

    /**
     * Print-ready canvas geometry with bleed. The renderer sizes the canvas from the
     * WITH-BLEED dimensions (trim + 6.5 mm on each of the four sides = +13 mm per axis)
     * at 300 DPI, so the background is composed edge-to-edge including the bleed; the
     * character stays inside the trim/safe area via the template's safe zones.
     *
     * @return array{physical_print_area: array<string, mixed>, print_areas: array<string, mixed>}
     */
    private function wallPrintPrintGeometry(string $size, bool $isPortrait): array
    {
        // ISO trim size as [short_mm, long_mm].
        [$trimShort, $trimLong] = ['A4' => [210, 297], 'A3' => [297, 420], 'A2' => [420, 594]][$size];
        $bleedMm = 6.5;
        $bleedShort = $trimShort + 2 * $bleedMm;
        $bleedLong = $trimLong + 2 * $bleedMm;
        $px = fn (float $mm): int => (int) round($mm * 300 / 25.4);

        $widthMm = $isPortrait ? $bleedShort : $bleedLong;
        $heightMm = $isPortrait ? $bleedLong : $bleedShort;

        return [
            'physical_print_area' => [
                'trim_width_mm' => $isPortrait ? $trimShort : $trimLong,
                'trim_height_mm' => $isPortrait ? $trimLong : $trimShort,
                'bleed_mm' => $bleedMm,
                'width_mm' => $widthMm,   // trim + bleed on both sides
                'height_mm' => $heightMm,
                'dpi' => 300,
                'source' => 'treatpod',
            ],
            'print_areas' => [
                'default' => ['width' => $px($widthMm), 'height' => $px($heightMm), 'dpi' => 300],
            ],
        ];
    }
}
