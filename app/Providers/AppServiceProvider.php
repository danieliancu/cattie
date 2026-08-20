<?php

namespace App\Providers;

use App\Contracts\AddressLookupProvider;
use App\Contracts\BackgroundRemovalRunner;
use App\Contracts\ImageGenerationProvider;
use App\Contracts\ImageUpscaler;
use App\Contracts\PaymentProvider;
use App\Contracts\PhotoModerator;
use App\Domain\Cart\Actions\ResolveGuestCart;
use App\Domain\Payments\Contracts\ShippingResolver;
use App\Domain\Payments\Contracts\TaxResolver;
use App\Domain\Payments\Resolvers\OrderShippingMethodResolver;
use App\Domain\Payments\Resolvers\ZeroUkTaxResolver;
use App\Models\FulfilmentProductMapping;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Observers\FulfilmentProductMappingObserver;
use App\Observers\ProductCategoryObserver;
use App\Observers\ProductObserver;
use App\Support\CatalogueNavigation;
use App\Providers\AddressLookup\GooglePlacesAddressLookupProvider;
use App\Providers\AddressLookup\HomedataAddressLookupProvider;
use App\Providers\AddressLookup\PostcodesIoAddressLookupProvider;
use App\Providers\ImageGeneration\FakeImageGenerationProvider;
use App\Providers\ImageGeneration\OpenAiImageGenerationProvider;
use App\Providers\Payments\FakePaymentProvider;
use App\Providers\Payments\StripePaymentProvider;
use App\Integrations\Stripe\SdkStripeGateway;
use App\Integrations\Stripe\StripeGateway;
use App\Services\AllowAllPhotoModerator;
use App\Services\LocalBackgroundRemovalRunner;
use App\Services\LocalImageUpscaler;
use App\Services\OpenAiPhotoModerator;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(StripeGateway::class, SdkStripeGateway::class);
        $this->app->bind(BackgroundRemovalRunner::class, LocalBackgroundRemovalRunner::class);
        $this->app->bind(ImageUpscaler::class, LocalImageUpscaler::class);
        $this->app->bind(ImageGenerationProvider::class, fn () => match (config('artwork.provider')) {
            'openai' => new OpenAiImageGenerationProvider,
            'fake' => new FakeImageGenerationProvider,
            default => throw new RuntimeException('Unsupported image generation provider.'),
        });
        $this->app->bind(PhotoModerator::class, fn () => match (config('artwork.moderation.provider')) {
            'openai' => new OpenAiPhotoModerator,
            default => new AllowAllPhotoModerator,
        });
        $this->app->bind(PaymentProvider::class, fn () => match (config('payments.provider')) {
            'fake' => new FakePaymentProvider,
            'stripe' => $this->app->make(StripePaymentProvider::class),
            default => throw new RuntimeException('Unsupported payment provider.')
        });
        $this->app->bind(ShippingResolver::class, OrderShippingMethodResolver::class);
        $this->app->bind(TaxResolver::class, fn () => match (config('payments.tax.strategy')) {
            'zero_uk' => new ZeroUkTaxResolver, default => throw new RuntimeException('Unsupported tax strategy.')
        });
        $this->app->bind(AddressLookupProvider::class, fn () => match (config('address_lookup.provider')) {
            'postcodes_io' => new PostcodesIoAddressLookupProvider,
            'homedata' => new HomedataAddressLookupProvider,
            'google_places' => new GooglePlacesAddressLookupProvider,
            default => throw new RuntimeException('Unsupported address lookup provider.'),
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Product::observe(ProductObserver::class);
        ProductCategory::observe(ProductCategoryObserver::class);
        FulfilmentProductMapping::observe(FulfilmentProductMappingObserver::class);
        View::composer('layouts.storefront', function ($view) {
            [$cart] = app(ResolveGuestCart::class)->handle(request(), false);
            $view->with([
                'basketItemCount' => (int) ($cart?->items()->sum('quantity') ?? 0),
                'searchProducts' => Product::query()->active()->ordered()->get(['name', 'slug'])
                    ->map(fn (Product $product) => ['name' => $product->name, 'url' => route('products.show', $product->slug)])
                    ->values(),
                'navCategories' => CatalogueNavigation::topLevelWithChildren(),
            ]);
        });
    }
}
