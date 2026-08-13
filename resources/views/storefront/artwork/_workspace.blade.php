<section class="{{ in_array($session->status->value, ['preparing_photo', 'generating']) ? 'fixed inset-0 z-50 flex items-center justify-center bg-ink/55 p-4' : 'shell py-12 sm:py-20' }}" @if(in_array($session->status->value, ['preparing_photo', 'generating'])) x-data="artworkProgress(@js(route('artwork.status', $session->public_id)), @js(config('artwork.poll_interval_ms')))" x-init="start()" @endif>
    <div class="mx-auto w-full max-w-5xl">
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
                    @error('photo')<p class="mt-3 text-red-700">{{ $message }}</p>@enderror
                    <button class="button-primary mt-7 w-full">Create my artwork →</button>
                </form>
            </div>
        @elseif(in_array($session->status->value, ['preparing_photo', 'generating']))
            <div class="mx-auto max-w-xl rounded-[2rem] bg-white p-8 text-center shadow-2xl sm:p-12" role="dialog" aria-modal="true" aria-labelledby="artwork-progress-title" aria-live="polite">
                <div class="mx-auto h-20 w-20 animate-pulse rounded-full bg-gradient-to-br from-rose to-sky"></div>
                <h1 id="artwork-progress-title" class="mt-8 font-display text-4xl">Creating your artwork…</h1>
                <p class="mt-4 text-muted" x-text="message">Creating your illustration…</p>
                <p class="mt-8 text-sm text-muted">You can safely refresh this page.</p>
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
            @php($surfaceColours = $bottleVariants->mapWithKeys(fn ($variant) => [$variant->id => $session->product->preview_configuration['design_surfaces_by_variant'][strtolower($variant->options['colour'] ?? '')] ?? '#f4efe7']))
            @php($designWidth = $selectedDesign?->width ?? 6)
            @php($designHeight = $selectedDesign?->height ?? 5)
            @php($characterConfig = $session->product->designTemplate?->definition()['character'] ?? ['x' => .5, 'y' => .5, 'max_width' => .36, 'max_height' => .84])
            @php($savedName = collect($session->personalisation_snapshot)->firstWhere('key', 'name')['value'] ?? '')

            <div class="mt-8 grid gap-10 lg:grid-cols-[1fr_.72fr]" x-data="designWorkspace(@js([
                'previewMode' => $templated ? 'design' : 'artwork',
                'exampleUrl' => $selectedVariantExamples->first()?->url(),
                'exampleAlt' => $selectedVariantExamples->first()?->alt_text ?? 'Product example',
                'variantExamples' => $productExamples->groupBy('product_variant_id')->map(fn ($images) => $images->map(fn ($image) => ['url' => $image->url(), 'alt' => $image->alt_text])->values())->all(),
                'examplesFollowVariant' => $examplesFollowVariant,
                'variantSurfaces' => $surfaceColours,
                'surfaceColour' => $surfaceColours->get($session->product_variant_id, '#f4efe7'),
                'characterX' => (float) ($characterConfig['x'] ?? .5),
                'characterY' => (float) ($characterConfig['y'] ?? .5),
                'characterWidth' => (float) ($characterConfig['max_width'] ?? .36),
                'characterHeight' => (float) ($characterConfig['max_height'] ?? .84),
                'selectedDesignUrl' => $selectedDesign ? route('artwork.designs', [$session->public_id, $selectedDesign]) : null,
                'selectedLayoutUrl' => $selectedDesign ? route('artwork.design-layout', [$session->public_id, $selectedDesign]) : null,
                'editorBackgroundUrl' => $selectedDesign?->editor_background_storage_key ? route('artwork.design-editor-background', [$session->public_id, $selectedDesign]) : null,
                'characterUrl' => $selectedAsset ? route('artwork.assets', [$session->public_id, $selectedAsset, 'trim' => 1]) : null,
                'selectedDesignId' => $selectedDesign?->id,
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
                <div class="lg:sticky lg:top-24 lg:self-start">
                    @if($templated)
                        <div class="mb-4 flex justify-center gap-2" role="group" aria-label="Preview type">
                            <button type="button" @click="previewMode = 'design'" :class="previewMode === 'design' ? 'bg-ink text-white' : 'bg-white text-ink'" class="rounded-full px-4 py-2 text-sm font-bold">Design</button>
                            <button type="button" @click="previewMode = 'product'" :class="previewMode === 'product' ? 'bg-ink text-white' : 'bg-white text-ink'" class="rounded-full px-4 py-2 text-sm font-bold">Product</button>
                        </div>
                    @endif

                    @if($selectedDesign)
                        <div x-show="previewMode === 'design'" x-ref="editorCanvas" class="relative overflow-hidden rounded-[2.5rem]" style="aspect-ratio: {{ $designWidth }} / {{ $designHeight }}" :style="`aspect-ratio: {{ $designWidth }} / {{ $designHeight }}; background-color: ${surfaceColour}`">
                            <img x-show="!editable" :src="selectedDesignUrl" alt="Your flat personalised print design" class="h-full w-full object-contain">
                            <template x-if="editable">
                                <div class="absolute inset-0">
                                    <img :src="editorBackgroundUrl" alt="Your personalised design background" class="h-full w-full object-contain">
                                    <div x-ref="characterZone" class="absolute overflow-visible" style="left:{{ (($characterConfig['x'] ?? .5) - (($characterConfig['max_width'] ?? .36) / 2)) * 100 }}%; top:{{ (($characterConfig['y'] ?? .5) - (($characterConfig['max_height'] ?? .84) / 2)) * 100 }}%; width:{{ ($characterConfig['max_width'] ?? .36) * 100 }}%; height:{{ ($characterConfig['max_height'] ?? .84) * 100 }}%">
                                        <div class="absolute inset-0 overflow-hidden">
                                            <div class="absolute" :style="`left:${50 + (offsetX / characterWidth * 100)}%; top:${50 + (offsetY / characterHeight * 100)}%; width:${scale * 100}%; height:${scale * 100}%; transform:translate(-50%,-50%)`">
                                                <img :src="characterUrl" alt="Your AI character" class="pointer-events-none h-full w-full select-none object-contain">
                                            </div>
                                        </div>
                                        <div @pointerdown.prevent="beginTransform($event, 'move')" :class="transformMode === 'move' ? 'cursor-grabbing' : 'cursor-grab'" class="absolute inset-0 z-10 touch-none border-2 border-dashed border-white/90 shadow-[inset_0_0_0_1px_rgba(0,0,0,0.18)]">
                                            <template x-for="corner in ['nw', 'ne', 'sw', 'se']">
                                                <button type="button" @pointerdown.stop.prevent="beginTransform($event, 'scale')" :class="{'-left-3.5 -top-3.5 cursor-nwse-resize': corner === 'nw', '-right-3.5 -top-3.5 cursor-nesw-resize': corner === 'ne', '-bottom-3.5 -left-3.5 cursor-nesw-resize': corner === 'sw', '-bottom-3.5 -right-3.5 cursor-nwse-resize': corner === 'se'}" class="absolute flex h-7 w-7 touch-none items-center justify-center rounded-lg border-2 border-white bg-coral text-sm font-black leading-none text-white shadow-lg ring-1 ring-ink/25" :aria-label="`Resize character from ${corner} corner`">
                                                    <span aria-hidden="true" x-text="({nw: '↖', ne: '↗', sw: '↙', se: '↘'})[corner]"></span>
                                                </button>
                                            </template>
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
                        <div class="mt-6">
                            <p class="text-sm font-bold text-ink">Product examples</p>
                            <p class="mt-1 text-xs text-muted">Personalised examples shown for inspiration.</p>
                            <div class="mt-3 flex flex-wrap gap-3">
                                @foreach($productExamples as $image)
                                    <button type="button" x-show="!examplesFollowVariant || selectedVariantId === @js($image->product_variant_id)" @click="previewMode = 'product'; exampleUrl = @js($image->url()); exampleAlt = @js($image->alt_text)" class="cursor-pointer overflow-hidden rounded-xl bg-white ring-2 ring-transparent focus:ring-coral" :class="previewMode === 'product' && exampleUrl === @js($image->url()) ? 'ring-coral' : ''">
                                        <img src="{{ $image->url() }}" alt="{{ $image->alt_text }}" class="h-24 w-28 object-cover">
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>

                <div class="self-center">
                    <h1 class="font-display text-5xl">{{ $templated ? $session->product->name : ($session->status->value === 'approved' ? 'This is the one.' : 'Your artwork is ready.') }}</h1>
                    <p class="mt-5 leading-7 text-muted">{{ $templated ? $session->product->short_description : 'Review the generated artwork before continuing.' }}</p>
                    @if($session->product->categories->isNotEmpty())<p class="mt-4 text-sm text-muted"><span class="font-semibold text-ink">Categories:</span> @foreach($session->product->categories as $category)<a class="hover:text-coral hover:underline" href="{{ route('categories.show', $category) }}">{{ $category->name }}</a>@unless($loop->last) <span aria-hidden="true">·</span> @endunless @endforeach</p>@endif

                    @if($templated && $session->status->value === 'preview_ready')
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

                    @error('design')<p class="mt-4 rounded-xl bg-red-50 p-3 text-red-700">{{ $message }}</p>@enderror
                    <p x-show="colourError" x-text="colourError" class="mt-4 rounded-xl bg-red-50 p-3 text-sm text-red-700"></p>
                    <p x-show="layoutError" x-text="layoutError" class="mt-4 rounded-xl bg-red-50 p-3 text-sm text-red-700"></p>
                    <p x-show="nameError" x-text="nameError" class="mt-4 rounded-xl bg-red-50 p-3 text-sm text-red-700"></p>

                    @if($templated && $session->status->value === 'preview_ready')
                        <fieldset class="mt-8">
                            <legend class="font-display text-xl">{{ $session->product->preview_configuration['variant_label'] ?? 'Bottle colour' }}</legend>
                            <div class="bottle-colour-options mt-4 grid grid-cols-4 gap-2">
                                @foreach($bottleVariants as $variant)
                                    @php($colourLabel = strtolower($variant->options['colour'] ?? '') === 'grey' ? 'Gray' : str($variant->options['colour'] ?? '')->title())
                                    <label class="selection-card min-w-0" :class="changingColour ? 'pointer-events-none opacity-60' : ''">
                                        <input class="sr-only" type="radio" name="preview_variant_id" value="{{ $variant->id }}" :checked="selectedVariantId === @js($variant->id)" @change="chooseVariant(@js($variant->id))" :disabled="changingColour">
                                        <span><strong class="block">{{ $colourLabel }}</strong><small class="block">{{ str($variant->options['size'] ?? '')->replace('ml', ' ml') }}</small><small class="block">{{ $variant->formattedPrice() }}</small></span>
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>
                    @endif

                    <form method="POST" action="{{ route('artwork.cart', $session->public_id) }}" class="mt-9">
                        @csrf
                        <input type="hidden" name="asset_id" :value="selectedAssetId">
                        <input type="hidden" name="design_id" :value="selectedDesignId">
                        <button class="button-primary w-full" :disabled="changingColour || updatingName || nameValue !== savedNameValue">Add to basket</button>
                    </form>
                    <form method="POST" action="{{ route('artwork.change', $session->public_id) }}" class="mt-4 text-center">
                        @csrf
                        <input type="hidden" name="design_id" :value="selectedDesignId">
                        <button class="cursor-pointer font-bold text-coral underline">Change artwork</button>
                    </form>

                    <div class="mt-10 border-t border-rose/25 pt-8">
                        <h2 class="font-display text-2xl">About your gift</h2>
                        <p class="mt-4 leading-7 text-muted">{{ $session->product->description }}</p>
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
    Alpine.data('artworkProgress', (url, interval) => ({
        message: 'Preparing your photo…',
        start() {
            const poll = async () => {
                const response = await fetch(url, {headers: {Accept: 'application/json'}});
                if (!response.ok) return;
                const data = await response.json();
                this.message = data.message;
                if (['preview_ready', 'failed', 'approved', 'expired'].includes(data.status)) {
                    location.reload();
                    return;
                }
                setTimeout(poll, interval);
            };
            setTimeout(poll, interval);
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
                this.savedNameValue = submittedName;
            } catch (error) {
                this.nameError = error.message || 'We could not update that name. Please try again.';
            } finally {
                this.updatingName = false;
            }
        },
        beginTransform(event, mode) {
            if (!this.editable || this.changingColour || this.updatingName) return;
            this.transformMode = mode;
            const startX = event.clientX;
            const startY = event.clientY;
            const startOffsetX = this.offsetX;
            const startOffsetY = this.offsetY;
            const startScale = this.scale;
            const canvas = this.$refs.editorCanvas.getBoundingClientRect();
            const zone = this.$refs.characterZone.getBoundingClientRect();
            const zoneCenterX = zone.left + (zone.width / 2);
            const zoneCenterY = zone.top + (zone.height / 2);
            const startDistance = Math.max(1, Math.hypot(startX - zoneCenterX, startY - zoneCenterY));
            const move = pointer => {
                if (mode === 'move') {
                    this.offsetX = startOffsetX + ((pointer.clientX - startX) / canvas.width);
                    this.offsetY = startOffsetY + ((pointer.clientY - startY) / canvas.height);
                } else {
                    const distance = Math.hypot(pointer.clientX - zoneCenterX, pointer.clientY - zoneCenterY);
                    this.scale = Math.max(.1, Math.min(50, startScale * (distance / startDistance)));
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
                this.selectedDesignId = data.design_id;
                this.selectedDesignUrl = `${data.preview_url}?v=${version}`;
                this.selectedLayoutUrl = data.layout_url;
                this.editorBackgroundUrl = `${data.background_url}?v=${version}`;
            } catch (error) {
                this.layoutError = error.message || 'We could not save those changes. Please try again.';
            } finally {
                this.savingLayout = false;
            }
        },
        async chooseVariant(variantId) {
            if (variantId === this.selectedVariantId || this.changingColour || this.updatingName) return;
            const previousVariantId = this.selectedVariantId;
            const previousSurfaceColour = this.surfaceColour;
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
                if (this.examplesFollowVariant) {
                    const firstExample = this.variantExamples[variantId]?.[0];
                    this.exampleUrl = firstExample?.url || null;
                    this.exampleAlt = firstExample?.alt || 'Product example';
                }
            } catch (error) {
                this.selectedVariantId = previousVariantId;
                this.surfaceColour = previousSurfaceColour;
                this.colourError = error.message || 'We could not update that colour. Please try again.';
            } finally {
                this.changingColour = false;
            }
        },
    }));
});
</script>
