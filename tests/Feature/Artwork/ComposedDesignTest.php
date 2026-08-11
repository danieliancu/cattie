<?php

namespace Tests\Feature\Artwork;

use App\Domain\Artwork\Actions\ApproveArtwork;
use App\Domain\Artwork\Actions\RenderComposedDesign;
use App\Domain\Artwork\Actions\StartArtworkSession;
use App\Domain\Cart\Actions\AddApprovedArtworkToCart;
use App\Enums\ArtworkSessionStatus;
use App\Enums\GenerationStatus;
use App\Models\Cart;
use App\Models\ComposedDesign;
use App\Models\Product;
use Database\Seeders\CatalogueSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ComposedDesignTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        ini_set('memory_limit', '256M');
        Storage::fake('local');
        Storage::fake('public');
        $this->seed(CatalogueSeeder::class);
    }

    public function test_variant_dimensions_are_exact_and_preview_is_smaller_and_private(): void
    {
        foreach (['black' => [2750, 2279], 'grey' => [2750, 2279], 'navy' => [2750, 2279], 'red' => [2750, 2279]] as $colour => [$width, $height]) {
            [$session, $asset] = $this->inputs($colour);
            $design = app(RenderComposedDesign::class)->handle($session, $asset);
            $this->assertSame([$width, $height], [$design->width, $design->height]);
            $this->assertSame([$width, $height], array_slice(getimagesizefromstring(Storage::disk('local')->get($design->storage_key)), 0, 2));
            [$previewWidth, $previewHeight] = getimagesizefromstring(Storage::disk('local')->get($design->preview_storage_key));
            $this->assertLessThanOrEqual(1200, max($previewWidth, $previewHeight));
            $this->assertStringStartsWith("artwork-sessions/{$session->public_id}/composed-designs/", $design->storage_key);
            Storage::disk('public')->assertMissing($design->storage_key);
        }
    }

    public function test_missing_prodigi_resolution_fails_without_a_universal_fallback(): void
    {
        [$session, $asset] = $this->inputs('black');
        $session->variant->fulfilmentMappings()->delete();

        $this->expectException(DomainException::class);
        app(RenderComposedDesign::class)->handle($session->fresh(), $asset);
    }

    public function test_normalized_coordinates_and_contain_preserve_aspect_ratio(): void
    {
        $renderer = app(RenderComposedDesign::class);
        $this->assertSame(['x' => 798, 'y' => 182, 'width' => 1155, 'height' => 1914], $renderer->pixelRect(['x' => .29, 'y' => .08, 'max_width' => .42, 'max_height' => .84], 2750, 2279));
        $size = $renderer->containedSize(600, 1200, 1155, 1914);
        $this->assertEqualsWithDelta(.5, $size['width'] / $size['height'], .001);
        $this->assertLessThanOrEqual(1155, $size['width']);
        $this->assertLessThanOrEqual(1914, $size['height']);
    }

    public function test_bottle_colour_selects_the_exact_flat_background_theme(): void
    {
        foreach (['black' => '#17191f', 'grey' => '#777d86', 'navy' => '#18558b', 'red' => '#bd2738'] as $colour => $expected) {
            [$session, $asset] = $this->inputs($colour);
            $design = app(RenderComposedDesign::class)->handle($session, $asset);
            $image = imagecreatefromstring(Storage::disk('local')->get($design->storage_key));
            $this->assertSame(hexdec(substr($expected, 1)), imagecolorat($image, 0, 0) & 0xFFFFFF, "Unexpected background for {$colour}.");
            imagedestroy($image);
        }
    }

    public function test_reference_layout_and_character_adjustments_are_explicit_config(): void
    {
        $product = Product::query()->where('slug', 'cattie-water-bottle')->firstOrFail();
        $definition = $product->designTemplate->definition();
        $pattern = collect($definition['layers'])->firstWhere('type', 'personalisation_text_pattern');
        $this->assertSame(['bold', 'serif', 'script'], array_keys($pattern['styles']));
        $this->assertCount(30, $pattern['items']);
        $this->assertContains(90, collect($pattern['items'])->pluck('rotation')->all());
        $this->assertSame(['x', 'y', 'scale', 'offset_x', 'offset_y', 'max_width', 'max_height'], array_keys($definition['character']));

        $renderer = app(RenderComposedDesign::class);
        $base = $renderer->characterBox($definition['character'], 1000, 800);
        $adjusted = $renderer->characterBox([...$definition['character'], 'scale' => .8, 'offset_x' => .1, 'offset_y' => -.1], 1000, 800);
        $this->assertLessThan($base['width'], $adjusted['width']);
        $this->assertGreaterThan($base['x'], $adjusted['x']);
        $this->assertLessThan($base['y'], $adjusted['y']);
    }

    public function test_alpha_artwork_and_repeated_name_are_composed(): void
    {
        [$session, $asset] = $this->inputs('black');
        $design = app(RenderComposedDesign::class)->handle($session, $asset);
        $image = imagecreatefromstring(Storage::disk('local')->get($design->storage_key));
        $pink = imagecolorat($image, 0, 0) & 0xFFFFFF;
        $differentPixels = 0;
        for ($x = 300; $x < 700; $x += 40) {
            for ($y = 300; $y < 1800; $y += 60) {
                $differentPixels += ((imagecolorat($image, $x, $y) & 0xFFFFFF) !== $pink) ? 1 : 0;
            }
        }
        imagedestroy($image);
        $this->assertGreaterThan(5, $differentPixels, 'The deterministic name pattern should alter the pink background.');
    }

    public function test_mixed_typography_is_offline_deterministic_and_clipped_to_the_safe_zone(): void
    {
        [$session, $asset] = $this->inputs('black');
        $session->update(['personalisation_snapshot' => [['key' => 'name', 'label' => 'Name', 'type' => 'text', 'value' => 'María-Andreea Foarte Lung']]]);
        $renderer = app(RenderComposedDesign::class);
        $fonts = $renderer->resolvedFontPaths();
        $this->assertSame(['script', 'serif', 'sans-bold'], array_keys($fonts));
        $this->assertCount(3, array_unique($fonts));
        foreach ($fonts as $font) {
            $this->assertFileExists($font);
        }

        $first = $renderer->handle($session->fresh(), $asset);
        $second = $renderer->handle($session->fresh(), $asset);
        $firstBytes = Storage::disk('local')->get($first->storage_key);
        $this->assertSame(hash('sha256', $firstBytes), hash('sha256', Storage::disk('local')->get($second->storage_key)));
        $image = imagecreatefromstring($firstBytes);
        $pink = imagecolorat($image, 0, 0) & 0xFFFFFF;
        foreach ([[10, 10], [2740, 10], [10, 2269], [2740, 2269]] as [$x, $y]) {
            $this->assertSame($pink, imagecolorat($image, $x, $y) & 0xFFFFFF);
        }
        imagedestroy($image);
    }

    public function test_artwork_page_uses_prodigi_examples_and_has_no_design_history(): void
    {
        [$session, $firstAsset] = $this->inputs('black', 'gallery-owner');
        $renderer = app(RenderComposedDesign::class);
        $renderer->handle($session, $firstAsset);
        $renderer->handle($session, $firstAsset);

        $response = $this->withCookie('cattie_guest_token', 'gallery-owner')->get(route('products.show', $session->product->slug));
        $response->assertOk()->assertSee('Your bottle design')->assertSee('Product')->assertDontSee('Artwork only')->assertDontSee('Try another version')->assertSee('Bottle colour')->assertSee('Bottle examples')->assertSee('Official Prodigi product photography')->assertDontSee('Previous designs')
            ->assertSee('About your gift')->assertSee('99 Day')->assertSee('Secure')->assertSee('Privacy')
            ->assertSee('Shipping &amp; Returns', false)->assertSee('99 Days Return')->assertSee('Delivery');
        $response->assertSee("previewMode = 'product'; exampleUrl =", false)->assertSee('aspect-[6/5]', false);
        foreach (['catalogue/bottle01.jpg', 'catalogue/bottle02.jpg', 'catalogue/close-up.jpg', 'catalogue/lid.jpg'] as $path) {
            $response->assertSee($path, false);
        }

        $secondGeneration = $firstAsset->generation->replicate();
        $secondGeneration->idempotency_key = null;
        $secondGeneration->save();
        $secondKey = 'generated/'.uniqid().'.png';
        Storage::disk('local')->put($secondKey, Storage::disk('local')->get($firstAsset->storage_key));
        $secondAsset = $secondGeneration->assets()->create(['kind' => 'provider_original', 'disk' => 'local', 'storage_key' => $secondKey, 'mime_type' => 'image/png']);
        $secondDesign = $renderer->handle($session, $secondAsset);
        $history = $this->withCookie('cattie_guest_token', 'gallery-owner')->get(route('products.show', $session->product->slug));
        $history->assertOk()->assertDontSee('Previous designs')->assertDontSee('Adjust your character')
            ->assertSee('selectedDesignUrl', false)->assertSee('selectedDesignId', false)
            ->assertDontSee('action="'.route('artwork.approve', $session->public_id).'"', false);
        foreach (['Black', 'Gray', 'Navy', 'Red'] as $name) {
            $history->assertSee($name);
        }
        $history->assertSee('<small class="block">650 ml</small><small class="block">£16.50</small>', false);

        $this->withCookie('cattie_guest_token', 'gallery-owner')->post(route('artwork.change', $session->public_id), ['design_id' => $secondDesign->id])->assertRedirect();
        $this->assertSame($secondGeneration->id, $session->fresh()->current_generation_id);
        $this->withCookie('cattie_guest_token', 'gallery-owner')->post(route('artwork.upload', $session->public_id), [
            'variant_id' => $session->product_variant_id,
            'artwork_style_id' => $session->artwork_style_id,
            'personalisation' => ['name' => 'Maria'],
        ])->assertRedirect();
        $this->assertSame(2, $session->fresh()->generations()->count(), 'An existing AI thumbnail must be reused without generating again.');
        $returningHistory = $this->withCookie('cattie_guest_token', 'gallery-owner')->get(route('products.show', $session->product->slug));
        $returningHistory->assertOk()->assertDontSee('Previous designs');
        $this->assertSame(2, $session->fresh()->composedDesigns()->distinct()->count('generation_asset_id'));
    }

    public function test_history_regeneration_variant_change_and_approval_keep_both_facts(): void
    {
        [$session, $firstAsset] = $this->inputs('black');
        $first = app(RenderComposedDesign::class)->handle($session, $firstAsset);
        $second = app(RenderComposedDesign::class)->handle($session, $firstAsset);
        $this->assertNotSame($first->id, $second->id);

        $lime = $session->product->variants->first(fn ($variant) => $variant->options['colour'] === 'red');
        $session->update(['product_variant_id' => $lime->id]);
        $limeDesign = app(RenderComposedDesign::class)->handle($session->fresh(), $firstAsset);
        $this->assertSame([2750, 2279], [$limeDesign->width, $limeDesign->height]);
        $this->assertDatabaseCount('composed_designs', 3);

        app(ApproveArtwork::class)->handle($session->fresh(), $firstAsset, $limeDesign);
        $session->refresh();
        $this->assertSame($firstAsset->id, $session->approved_generation_asset_id);
        $this->assertSame($limeDesign->id, $session->approved_composed_design_id);
        $this->assertTrue($firstAsset->fresh()->is_selected);
    }

    public function test_template_product_cart_requires_and_snapshots_approved_design(): void
    {
        [$session, $asset] = $this->inputs('black');
        $cart = Cart::query()->create(['status' => 'active', 'currency' => 'GBP']);
        $session->update(['status' => ArtworkSessionStatus::Approved, 'approved_generation_asset_id' => $asset->id]);
        $asset->update(['is_selected' => true]);
        try {
            app(AddApprovedArtworkToCart::class)->handle($cart, $session->fresh());
            $this->fail('A template-backed product must require a composed design.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('artwork', $e->errors());
        }

        $session->update(['status' => ArtworkSessionStatus::PreviewReady, 'approved_generation_asset_id' => null]);
        $design = app(RenderComposedDesign::class)->handle($session->fresh(), $asset);
        app(ApproveArtwork::class)->handle($session->fresh(), $asset, $design);
        $item = app(AddApprovedArtworkToCart::class)->handle($cart, $session->fresh());
        $this->assertSame($design->id, $item->composed_design_id);
    }

    public function test_design_preview_has_same_ownership_boundary_as_ai_assets(): void
    {
        [$session, $asset] = $this->inputs('black', 'owner-token');
        $design = app(RenderComposedDesign::class)->handle($session, $asset);
        $this->withCookie('cattie_guest_token', 'intruder')->get(route('artwork.designs', [$session->public_id, $design]))->assertNotFound();
        $response = $this->withCookie('cattie_guest_token', 'owner-token')->get(route('artwork.designs', [$session->public_id, $design]));
        $response->assertOk();
        $this->assertStringContainsString('private', $response->headers->get('Cache-Control'));
    }

    public function test_character_layout_editor_rerenders_without_ai_and_is_private(): void
    {
        [$session, $asset] = $this->inputs('black', 'layout-owner');
        $design = app(RenderComposedDesign::class)->handle($session, $asset);
        $payload = ['scale' => 1.35, 'offset_x' => .12, 'offset_y' => -.08];

        $response = $this->withCookie('cattie_guest_token', 'layout-owner')->post(route('artwork.design-layout', [$session->public_id, $design]), $payload, ['Accept' => 'application/json']);

        $response->assertOk()->assertJsonStructure(['design_id', 'asset_id', 'preview_url', 'layout_url', 'background_url']);
        $updated = ComposedDesign::query()->findOrFail($response->json('design_id'));
        $this->assertSame($asset->id, $updated->generation_asset_id);
        $this->assertSame($payload, $updated->character_adjustments);
        Storage::disk('local')->assertExists($updated->editor_background_storage_key);
        $this->assertSame(1, $session->generations()->count(), 'Layout changes must not run AI again.');

        $page = $this->withCookie('cattie_guest_token', 'layout-owner')->get(route('products.show', $session->product->slug));
        $page->assertOk()->assertDontSee('Adjust your character')->assertDontSee('Drag the rectangle to move.')
            ->assertSee('Resize character')->assertSee('Resize character from left')->assertSee('Resize character from right')
            ->assertDontSee('Previous designs')->assertSee('Bottle colour')
            ->assertSee('x-show="previewMode === \'design\'" class="pointer-events-none relative', false)
            ->assertSee('beginTransform($event, \'move\')', false)->assertSee('async chooseVariant(variantId)', false);

        $nextVariant = $session->product->variants->first(fn ($variant) => $variant->is_active && $variant->id !== $session->product_variant_id);
        $colourResponse = $this->withCookie('cattie_guest_token', 'layout-owner')->post(route('artwork.variant', $session->public_id), [
            'variant_id' => $nextVariant->id,
            'design_id' => $updated->id,
        ], ['Accept' => 'application/json']);
        $colourResponse->assertOk()->assertJsonStructure(['variant_id', 'design_id', 'preview_url', 'layout_url', 'background_url']);
        $colourDesign = ComposedDesign::query()->findOrFail($colourResponse->json('design_id'));
        $this->assertSame($payload, $colourDesign->character_adjustments);

        $nameResponse = $this->withCookie('cattie_guest_token', 'layout-owner')->post(route('artwork.name', $session->public_id), [
            'name' => 'Mia Rose',
            'design_id' => $colourDesign->id,
        ], ['Accept' => 'application/json']);
        $nameResponse->assertOk()->assertJsonStructure(['name', 'design_id', 'preview_url', 'layout_url', 'background_url']);
        $nameDesign = ComposedDesign::query()->findOrFail($nameResponse->json('design_id'));
        $this->assertSame($payload, $nameDesign->character_adjustments);
        $this->assertSame('Mia Rose', collect($session->fresh()->personalisation_snapshot)->firstWhere('key', 'name')['value']);
        $this->assertSame(1, $session->fresh()->generations()->count(), 'Changing the name must not run AI again.');

        $updatedPage = $this->withCookie('cattie_guest_token', 'layout-owner')->get(route('products.show', $session->product->slug));
        $updatedPage->assertOk()->assertSee('x-model="nameValue"', false)->assertSee('Mia Rose', false)
            ->assertSee('@input="scheduleNameUpdate()"', false)->assertSee('12 - nameValue.length', false);

        $emptyNameResponse = $this->withCookie('cattie_guest_token', 'layout-owner')->post(route('artwork.name', $session->public_id), [
            'name' => '',
            'design_id' => $nameDesign->id,
        ], ['Accept' => 'application/json']);
        $emptyNameResponse->assertOk()->assertJsonPath('name', '');
        $this->assertSame('', collect($session->fresh()->personalisation_snapshot)->firstWhere('key', 'name')['value']);
        $this->assertSame(1, $session->fresh()->generations()->count(), 'Clearing the name must not run AI again.');

        $this->withCookie('cattie_guest_token', 'intruder')->post(route('artwork.design-layout', [$session->public_id, $design]), $payload, ['Accept' => 'application/json'])->assertNotFound();
        $this->withCookie('cattie_guest_token', 'intruder')->post(route('artwork.name', $session->public_id), ['name' => 'Intruder', 'design_id' => $nameDesign->id], ['Accept' => 'application/json'])->assertNotFound();
    }

    public function test_change_artwork_from_basket_returns_cattie_to_the_editable_design_workspace(): void
    {
        [$session, $asset] = $this->inputs('black', 'basket-editor');
        $design = app(RenderComposedDesign::class)->handle($session, $asset, ['scale' => 1.2, 'offset_x' => .05, 'offset_y' => -.03]);

        $this->withCookie('cattie_guest_token', 'basket-editor')->post(route('artwork.cart', $session->public_id), [
            'asset_id' => $asset->id,
            'design_id' => $design->id,
        ])->assertRedirect(route('cart.index'));
        $item = Cart::query()->firstOrFail()->items()->firstOrFail();

        $this->withCookie('cattie_guest_token', 'basket-editor')->post(route('cart.change-artwork', $item))
            ->assertRedirect(route('products.show', $session->product->slug));

        $this->assertSame(ArtworkSessionStatus::PreviewReady, $session->fresh()->status);
        $this->assertSame(1, $session->fresh()->generations()->count());
        $this->withCookie('cattie_guest_token', 'basket-editor')->get(route('products.show', $session->product->slug))
            ->assertOk()->assertSee('Your bottle design')->assertSee('Bottle colour')
            ->assertSee('id="artwork-name"', false)->assertSee('x-model="nameValue"', false)->assertSee('Maria')
            ->assertSee('Resize character')->assertDontSee('Upload your photo');
    }

    private function inputs(string $colour, string $token = 'owner-token'): array
    {
        $product = Product::query()->where('slug', 'cattie-water-bottle')->with(['variants', 'artworkStyles'])->firstOrFail();
        $variant = $product->variants->first(fn ($variant) => $variant->options['colour'] === $colour);
        $style = $product->artworkStyles->first();
        [$session] = app(StartArtworkSession::class)->handle($product, ['variant_id' => $variant->id, 'artwork_style_id' => $style->id, 'personalisation' => ['name' => 'Maria']], $token);
        $upload = $session->uploads()->create(['disk' => 'local', 'storage_key' => 'source-'.uniqid().'.png', 'mime_type' => 'image/png', 'size_bytes' => 1, 'sha256' => hash('sha256', uniqid())]);
        $generation = $session->generations()->create(['upload_id' => $upload->id, 'product_id' => $product->id, 'product_variant_id' => $variant->id, 'artwork_style_id' => $style->id, 'prompt_key' => 'test', 'prompt_version' => 1, 'resolved_prompt' => 'test', 'provider' => 'fake', 'model' => 'fake', 'status' => GenerationStatus::Succeeded, 'cost_currency' => 'GBP']);
        $key = 'generated/'.uniqid().'.png';
        Storage::disk('local')->put($key, file_get_contents(database_path('seeders/assets/fake-artwork/fake-artwork-a.png')));
        $asset = $generation->assets()->create(['kind' => 'provider_original', 'disk' => 'local', 'storage_key' => $key, 'mime_type' => 'image/png']);
        $session->update(['current_generation_id' => $generation->id, 'status' => ArtworkSessionStatus::PreviewReady]);

        return [$session->fresh(['product.variants', 'variant']), $asset];
    }
}
