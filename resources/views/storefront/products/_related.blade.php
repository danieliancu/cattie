@if($relatedProducts->isNotEmpty())
    <section class="shell mt-20 sm:mt-24" aria-labelledby="related-products-title">
        <h2 id="related-products-title" class="font-display text-3xl sm:text-4xl">You might also like</h2>
        <div class="mt-9 grid gap-x-8 gap-y-14 sm:grid-cols-2 lg:grid-cols-4">
            @foreach($relatedProducts as $relatedProduct)
                <x-product-card :product="$relatedProduct" />
            @endforeach
        </div>
    </section>
@endif
