<div class="mt-5">
    <div class="flex items-center gap-3">
        <i data-lucide="map" class="h-6 w-6 shrink-0 text-coral" stroke-width="1.5" aria-hidden="true"></i>
        <h3 class="font-display text-xl">{{ $productionHeading }}</h3>
    </div>
    <p class="mt-2 leading-7 text-muted">{{ $productionMessage }}</p>
</div>
<div class="mt-5 overflow-x-auto rounded-2xl border border-rose/25">
    <table class="min-w-full border-collapse text-left text-sm">
        <caption class="sr-only">Estimated total United Kingdom delivery times and prices</caption>
        <thead class="bg-sand text-ink">
            <tr>
                <th scope="col" class="whitespace-nowrap px-4 py-3 font-bold">Price</th>
                <th scope="col" class="whitespace-nowrap px-4 py-3 font-bold">Estimated delivery</th>
                <th scope="col" class="whitespace-nowrap px-4 py-3 font-bold">Delivery method</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-rose/20 bg-white text-muted">
            @foreach($methods as $method)
                <tr>
                    <th scope="row" class="whitespace-nowrap px-4 py-3 font-semibold text-ink">{{ $method['price'] }}</th>
                    <td class="whitespace-nowrap px-4 py-3">{{ $method['estimated_delivery'] }}</td>
                    <td class="whitespace-nowrap px-4 py-3">{{ $method['method'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
<p class="mt-4 text-sm leading-6 text-muted">{{ $disclaimer }}</p>
