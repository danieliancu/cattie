@php($specifications = $product->preview_configuration['specifications'] ?? [])
@if($specifications !== [])
    <div class="mt-10 border-t border-rose/25 pt-8">
        <h2 class="font-display text-2xl">Product details</h2>
        <dl class="mt-5 divide-y divide-rose/20 text-sm">
            @foreach($specifications as $specification)
                <div class="grid gap-1 py-3 sm:grid-cols-[9rem_1fr] sm:gap-4">
                    <dt class="font-bold text-ink">{{ $specification['label'] }}</dt>
                    <dd class="leading-6 text-muted">{{ $specification['value'] }}</dd>
                </div>
            @endforeach
        </dl>
    </div>
@endif
