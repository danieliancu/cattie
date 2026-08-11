<?php

namespace App\Providers;

use App\Contracts\ImageGenerationProvider;
use App\Contracts\PaymentProvider;
use App\Domain\Payments\Contracts\ShippingResolver;
use App\Domain\Payments\Contracts\TaxResolver;
use App\Domain\Payments\Resolvers\FreeUkShippingResolver;
use App\Domain\Payments\Resolvers\ZeroUkTaxResolver;
use App\Providers\ImageGeneration\FakeImageGenerationProvider;
use App\Providers\ImageGeneration\OpenAiImageGenerationProvider;
use App\Providers\Payments\FakePaymentProvider;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(ImageGenerationProvider::class, fn () => match (config('artwork.provider')) {
            'openai' => new OpenAiImageGenerationProvider, default => new FakeImageGenerationProvider
        });
        $this->app->bind(PaymentProvider::class, fn () => match (config('payments.provider')) {
            'fake' => new FakePaymentProvider, default => throw new RuntimeException('Unsupported payment provider.')
        });
        $this->app->bind(ShippingResolver::class, fn () => match (config('payments.shipping.strategy')) {
            'free_uk' => new FreeUkShippingResolver, default => throw new RuntimeException('Unsupported shipping strategy.')
        });
        $this->app->bind(TaxResolver::class, fn () => match (config('payments.tax.strategy')) {
            'zero_uk' => new ZeroUkTaxResolver, default => throw new RuntimeException('Unsupported tax strategy.')
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
