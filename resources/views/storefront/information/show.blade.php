<x-layouts.storefront :title="$content['title'].' | Kattie.uk'" :description="$content['description']" :canonical="url()->current()">
    <section class="shell py-14 sm:py-20">
        <div class="mx-auto max-w-3xl">
            <h1 class="font-display text-4xl sm:text-5xl">{{ $content['title'] }}</h1>
            <p class="mt-5 text-lg leading-8 text-muted">{{ $content['description'] }}</p>

            <div class="mt-12 space-y-10">
                @foreach($content['sections'] as [$heading, $paragraphs])
                    <section>
                        <h2 class="font-display text-2xl">{{ $heading }}</h2>
                        <div class="mt-4 space-y-4 text-base leading-7 text-muted">
                            @foreach($paragraphs as $paragraph)
                                <p>{{ $paragraph }}</p>
                            @endforeach
                        </div>
                    </section>
                @endforeach
            </div>

            @if(isset($content['delivery_table']))
                <section class="mt-10">
                    <h2 class="font-display text-2xl">UK delivery estimates</h2>
                    @include('storefront.information._delivery-table', [
                        'productionHeading' => $content['delivery_table']['production_heading'],
                        'productionMessage' => $content['delivery_table']['production_message'],
                        'methods' => $content['delivery_table']['methods'],
                        'disclaimer' => $content['delivery_table']['disclaimer'],
                    ])
                </section>
            @endif
        </div>
    </section>
</x-layouts.storefront>
