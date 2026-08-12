@if($relatedProducts->isNotEmpty())
    <section class="shell mt-20 sm:mt-24" aria-labelledby="related-products-title">
        <h2 id="related-products-title" class="font-display text-3xl sm:text-4xl">You might also like</h2>
        <div class="mt-9 grid grid-cols-2 gap-x-4 gap-y-10 sm:gap-x-8 sm:gap-y-14 lg:grid-cols-4">
            @foreach($relatedProducts as $relatedProduct)
                <x-product-card :product="$relatedProduct" />
            @endforeach
        </div>
    </section>
@endif
