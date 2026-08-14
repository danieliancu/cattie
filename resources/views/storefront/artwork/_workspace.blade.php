<section class="{{ in_array($session->status->value, ['preparing_photo', 'generating']) ? 'fixed inset-0 z-50 overflow-hidden' : 'shell pb-0 pt-4 sm:py-20' }}" @if(in_array($session->status->value, ['preparing_photo', 'generating'])) x-data="artworkProgress(@js(route('artwork.status', $session->public_id)), @js(config('artwork.poll_interval_ms')), @js($session->processing_stage?->value ?? ($session->status->value === 'preparing_photo' ? 'preparing_photo' : 'creating_illustration')))" x-init="start()" @endif>
    <div class="{{ in_array($session->status->value, ['preparing_photo', 'generating']) ? 'h-full w-full' : 'mx-auto w-full min-w-0 max-w-5xl overflow-x-clip' }}">
        @if($session->status->value === 'awaiting_upload')
            <div class="mx-auto mt-8 max-w-2xl text-center">
                <h1 class="font-display text-5xl">Upload your photo</h1>
                <p class="mt-4 text-muted">Use a clear photo where the face is easy to see.</p>
                <form action="{{ route('artwork.upload', $session->public_id) }}" method="POST" enctype="multipart/form-data" class="mt-10">
                    @csrf
                    <label class="relative flex min-h-72 w-full min-w-0 max-w-full cursor-pointer flex-col items-center justify-center overflow-hidden rounded-[2rem] border-2 border-dashed border-coral/50 bg-white p-8">
                        <span class="font-display text-3xl">Choose a favourite photo</span>
                        <span class="mt-3 text-sm text-muted">JPEG, PNG or WebP · up to 10 MB</span>
                        <input class="absolute inset-0 h-full w-full cursor-pointer opacity-0" type="file" name="photo" accept="image/jpeg,image/png,image/webp" required>
                    </label>
                    <button class="button-primary mt-7 w-full">Create my artwork →</button>
                </form>
            </div>
        @elseif(in_array($session->status->value, ['preparing_photo', 'generating']))
            <div class="absolute inset-0 bg-cover bg-center lg:hidden" style="background-image:url('{{ route('artwork.original', $session->public_id) }}')" aria-hidden="true"></div>
            <div class="artwork-progress-overlay absolute inset-0" aria-hidden="true"></div>
            <div x-show="!ready && !failed" class="pointer-events-none absolute inset-0 z-10 overflow-hidden lg:hidden" aria-hidden="true">
                <template x-for="star in stars" :key="star.id">
                    <span class="artwork-ai-star absolute" :style="star.style"></span>
                </template>
            </div>
            <div x-show="!ready && !failed" class="pointer-events-none absolute inset-0 z-10 hidden overflow-hidden lg:block" aria-hidden="true">
                <template x-for="star in desktopStars" :key="star.id">
                    <span class="artwork-ai-star absolute" :style="star.style"></span>
                </template>
            </div>
            <div x-show="ready" x-cloak class="pointer-events-none absolute inset-0 z-30 overflow-hidden" aria-hidden="true">
                <template x-for="piece in confetti" :key="piece.id">
                    <span class="artwork-confetti absolute" :style="piece.style"></span>
                </template>
            </div>
            <div class="relative z-20 flex min-h-dvh items-center justify-center overflow-y-auto p-5 sm:p-10" role="dialog" aria-modal="true" aria-labelledby="artwork-progress-title" aria-live="polite">
                <div class="relative w-full max-w-xl text-white lg:rounded-[2rem] lg:bg-black lg:p-12 lg:shadow-2xl">
                    <form x-show="!failed && !ready" method="POST" action="{{ route('artwork.cancel', $session->public_id) }}" class="absolute -top-14 right-0">@csrf<button type="submit" class="flex h-10 w-10 cursor-pointer items-center justify-center text-white transition hover:scale-110" aria-label="Cancel artwork creation" title="Cancel artwork creation"><i data-lucide="x" class="h-9 w-9" stroke-width="2.5" aria-hidden="true"></i></button></form>
                    <h1 id="artwork-progress-title" class="font-display text-4xl sm:text-5xl" x-text="failed ? 'We couldn’t create your artwork this time.' : (ready ? 'Your design is ready' : 'Creating your artwork…')">Creating your artwork…</h1>
                    <ol class="mt-10 space-y-4 text-left sm:mt-12">
                        <template x-for="(step, index) in steps" :key="step.key">
                            <li class="flex items-center gap-4" :class="isComplete(index) ? 'text-white' : 'text-white/70'">
                                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-md border-2 transition" :class="isComplete(index) ? 'border-emerald-400 bg-emerald-400 text-black' : 'border-white/70'">
                                    <svg x-show="isComplete(index)" class="h-5 w-5" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="m4 10 4 4 8-9" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                </span>
                                <span class="text-lg font-semibold sm:text-xl" x-text="step.label"></span>
                            </li>
                        </template>
                    </ol>
                    <div class="mt-10 h-3 overflow-hidden rounded-full bg-white/20" role="progressbar" aria-label="Artwork creation progress" :aria-valuenow="progress" aria-valuemin="0" aria-valuemax="100">
                        <div class="relative h-full overflow-hidden rounded-full bg-emerald-400 transition-[width] duration-300 ease-linear" :style="`width: ${progress}%`">
                            <span class="artwork-progress-shine absolute inset-y-0 w-1/3 bg-gradient-to-r from-transparent via-white/55 to-transparent" aria-hidden="true"></span>
                        </div>
                    </div>
                    <p class="mt-3 text-right text-sm font-bold text-white" x-text="`${Math.floor(progress)}%`"></p>
                    <div x-show="ready" x-cloak class="mt-8">
                        <button type="button" class="button-primary w-full" @click="viewDesign()">View Your Design</button>
                    </div>
                    <div x-show="failed" x-cloak class="mt-8 grid gap-3 sm:grid-cols-2">
                        <form method="POST" action="{{ route('artwork.regenerate', $session->public_id) }}">@csrf<button class="button-primary w-full">Try again</button></form>
                        <form method="POST" action="{{ route('artwork.change', $session->public_id) }}">@csrf<button class="w-full rounded-full border-2 border-white px-6 py-3 font-bold text-white">Upload a new photo</button></form>
                    </div>
                    <p x-show="!ready && !failed" class="mt-8 text-center text-sm text-white/70">You can safely refresh this page.</p>
                </div>
            </div>
        @elseif($session->status->value === 'failed')
            <div class="mx-auto mt-16 max-w-xl text-center">
                <h1 class="font-display text-5xl">We couldn’t prepare your design this time.</h1>
                <p class="mt-5 text-muted">You can try again or choose a new photo.</p>
                <form method="POST" action="{{ route('artwork.regenerate', $session->public_id) }}" class="mt-8">@csrf<button class="button-primary">Try again</button></form>
                <form method="POST" action="{{ route('artwork.change', $session->public_id) }}" class="mt-4">@csrf<button class="font-bold text-coral underline">Upload a new photo</button></form>
            </div>
        @elseif($session->status->value === 'expired')
            <div class="mx-auto mt-16 max-w-xl text-center">
                <h1 class="font-display text-5xl">This artwork session has expired.</h1>
                <a href="{{ route('products.index') }}" class="button-primary mt-8">Choose a gift</a>
            </div>
        @else
            @php($templated = (bool) $session->product->designTemplate)
            @php($designs = $session->composedDesigns->sortByDesc('created_at'))
            @php($selectedDesign = $session->approvedComposedDesign ?? $designs->first(fn ($design) => $design->product_variant_id === $session->product_variant_id) ?? $designs->first())
            @php($selectedAsset = $session->approvedAsset ?? $selectedDesign?->generationAsset ?? $session->currentGeneration?->assets->firstWhere('kind', 'provider_original'))
            @php($artworkPreview = $selectedAsset?->generation?->assets?->firstWhere('kind', 'web_preview') ?? $session->currentGeneration?->assets->firstWhere('kind', 'web_preview'))
            @php($productExamples = $session->product->images->sortBy('sort_order')->values())
            @php($examplesFollowVariant = $productExamples->contains(fn ($image) => (bool) $image->product_variant_id))
            @php($bottleVariants = $session->product->variants->filter(fn ($variant) => $variant->is_active)->values())
            @php($selectedVariant = $bottleVariants->firstWhere('id', $session->product_variant_id))
            @php($selectedVariantExamples = $examplesFollowVariant ? $productExamples->where('product_variant_id', $session->product_variant_id)->values() : $productExamples)
            @php($designWidth = $selectedDesign?->width ?? 6)
            @php($designHeight = $selectedDesign?->height ?? 5)
            @php($resolvedConfig = $selectedDesign?->resolved_manifest['configuration'] ?? $session->product->designTemplate?->definition() ?? [])
            @php($previewSurface = $resolvedConfig['preview_surface'] ?? [])
            @php($surfaceColours = $bottleVariants->mapWithKeys(function ($variant) use ($previewSurface) { $option = $previewSurface['variant_option'] ?? null; $value = $option ? strtolower($variant->options[$option] ?? '') : null; return [$variant->id => ($value ? ($previewSurface['colours_by_variant'][$value] ?? $previewSurface['fallback_colour'] ?? '#f4efe7') : ($previewSurface['colour'] ?? '#f4efe7'))]; }))
            @php($characterConfig = $resolvedConfig['character'] ?? ['x' => .5, 'y' => .5, 'max_width' => .36, 'max_height' => .84])
            @php($characterClip = $resolvedConfig['character_clip'] ?? ['mode' => 'canvas'])
            @php($savedName = collect($session->personalisation_snapshot)->firstWhere('key', 'name')['value'] ?? '')

            <div class="grid min-w-0 sm:mt-8 [&>*]:min-w-0 lg:grid-cols-[minmax(0,1fr)_minmax(0,.72fr)] lg:gap-10" x-data="designWorkspace(@js([
                'previewMode' => $templated ? 'design' : 'artwork',
                'exampleUrl' => $selectedVariantExamples->first()?->url(),
                'exampleAlt' => $selectedVariantExamples->first()?->alt_text ?? 'Product example',
                'variantExamples' => $productExamples->groupBy('product_variant_id')->map(fn ($images) => $images->map(fn ($image) => ['url' => $image->url(), 'alt' => $image->alt_text])->values())->all(),
                'examplesFollowVariant' => $examplesFollowVariant,
                'variantSurfaces' => $surfaceColours,
                'variantPrices' => $bottleVariants->mapWithKeys(fn ($variant) => [$variant->id => $variant->formattedPrice()]),
                'surfaceColour' => $surfaceColours->get($session->product_variant_id, '#f4efe7'),
                'characterX' => (float) ($characterConfig['x'] ?? .5),
                'characterY' => (float) ($characterConfig['y'] ?? .5),
                'characterWidth' => (float) ($characterConfig['max_width'] ?? .36),
                'characterHeight' => (float) ($characterConfig['max_height'] ?? .84),
                'characterClip' => $characterClip,
                'scaleMin' => (float) data_get($characterConfig, 'adjustment_limits.scale_min', .1),
                'scaleMax' => (float) data_get($characterConfig, 'adjustment_limits.scale_max', 50),
                'offsetXMin' => max((float) data_get($characterConfig, 'adjustment_limits.offset_x_min', -1000), -(float) ($characterConfig['x'] ?? .5)),
                'offsetXMax' => min((float) data_get($characterConfig, 'adjustment_limits.offset_x_max', 1000), 1 - (float) ($characterConfig['x'] ?? .5)),
                'offsetYMin' => max((float) data_get($characterConfig, 'adjustment_limits.offset_y_min', -1000), -(float) ($characterConfig['y'] ?? .5)),
                'selectedDesignUrl' => $selectedDesign ? route('artwork.designs', [$session->public_id, $selectedDesign]) : null,
                'selectedLayoutUrl' => $selectedDesign ? route('artwork.design-layout', [$session->public_id, $selectedDesign]) : null,
                'editorBackgroundUrl' => $selectedDesign?->editor_background_storage_key ? route('artwork.design-editor-background', [$session->public_id, $selectedDesign]) : null,
                'characterUrl' => $selectedAsset ? route('artwork.assets', [$session->public_id, $selectedAsset, 'trim' => 1]) : null,
                'selectedDesignId' => $selectedDesign?->id,
                'renderFingerprint' => $selectedDesign?->render_fingerprint,
                'selectedAssetId' => $selectedAsset?->id,
                'selectedVariantId' => $session->product_variant_id,
                'scale' => (float) ($selectedDesign?->character_adjustments['scale'] ?? 1),
                'offsetX' => (float) ($selectedDesign?->character_adjustments['offset_x'] ?? 0),
                'offsetY' => (float) ($selectedDesign?->character_adjustments['offset_y'] ?? 0),
                'editable' => $templated && $session->status->value === 'preview_ready' && $selectedDesign?->editor_background_storage_key,
                'nameValue' => $savedName,
                'savedNameValue' => $savedName,
                'variantUrl' => route('artwork.variant', $session->public_id),
                'nameUrl' => route('artwork.name', $session->public_id),
                'csrf' => csrf_token(),
            ]))">
                <div class="w-full min-w-0 max-w-full lg:sticky lg:top-24 lg:self-start">
                    @if($templated)
                        <div class="mb-4 flex justify-center gap-2" role="group" aria-label="Preview type">
                            <button type="button" @click="previewMode = 'design'" :class="previewMode === 'design' ? 'bg-ink text-white' : 'bg-white text-ink'" class="cursor-pointer rounded-full px-4 py-2 text-sm font-bold">Design</button>
                            <button type="button" @click="previewMode = 'product'" :class="previewMode === 'product' ? 'bg-ink text-white' : 'bg-white text-ink'" class="cursor-pointer rounded-full px-4 py-2 text-sm font-bold">Product</button>
                        </div>
                    @endif

                    @if($selectedDesign)
                        <div x-show="previewMode === 'design'" x-ref="editorCanvas" class="relative w-full min-w-0 max-w-full overflow-hidden rounded-[2.5rem]" style="aspect-ratio: {{ $designWidth }} / {{ $designHeight }}" :style="`aspect-ratio: {{ $designWidth }} / {{ $designHeight }}; background-color: ${surfaceColour}`">
                            <img x-show="!editable" :src="selectedDesignUrl" alt="Your flat personalised print design" class="h-full w-full object-contain">
                            <template x-if="editable">
                                <div class="absolute inset-0">
                                    <img :src="editorBackgroundUrl" alt="Your personalised design background" class="h-full w-full object-contain">
                                    <div class="absolute inset-0" :style="characterClipStyle()">
                                    <div x-ref="characterZone" class="absolute overflow-visible" :style="characterZoneStyle()">
                                        <div class="absolute inset-0 overflow-visible">
                                            <div x-ref="characterSelection" @pointerdown.prevent="beginTransform($event, 'move')" :class="transformMode === 'move' ? 'cursor-grabbing' : 'cursor-grab'" class="absolute touch-none border-2 border-dashed border-white/90 shadow-[inset_0_0_0_1px_rgba(0,0,0,0.18)]" :style="`left:${50 + (offsetX / characterWidth * 100)}%; top:${50 + (offsetY / characterHeight * 100)}%; width:${scale * 100}%; height:${scale * 100}%; transform:translate(-50%,-50%)`">
                                                <img :src="characterUrl" alt="Your AI character" class="pointer-events-none h-full w-full select-none object-contain">
                                            <template x-for="handle in ['nw', 'ne', 'w', 'e', 'sw', 'se']">
                                                <button type="button" @pointerdown.stop.prevent="beginTransform($event, 'scale')" :class="{'-left-3.5 -top-3.5 cursor-nwse-resize': handle === 'nw', '-right-3.5 -top-3.5 cursor-nesw-resize': handle === 'ne', '-left-3.5 top-1/2 -translate-y-1/2 cursor-ew-resize': handle === 'w', '-right-3.5 top-1/2 -translate-y-1/2 cursor-ew-resize': handle === 'e', '-bottom-3.5 -left-3.5 cursor-nesw-resize': handle === 'sw', '-bottom-3.5 -right-3.5 cursor-nwse-resize': handle === 'se'}" class="absolute z-10 flex h-7 w-7 touch-none items-center justify-center rounded-lg border-2 border-white bg-coral text-sm font-black leading-none text-white shadow-lg ring-1 ring-ink/25" :aria-label="`Resize character from ${handle} handle`">
                                                    <span aria-hidden="true" x-text="({nw: '↖', ne: '↗', w: '↔', e: '↔', sw: '↙', se: '↘'})[handle]"></span>
                                                </button>
                                            </template>
                                            </div>
                                        </div>
                                    </div>
                                    </div>
                                </div>
                            </template>
                            <div x-show="updatingName" class="absolute inset-0 flex items-center justify-center bg-white/65" aria-live="polite">
                                <span class="rounded-full bg-ink px-4 py-2 text-sm font-bold text-white">Updating name…</span>
                            </div>
                            <div x-show="changingColour" class="absolute inset-0 flex items-center justify-center bg-white/65" aria-live="polite">
                                <span class="rounded-full bg-ink px-4 py-2 text-sm font-bold text-white">Updating colours…</span>
                            </div>
                            <div x-show="savingLayout" class="absolute inset-x-0 bottom-3 mx-auto w-max rounded-full bg-ink/80 px-3 py-1 text-xs text-white">Saving…</div>
                        </div>
                    @endif

                    @if(!$templated && $artworkPreview)
                        <div x-show="previewMode === 'artwork'" class="overflow-hidden rounded-[2.5rem] bg-sand shadow-2xl" style="aspect-ratio: {{ $designWidth }} / {{ $designHeight }}">
                            <img src="{{ route('artwork.assets', [$session->public_id, $artworkPreview]) }}" alt="Your generated AI artwork" class="h-full w-full object-contain">
                        </div>
                    @endif

                    @if($productExamples->isNotEmpty())
                        <div x-show="previewMode === 'product'" class="overflow-hidden rounded-[2.5rem] bg-white" style="aspect-ratio: {{ $designWidth }} / {{ $designHeight }}">
                            <img :src="exampleUrl" :alt="exampleAlt" class="h-full w-full object-contain">
                        </div>
                        <div class="mt-3 grid min-h-24 grid-cols-4 content-start gap-3">
                                @foreach($productExamples as $image)
                                    <button type="button" x-show="!examplesFollowVariant || selectedVariantId === @js($image->product_variant_id)" @click="previewMode = 'product'; exampleUrl = @js($image->url()); exampleAlt = @js($image->alt_text)" class="aspect-square cursor-pointer overflow-hidden rounded-xl bg-sand ring-2 ring-transparent focus:ring-coral" :class="previewMode === 'product' && exampleUrl === @js($image->url()) ? 'ring-coral' : ''">
                                        <img src="{{ $image->url() }}" alt="{{ $image->alt_text }}" class="h-full w-full object-cover">
                                    </button>
                                @endforeach
                        </div>
                    @endif
                </div>

                <div class="self-center">
                    <div class="hidden lg:block">
                        <h1 class="font-display text-5xl">{{ $templated ? $session->product->name : ($session->status->value === 'approved' ? 'This is the one.' : 'Your artwork is ready.') }}</h1>
                        <p class="mt-5 leading-7 text-muted">{{ $templated ? $session->product->short_description : 'Review the generated artwork before continuing.' }}</p>
                        @if($session->product->categories->isNotEmpty())<p class="mt-4 text-sm text-muted"><span class="font-semibold text-ink">Categories:</span> @foreach($session->product->categories as $category)<a class="hover:text-coral hover:underline" href="{{ route('categories.show', $category) }}">{{ $category->name }}</a>@unless($loop->last) <span aria-hidden="true">·</span> @endunless @endforeach</p>@endif
                    </div>

                    <p x-show="colourError" x-text="colourError" class="mt-4 rounded-xl bg-red-50 p-3 text-sm text-red-700"></p>
                    <p x-show="layoutError" x-text="layoutError" class="mt-4 rounded-xl bg-red-50 p-3 text-sm text-red-700"></p>
                    <p x-show="nameError" x-text="nameError" class="mt-4 rounded-xl bg-red-50 p-3 text-sm text-red-700"></p>

                    @if($templated && $session->status->value === 'preview_ready')
                        <p class="mt-8 text-2xl font-bold">Price <span x-text="variantPrices[selectedVariantId]"></span></p>
                        <fieldset class="mt-8">
                            <legend class="font-display text-xl">{{ $session->product->preview_configuration['variant_label'] ?? 'Bottle colour' }}</legend>
                            <div class="bottle-colour-options mt-4 grid grid-cols-4 gap-2">
                                @foreach($bottleVariants as $variant)
                                    @php($colourLabel = strtolower($variant->options['colour'] ?? '') === 'grey' ? 'Gray' : str($variant->options['colour'] ?? '')->title())
                                    @php($variantAvailable = $variant->hasSingleActiveFulfilmentMapping())
                                    <label class="selection-card min-w-0 {{ $variantAvailable ? '' : 'cursor-not-allowed opacity-45' }}" :class="changingColour ? 'pointer-events-none opacity-60' : ''" @if(!$variantAvailable) title="This option is not currently available" @endif>
                                        <input class="sr-only" type="radio" name="preview_variant_id" value="{{ $variant->id }}" :checked="selectedVariantId === @js($variant->id)" @change="chooseVariant(@js($variant->id))" @disabled(!$variantAvailable) :disabled="changingColour">
                                        <span><strong class="block">{{ $colourLabel }}</strong><span class="block">{{ $variantAvailable ? $variant->formattedPrice() : 'Unavailable' }}</span></span>
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>
                        <div class="mt-8">
                            <div class="flex items-center justify-between gap-3">
                                <label for="artwork-name" class="form-label">Name</label>
                                <span class="text-xs text-muted" x-text="`${Math.max(0, 12 - nameValue.length)} characters left`"></span>
                            </div>
                            <div class="relative">
                                <input id="artwork-name" class="form-control pr-16" type="text" maxlength="12" x-model="nameValue">
                                <button type="button" class="absolute right-2 top-1/2 -translate-y-1/2 rounded-lg bg-ink px-3 py-1.5 text-xs font-bold text-white transition hover:bg-coral disabled:cursor-not-allowed disabled:opacity-35" @click="updateName()" :disabled="updatingName || savingLayout || changingColour || nameValue === savedNameValue" aria-label="Apply name">OK</button>
                            </div>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('artwork.cart', $session->public_id) }}" class="mt-9">
                        @csrf
                        <input type="hidden" name="asset_id" :value="selectedAssetId">
                        <input type="hidden" name="design_id" :value="selectedDesignId">
                        <input type="hidden" name="render_fingerprint" :value="renderFingerprint">
                        <button class="button-primary w-full" :disabled="changingColour || updatingName || savingLayout || !renderFingerprint || nameValue !== savedNameValue">Add to basket</button>
                    </form>
                    <form method="POST" action="{{ route('artwork.change', $session->public_id) }}" class="mt-4 text-center">
                        @csrf
                        <input type="hidden" name="design_id" :value="selectedDesignId">
                        <button class="cursor-pointer font-bold text-coral underline">Change artwork</button>
                    </form>

                    <div class="mt-10 lg:hidden">
                        <h1 class="font-display text-2xl">{{ $templated ? $session->product->name : ($session->status->value === 'approved' ? 'This is the one.' : 'Your artwork is ready.') }}</h1>
                        <p class="mt-4 leading-7 text-muted">{{ $templated ? $session->product->short_description : 'Review the generated artwork before continuing.' }}</p>
                        @if($session->product->categories->isNotEmpty())<p class="mt-4 text-sm text-muted"><span class="font-semibold text-ink">Categories:</span> @foreach($session->product->categories as $category)<a class="hover:text-coral hover:underline" href="{{ route('categories.show', $category) }}">{{ $category->name }}</a>@unless($loop->last) <span aria-hidden="true">·</span> @endunless @endforeach</p>@endif
                    </div>
                    <div class="mt-10 border-t border-rose/25 pt-8">
                        <h2 class="font-display text-2xl">About your gift</h2>
                        <div class="mt-4 whitespace-pre-line leading-7 text-muted">{{ $session->product->description }}</div>
                    </div>
                    @include('storefront.products._specifications', ['product' => $session->product])
                    @include('storefront.products._shipping-returns')
                </div>
            </div>
        @endif
    </div>
