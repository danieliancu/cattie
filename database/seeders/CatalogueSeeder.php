<?php

namespace Database\Seeders;

use App\Domain\Catalogue\Actions\SyncProductMarketingAssets;
use App\Models\ArtworkStyle;
use App\Models\Product;
use App\Models\ProductCategory;
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
        $this->seedProductCategories();
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
                'name' => 'Cattie Water Bottle',
                'short_description' => 'A future personalised 650 ml insulated bottle.',
                'description' => 'Inactive catalogue placeholder for the first Prodigi-backed Cattie bottle.',
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
                'description' => 'A lightweight 750 ml aluminium water bottle with a red flip lid, personalised with their name and Cattie artwork. The wraparound design makes it a colourful everyday bottle for school, sports and days out. The Silver option has a subtle shimmering finish.',
                'meta_description' => 'Create a personalised 750 ml aluminium water bottle with a red flip lid, name and custom Cattie artwork.',
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

    private function seedProductCategories(): void
    {
        $categories = collect([
            [
                'name' => 'School & Lunch',
                'slug' => 'school-lunch',
                'short_description' => 'Personalised everyday essentials for school, lunches and days out.',
                'meta_title' => 'Personalised School & Lunch Gifts for Kids | Cattie.uk',
                'meta_description' => 'Shop personalised school and lunch gifts for children, created with their name and unique Cattie artwork.',
            ],
            [
                'name' => 'Kids Drinkware',
                'slug' => 'kids-drinkware',
                'short_description' => 'Personalised bottles and drinkware made especially for children.',
                'meta_title' => 'Personalised Kids Drinkware | Cattie.uk',
                'meta_description' => 'Discover personalised kids drinkware featuring their name and unique artwork, designed for school, sports and everyday adventures.',
            ],
            [
                'name' => 'School Accessories',
                'slug' => 'school-accessories',
                'short_description' => 'Personalised school accessories made for lessons, homework and creative little minds.',
                'meta_title' => 'Personalised School Accessories for Kids | Cattie.uk',
                'meta_description' => 'Shop personalised school accessories for children, created with their name and unique Cattie artwork.',
            ],
        ])->map(function (array $data, int $position) {
            return ProductCategory::query()->updateOrCreate(
                ['slug' => $data['slug']],
                $data + ['description' => null, 'is_active' => true, 'sort_order' => $position],
            );
        });

        $product = Product::query()->where('slug', 'water-bottle-with-red-flip-lid')->firstOrFail();
        $product->categories()->sync($categories
            ->whereIn('slug', ['school-lunch', 'kids-drinkware'])
            ->values()
            ->mapWithKeys(fn (ProductCategory $category, int $position) => [
                $category->id => ['sort_order' => $position],
            ])->all());

        Product::query()->where('slug', 'small-plastic-lunchbox')->firstOrFail()->categories()->sync([
            $categories->firstWhere('slug', 'school-lunch')->id => ['sort_order' => 1],
        ]);

        Product::query()->where('slug', 'personalised-stationery-pencil-tin')->firstOrFail()->categories()->sync([
            $categories->firstWhere('slug', 'school-accessories')->id => ['sort_order' => 0],
            $categories->firstWhere('slug', 'school-lunch')->id => ['sort_order' => 1],
        ]);
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
                'short_description' => 'A personalised lunchbox made for little hands, printed with their name and Cattie artwork.',
                'description' => 'A compact personalised lunchbox with a white lid and colourful base, designed for school lunches, snacks and days out. It is made from food-safe BPA-free plastic and includes a child-friendly opening tab for easy everyday use.',
                'meta_description' => 'Create a compact personalised children’s lunchbox with their name and unique Cattie artwork, ideal for school lunches, snacks and days out.',
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
                'description' => 'A sturdy personalised stationery tin with plenty of room for pencils, pens and small school essentials. Add their name and unique Cattie artwork to create an everyday school accessory made especially for them.',
                'meta_description' => 'Create a personalised pencil tin for children featuring their name and unique Cattie artwork â€” perfect for school, homework and creative time.',
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
                        ['label' => 'Personalisation', 'value' => 'Name and unique Cattie artwork'],
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
}
