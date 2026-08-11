<?php

namespace Database\Seeders;

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

        $this->seedDormantWaterBottle();
    }

    private function seedDormantWaterBottle(): void
    {
        $designTemplate = ProductDesignTemplate::query()->updateOrCreate(
            ['key' => 'bottle-wrap-v1'],
            ['version' => 3, 'definition_path' => 'bottle-wrap-v1/template.json'],
        );

        $product = Product::query()->updateOrCreate(
            ['slug' => 'cattie-water-bottle'],
            [
                'name' => 'Cattie Water Bottle',
                'short_description' => 'A future personalised 650 ml insulated bottle.',
                'description' => 'Inactive catalogue placeholder for the first Prodigi-backed Cattie bottle.',
                'meta_description' => null,
                'is_active' => true,
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
}