</section>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('artworkProgress', (url, interval, initialStage) => ({
        steps: [
            {key: 'preparing_photo', label: 'Preparing your photo…', progress: 10},
            {key: 'creating_illustration', label: 'Creating your illustration…', progress: 30},
            {key: 'removing_background', label: 'Removing the background…', progress: 55},
            {key: 'preparing_preview', label: 'Preparing your preview…', progress: 80},
            {key: 'ready', label: 'Your artwork is ready', progress: 100},
        ],
        stage: initialStage,
        progress: ({preparing_photo: 10, creating_illustration: 30, removing_background: 55, preparing_preview: 80, ready: 100})[initialStage] ?? 10,
        ready: false,
        failed: false,
        viewUrl: window.location.href,
        movementTimer: null,
        previousBodyOverflow: '',
        previousHtmlOverflow: '',
        stars: Array.from({length: 22}, (_, index) => {
            const side = index % 4;
            const along = 5 + ((index * 17) % 90);
            const edge = 4 + (index % 3) * 4;
            const coordinates = side === 0 ? `left:${along}%;top:${edge}px` : side === 1 ? `right:${edge}px;top:${along}%` : side === 2 ? `left:${along}%;bottom:${edge}px` : `left:${edge}px;top:${along}%`;
            const colour = ['#67e8f9', '#c084fc', '#f9a8d4', '#fde047', '#6ee7b7'][index % 5];
            return {id: index, style: `${coordinates};--star-colour:${colour};--star-size:${7 + (index % 4) * 2}px;--star-delay:${(index * .31) % 3.2}s;--star-duration:${1.7 + (index % 5) * .35}s`};
        }),
        desktopStars: Array.from({length: 42}, (_, index) => {
            const region = index % 4;
            const sequence = Math.floor(index / 4);
            const x = region === 0 ? 3 + ((sequence * 7) % 16) : region === 1 ? 81 + ((sequence * 11) % 16) : 22 + ((sequence * 13) % 57);
            const y = region === 2 ? 4 + ((sequence * 5) % 12) : region === 3 ? 84 + ((sequence * 7) % 12) : 4 + ((sequence * 17) % 92);
            const colour = ['#67e8f9', '#c084fc', '#f9a8d4', '#fde047', '#6ee7b7'][index % 5];
            return {id: `desktop-${index}`, style: `left:${x}%;top:${y}%;--star-colour:${colour};--star-size:${16 + (index % 5) * 4}px;--star-delay:${(index * .23) % 3.4}s;--star-duration:${1.9 + (index % 6) * .32}s`};
        }),
        confetti: Array.from({length: 72}, (_, index) => ({
            id: index,
            style: `left:${(index * 37) % 100}%;--confetti-colour:${['#34d399','#fbbf24','#fb7185','#60a5fa','#c084fc','#ffffff'][index % 6]};--confetti-delay:${(index % 18) * .08}s;--confetti-duration:${2.8 + (index % 7) * .22}s;--confetti-drift:${-70 + (index * 29) % 140}px;--confetti-turn:${360 + (index % 5) * 180}deg`,
        })),
        isComplete(index) {
            const current = this.steps.findIndex(step => step.key === this.stage);
            return this.ready || index < current;
        },
        start() {
            this.lockPageScroll();
            this.startContinuousMovement();
            const poll = async () => {
                try {
                    const response = await fetch(url, {headers: {Accept: 'application/json'}});
                    if (!response.ok) throw new Error('Status request failed');
                    const data = await response.json();
                    if (data.stage) this.stage = data.stage;
                    this.progress = Math.max(this.progress, Math.min(data.progress ?? this.progress, data.stage === 'ready' ? 100 : 99));
                    if (data.status === 'preview_ready' || data.status === 'approved') {
                        this.stage = 'ready';
                        this.progress = 100;
                        this.ready = true;
                        this.viewUrl = data.view_url || window.location.href;
                        this.stopContinuousMovement();
                        return;
                    }
                    if (data.status === 'failed' || data.status === 'expired') {
                        this.failed = true;
                        this.stopContinuousMovement();
                        return;
                    }
                } catch (error) {
                    setTimeout(poll, interval);
                    return;
                }
                setTimeout(poll, interval);
            };
            setTimeout(poll, interval);
        },
        startContinuousMovement() {
            this.movementTimer = setInterval(() => {
                if (this.ready || this.failed) return;
                const current = this.steps.findIndex(step => step.key === this.stage);
                const nextStep = this.steps[Math.min(current + 1, this.steps.length - 1)];
                const ceiling = nextStep?.key === 'ready' ? 99 : (nextStep?.progress ?? 99) - 0.5;
                if (this.progress < ceiling) {
                    const remaining = ceiling - this.progress;
                    this.progress = Math.min(ceiling, this.progress + Math.max(0.08, remaining * 0.018));
                }
            }, 250);
        },
        stopContinuousMovement() {
            if (this.movementTimer) clearInterval(this.movementTimer);
            this.movementTimer = null;
        },
        lockPageScroll() {
            this.previousBodyOverflow = document.body.style.overflow;
            this.previousHtmlOverflow = document.documentElement.style.overflow;
            document.body.style.overflow = 'hidden';
            document.documentElement.style.overflow = 'hidden';
        },
        unlockPageScroll() {
            document.body.style.overflow = this.previousBodyOverflow;
            document.documentElement.style.overflow = this.previousHtmlOverflow;
        },
        destroy() {
            this.stopContinuousMovement();
            this.unlockPageScroll();
        },
        viewDesign() {
            window.location.assign(this.viewUrl);
        },
    }));

    Alpine.data('designWorkspace', config => ({
        ...config,
        changingColour: false,
        updatingName: false,
        savingLayout: false,
        colourError: '',
        layoutError: '',
        nameError: '',
        transformMode: null,
        characterClipStyle() {
            if ((this.characterClip?.mode || 'canvas') !== 'custom') return '';
            const top = Number(this.characterClip.y) * 100;
            const right = (1 - Number(this.characterClip.x) - Number(this.characterClip.width)) * 100;
            const bottom = (1 - Number(this.characterClip.y) - Number(this.characterClip.height)) * 100;
            const left = Number(this.characterClip.x) * 100;
            return `clip-path: inset(${top}% ${right}% ${bottom}% ${left}%)`;
        },
        characterZoneStyle() {
            return `left:${(this.characterX - this.characterWidth / 2) * 100}%; top:${(this.characterY - this.characterHeight / 2) * 100}%; width:${this.characterWidth * 100}%; height:${this.characterHeight * 100}%`;
        },
        applyDesignGeometry(geometry) {
            if (!geometry) return;
            const character = geometry.character || {};
            this.characterX = Number(character.x ?? this.characterX);
            this.characterY = Number(character.y ?? this.characterY);
            this.characterWidth = Number(character.max_width ?? this.characterWidth);
            this.characterHeight = Number(character.max_height ?? this.characterHeight);
            this.characterClip = geometry.character_clip || {mode: 'canvas'};
            const limits = character.adjustment_limits || {};
            this.scaleMin = Number(limits.scale_min ?? .1);
            this.scaleMax = Number(limits.scale_max ?? 50);
            this.offsetXMin = Math.max(Number(limits.offset_x_min ?? -1000), -this.characterX);
            this.offsetXMax = Math.min(Number(limits.offset_x_max ?? 1000), 1 - this.characterX);
            this.offsetYMin = Math.max(Number(limits.offset_y_min ?? -1000), -this.characterY);
        },
        maximumDownOffset() {
            const canvasHeight = this.$refs.editorCanvas?.getBoundingClientRect().height || 1;
            const handleClearance = Math.min(.08, 18 / canvasHeight);
            return 1 - handleClearance - this.characterY + ((this.characterHeight * this.scale) / 2);
        },
        preloadImage(url) {
            return new Promise((resolve, reject) => {
                const image = new Image();
                image.onload = resolve;
                image.onerror = reject;
                image.src = url;
            });
        },
        async updateName() {
            if (this.changingColour || this.savingLayout || this.updatingName || this.nameValue === this.savedNameValue) return;
            this.nameError = '';
            this.updatingName = true;
            const previousFingerprint = this.renderFingerprint;
            this.renderFingerprint = null;
            const submittedName = this.nameValue;
            try {
                const response = await fetch(this.nameUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': this.csrf,
                    },
                    body: JSON.stringify({name: submittedName, design_id: this.selectedDesignId}),
                });
                const data = await response.json();
                if (!response.ok) throw new Error(data.message);
                const version = Date.now();
                const nextPreviewUrl = `${data.preview_url}?v=${version}`;
                const nextBackgroundUrl = `${data.background_url}?v=${version}`;
                await Promise.all([this.preloadImage(nextPreviewUrl), this.preloadImage(nextBackgroundUrl)]);
                this.selectedDesignId = data.design_id;
                this.selectedDesignUrl = nextPreviewUrl;
                this.selectedLayoutUrl = data.layout_url;
                this.editorBackgroundUrl = nextBackgroundUrl;
                this.renderFingerprint = data.render_fingerprint;
                this.savedNameValue = submittedName;
            } catch (error) {
                this.renderFingerprint = previousFingerprint;
                this.nameError = error.message || 'We could not update that name. Please try again.';
            } finally {
                this.updatingName = false;
            }
        },
        beginTransform(event, mode) {
            if (!this.editable || this.changingColour || this.updatingName) return;
            this.transformMode = mode;
            this.renderFingerprint = null;
            const startX = event.clientX;
            const startY = event.clientY;
            const startOffsetX = this.offsetX;
            const startOffsetY = this.offsetY;
            const startScale = this.scale;
            const canvas = this.$refs.editorCanvas.getBoundingClientRect();
            const zone = this.$refs.characterZone.getBoundingClientRect();
            const selection = this.$refs.characterSelection.getBoundingClientRect();
            const zoneCenterX = selection.left + (selection.width / 2);
            const zoneCenterY = selection.top + (selection.height / 2);
            const startDistance = Math.max(1, Math.hypot(startX - zoneCenterX, startY - zoneCenterY));
            const move = pointer => {
                if (mode === 'move') {
                    this.offsetX = Math.max(this.offsetXMin, Math.min(this.offsetXMax, startOffsetX + ((pointer.clientX - startX) / canvas.width)));
                    this.offsetY = Math.max(this.offsetYMin, Math.min(this.maximumDownOffset(), startOffsetY + ((pointer.clientY - startY) / canvas.height)));
                } else {
                    const distance = Math.hypot(pointer.clientX - zoneCenterX, pointer.clientY - zoneCenterY);
                    this.scale = Math.max(this.scaleMin, Math.min(this.scaleMax, startScale * (distance / startDistance)));
                    this.offsetY = Math.min(this.offsetY, this.maximumDownOffset());
                }
            };
            const up = () => {
                window.removeEventListener('pointermove', move);
                window.removeEventListener('pointerup', up);
                window.removeEventListener('pointercancel', up);
                this.transformMode = null;
                this.saveLayout();
            };
            window.addEventListener('pointermove', move);
            window.addEventListener('pointerup', up, {once: true});
            window.addEventListener('pointercancel', up, {once: true});
        },
        async saveLayout() {
            const previousFingerprint = this.renderFingerprint;
            this.renderFingerprint = null;
            this.savingLayout = true;
            this.layoutError = '';
            try {
                const response = await fetch(this.selectedLayoutUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': this.csrf,
                    },
                    body: JSON.stringify({scale: Number(this.scale), offset_x: Number(this.offsetX), offset_y: Number(this.offsetY)}),
                });
                const data = await response.json();
                if (!response.ok) throw new Error(data.message);
                const version = Date.now();
                const nextPreviewUrl = `${data.preview_url}?v=${version}`;
                await this.preloadImage(nextPreviewUrl);
                this.selectedDesignId = data.design_id;
                this.selectedDesignUrl = nextPreviewUrl;
                this.selectedLayoutUrl = data.layout_url;
                this.editorBackgroundUrl = `${data.background_url}?v=${version}`;
                this.renderFingerprint = data.render_fingerprint;
            } catch (error) {
                this.renderFingerprint = previousFingerprint;
                this.layoutError = error.message || 'We could not save those changes. Please try again.';
            } finally {
                this.savingLayout = false;
            }
        },
        async chooseVariant(variantId) {
            if (variantId === this.selectedVariantId || this.changingColour || this.updatingName) return;
            const previousVariantId = this.selectedVariantId;
            const previousSurfaceColour = this.surfaceColour;
            const previousFingerprint = this.renderFingerprint;
            this.renderFingerprint = null;
            this.selectedVariantId = variantId;
            this.surfaceColour = this.variantSurfaces[variantId] || '#f4efe7';
            this.previewMode = 'design';
            this.changingColour = true;
            this.colourError = '';
            try {
                const response = await fetch(this.variantUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': this.csrf,
                    },
                    body: JSON.stringify({variant_id: variantId, design_id: this.selectedDesignId}),
                });
                const data = await response.json();
                if (!response.ok) throw new Error(data.message);
                const version = Date.now();
                const nextPreviewUrl = `${data.preview_url}?v=${version}`;
                const nextBackgroundUrl = `${data.background_url}?v=${version}`;
                await Promise.all([this.preloadImage(nextPreviewUrl), this.preloadImage(nextBackgroundUrl)]);
                this.surfaceColour = data.surface_colour;
                this.selectedDesignId = data.design_id;
                this.selectedAssetId = data.asset_id;
                this.selectedDesignUrl = nextPreviewUrl;
                this.selectedLayoutUrl = data.layout_url;
                this.editorBackgroundUrl = nextBackgroundUrl;
                this.renderFingerprint = data.render_fingerprint;
                this.applyDesignGeometry(data.design_geometry);
                if (this.examplesFollowVariant) {
                    const firstExample = this.variantExamples[variantId]?.[0];
                    this.exampleUrl = firstExample?.url || null;
                    this.exampleAlt = firstExample?.alt || 'Product example';
                }
            } catch (error) {
                this.selectedVariantId = previousVariantId;
                this.surfaceColour = previousSurfaceColour;
                this.renderFingerprint = previousFingerprint;
                this.colourError = error.message || 'We could not update that colour. Please try again.';
            } finally {
                this.changingColour = false;
            }
        },
    }));
});
</script>

<style>
@keyframes artwork-progress-shine {
    from { transform: translateX(-140%); }
    to { transform: translateX(440%); }
}
.artwork-progress-shine { animation: artwork-progress-shine 1.6s linear infinite; }

@keyframes artwork-ai-twinkle {
    0%, 100% { opacity: 0; transform: scale(.25) rotate(0deg); }
    35%, 65% { opacity: .95; transform: scale(1) rotate(45deg); }
}
.artwork-ai-star {
    width: var(--star-size);
    height: var(--star-size);
    background: var(--star-colour);
    clip-path: polygon(50% 0, 62% 38%, 100% 50%, 62% 62%, 50% 100%, 38% 62%, 0 50%, 38% 38%);
    filter: drop-shadow(0 0 6px var(--star-colour));
    animation: artwork-ai-twinkle var(--star-duration) ease-in-out var(--star-delay) infinite;
}

@keyframes artwork-confetti-fall {
    0% { opacity: 0; transform: translate3d(0, -12vh, 0) rotate(0); }
    8% { opacity: 1; }
    100% { opacity: 1; transform: translate3d(var(--confetti-drift), 115vh, 0) rotate(var(--confetti-turn)); }
}
.artwork-confetti {
    top: -24px;
    width: 9px;
    height: 16px;
    border-radius: 2px;
    background: var(--confetti-colour);
    animation: artwork-confetti-fall var(--confetti-duration) cubic-bezier(.2,.65,.45,1) var(--confetti-delay) both;
}

@media (prefers-reduced-motion: reduce) {
    .artwork-ai-star { animation-duration: 4s; }
    .artwork-confetti { animation-duration: .01ms; animation-delay: 0s; }
}
</style>
