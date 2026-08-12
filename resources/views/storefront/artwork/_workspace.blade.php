<section class="{{ in_array($session->status->value, ['preparing_photo', 'generating']) ? 'fixed inset-0 z-50 flex items-center justify-center bg-ink/55 p-4' : 'shell py-12 sm:py-20' }}" @if(in_array($session->status->value, ['preparing_photo', 'generating'])) x-data="artworkProgress(@js(route('artwork.status', $session->public_id)), @js(config('artwork.poll_interval_ms')))" x-init="start()" @endif>
    <div class="mx-auto w-full max-w-5xl">
        @unless(in_array($session->status->value, ['preparing_photo', 'generating']))
            <p class="eyebrow text-center">{{ $session->product->name }}</p>
        @endunless

        @if($session->status->value === 'awaiting_upload')
            <div class="mx-auto mt-8 max-w-2xl text-center">
                <h1 class="font-display text-5xl">Upload your photo</h1>
                <p class="mt-4 text-muted">Use a clear photo where the face is easy to see.</p>
                <form action="{{ route('artwork.upload', $session->public_id) }}" method="POST" enctype="multipart/form-data" class="mt-10">
                    @csrf
                    <label class="flex min-h-72 cursor-pointer flex-col items-center justify-center rounded-[2rem] border-2 border-dashed border-coral/50 bg-white p-8">
                        <span class="font-display text-3xl">Choose a favourite photo</span>
                        <span class="mt-3 text-sm text-muted">JPEG, PNG or WebP · up to 10 MB</span>
                        <input class="mt-6 block max-w-full" type="file" name="photo" accept="image/jpeg,image/png,image/webp" required>
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
            @php($bottleVariants = $session->product->variants->filter(fn ($variant) => $variant->is_active && in_array(strtolower($variant->options['colour'] ?? ''), ['black', 'gray', 'grey', 'navy', 'red'], true))->values())
            @php($savedName = collect($session->personalisation_snapshot)->firstWhere('key', 'name')['value'] ?? '')

            <div class="mt-8 grid gap-10 lg:grid-cols-[1fr_.72fr]" x-data="designWorkspace(@js([
                'previewMode' => $templated ? 'design' : 'artwork',
                'exampleUrl' => $productExamples->first()?->url(),
                'exampleAlt' => $productExamples->first()?->alt_text ?? 'Product example',
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
                'variantUrl' => route('artwork.variant', $session->public_id),
                'nameUrl' => route('artwork.name', $session->public_id),
                'csrf' => csrf_token(),
            ]))">
                <div>
                    @if($templated)
                        <div class="mb-4 flex justify-center gap-2" role="group" aria-label="Preview type">
                            <button type="button" @click="previewMode = 'design'" :class="previewMode === 'design' ? 'bg-ink text-white' : 'bg-white text-ink'" class="rounded-full px-4 py-2 text-sm font-bold">Your bottle design</button>
                            <button type="button" @click="previewMode = 'product'" :class="previewMode === 'product' ? 'bg-ink text-white' : 'bg-white text-ink'" class="rounded-full px-4 py-2 text-sm font-bold">Product</button>
                        </div>
                    @endif

                    @if($selectedDesign)
                        <div x-show="previewMode === 'design'" x-ref="editorCanvas" class="relative aspect-[6/5] overflow-hidden rounded-[2.5rem] bg-sand shadow-2xl">
                            <img x-show="!editable" :src="selectedDesignUrl" alt="Your flat bottle print design" class="h-full w-full object-contain">
                            <template x-if="editable">
                                <div class="absolute inset-0">
                                    <img :src="editorBackgroundUrl" alt="Your personalised bottle background" class="h-full w-full object-contain">
                                    <div class="absolute overflow-visible" style="left:32%; top:9%; width:36%; height:84%">
                                        <div class="absolute inset-0 overflow-hidden">
                                            <div class="absolute" :style="`left:${50 + (offsetX / .36 * 100)}%; top:${50 + (offsetY / .84 * 100)}%; width:${scale * 100}%; height:${scale * 100}%; transform:translate(-50%,-50%)`">
                                                <img :src="characterUrl" alt="Your AI character" class="pointer-events-none h-full w-full select-none object-contain">
                                            </div>
                                        </div>
                                        <div @pointerdown.prevent="beginTransform($event, 'move')" class="absolute cursor-move touch-none border-2 border-dashed border-white/90" :style="`left:${50 + (offsetX / .36 * 100)}%; top:${50 + (offsetY / .84 * 100)}%; width:${scale * 100}%; height:${scale * 100}%; transform:translate(-50%,-50%)`">
                                            <template x-for="corner in ['nw', 'ne', 'sw', 'se']">
                                                <button type="button" @pointerdown.stop.prevent="beginTransform($event, 'scale')" :class="{'-left-2 -top-2': corner === 'nw', '-right-2 -top-2': corner === 'ne', '-bottom-2 -left-2': corner === 'sw', '-bottom-2 -right-2': corner === 'se'}" class="absolute h-4 w-4 cursor-nwse-resize rounded-full border-2 border-white bg-coral shadow" aria-label="Resize character"></button>
                                            </template>
                                        </div>
                                    </div>
                                </div>
                            </template>
                            <div x-show="changingColour || updatingName" class="absolute inset-0 flex items-center justify-center bg-white/65" aria-live="polite">
                                <span class="rounded-full bg-ink px-4 py-2 text-sm font-bold text-white" x-text="updatingName ? 'Updating name…' : 'Updating colour…'"></span>
                            </div>
                            <div x-show="savingLayout" class="absolute inset-x-0 bottom-3 mx-auto w-max rounded-full bg-ink/80 px-3 py-1 text-xs text-white">Saving…</div>
                        </div>
                        <template x-if="editable">
                            <div x-show="previewMode === 'design'" class="pointer-events-none relative -mt-[83.333%] aspect-[6/5]">
                                <button type="button" @pointerdown.stop.prevent="beginTransform($event, 'scale')" class="pointer-events-auto absolute z-20 h-4 w-4 -translate-x-1/2 -translate-y-1/2 cursor-ew-resize rounded-full border-2 border-white bg-coral shadow" :style="`left:${50 + (offsetX * 100) - (18 * scale)}%; top:${51 + (offsetY * 100)}%`" aria-label="Resize character from left"></button>
                                <button type="button" @pointerdown.stop.prevent="beginTransform($event, 'scale')" class="pointer-events-auto absolute z-20 h-4 w-4 -translate-x-1/2 -translate-y-1/2 cursor-ew-resize rounded-full border-2 border-white bg-coral shadow" :style="`left:${50 + (offsetX * 100) + (18 * scale)}%; top:${51 + (offsetY * 100)}%`" aria-label="Resize character from right"></button>
                            </div>
                        </template>
                    @endif

                    @if(!$templated && $artworkPreview)
                        <div x-show="previewMode === 'artwork'" class="aspect-[6/5] overflow-hidden rounded-[2.5rem] bg-sand shadow-2xl">
                            <img src="{{ route('artwork.assets', [$session->public_id, $artworkPreview]) }}" alt="Your generated AI artwork" class="h-full w-full object-contain">
                        </div>
                    @endif

                    @if($productExamples->isNotEmpty())
                        <div x-show="previewMode === 'product'" class="aspect-[6/5] overflow-hidden rounded-[2.5rem] bg-white shadow-2xl">
                            <img :src="exampleUrl" :alt="exampleAlt" class="h-full w-full object-contain">
                        </div>
                        <div class="mt-6">
                            <p class="text-sm font-bold text-ink">Bottle examples</p>
                            <p class="mt-1 text-xs text-muted">Official Prodigi product photography. These are not personalised mockups.</p>
                            <div class="mt-3 flex flex-wrap gap-3">
                                @foreach($productExamples as $image)
                                    <button type="button" @click="previewMode = 'product'; exampleUrl = @js($image->url()); exampleAlt = @js($image->alt_text)" class="cursor-pointer overflow-hidden rounded-xl bg-white ring-2 ring-transparent focus:ring-coral" :class="previewMode === 'product' && exampleUrl === @js($image->url()) ? 'ring-coral' : ''">
                                        <img src="{{ $image->url() }}" alt="{{ $image->alt_text }}" class="h-24 w-28 object-cover">
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>

                <div class="self-center">
                    <p class="eyebrow">{{ $session->artworkStyle->name }}</p>
                    <h1 class="mt-4 font-display text-5xl">{{ $session->status->value === 'approved' ? 'This is the one.' : ($templated ? 'Your bottle design' : 'Your artwork is ready.') }}</h1>
                    <p class="mt-5 leading-7 text-muted">{{ $templated ? 'This is the real flat print design, not a simulated bottle mockup. Your character is composed over a background made from the supplied name.' : 'Review the generated artwork before continuing.' }}</p>

                    @if($templated && $session->status->value === 'preview_ready')
                        <div class="mt-8">
                            <div class="flex items-center justify-between gap-3">
                                <label for="artwork-name" class="form-label">Name</label>
                                <span class="text-xs text-muted" x-text="`${Math.max(0, 12 - nameValue.length)} characters left`"></span>
                            </div>
                            <input id="artwork-name" class="form-control" type="text" maxlength="12" x-model="nameValue" @input="scheduleNameUpdate()">
                        </div>
                    @endif

                    @error('design')<p class="mt-4 rounded-xl bg-red-50 p-3 text-red-700">{{ $message }}</p>@enderror
                    <p x-show="colourError" x-text="colourError" class="mt-4 rounded-xl bg-red-50 p-3 text-sm text-red-700"></p>
                    <p x-show="layoutError" x-text="layoutError" class="mt-4 rounded-xl bg-red-50 p-3 text-sm text-red-700"></p>
                    <p x-show="nameError" x-text="nameError" class="mt-4 rounded-xl bg-red-50 p-3 text-sm text-red-700"></p>

                    @if($templated && $session->status->value === 'preview_ready')
                        <fieldset class="mt-8">
                            <legend class="font-display text-xl">Bottle colour</legend>
                            <div class="bottle-colour-options mt-4 grid grid-cols-4 gap-2">
                                @foreach($bottleVariants as $variant)
                                    @php($colourLabel = strtolower($variant->options['colour'] ?? '') === 'grey' ? 'Gray' : str($variant->options['colour'] ?? '')->title())
                                    <label class="selection-card min-w-0" :class="changingColour ? 'pointer-events-none opacity-60' : ''">
                                        <input class="sr-only" type="radio" name="preview_variant_id" value="{{ $variant->id }}" :checked="selectedVariantId === @js($variant->id)" @change="chooseVariant(@js($variant->id))" :disabled="changingColour">
                                        <span><strong class="block">{{ $colourLabel }}</strong><small class="block">650 ml</small><small class="block">{{ $variant->formattedPrice() }}</small></span>
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>
                    @endif

                    <form method="POST" action="{{ route('artwork.cart', $session->public_id) }}" class="mt-9">
                        @csrf
                        <input type="hidden" name="asset_id" :value="selectedAssetId">
                        <input type="hidden" name="design_id" :value="selectedDesignId">
                        <button class="button-primary w-full" :disabled="changingColour || updatingName">Add to basket</button>
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
        nameTimer: null,
        preloadImage(url) {
            return new Promise((resolve, reject) => {
                const image = new Image();
                image.onload = resolve;
                image.onerror = reject;
                image.src = url;
            });
        },
        scheduleNameUpdate() {
            clearTimeout(this.nameTimer);
            this.nameError = '';
            this.nameTimer = setTimeout(() => this.updateName(), 350);
        },
        async updateName() {
            if (this.changingColour || this.savingLayout) {
                this.nameTimer = setTimeout(() => this.updateName(), 250);
                return;
            }
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
                if (this.nameValue !== submittedName) this.scheduleNameUpdate();
            } catch (error) {
                this.nameError = error.message || 'We could not update that name. Please try again.';
            } finally {
                this.updatingName = false;
            }
        },
        beginTransform(event, mode) {
            if (!this.editable || this.changingColour || this.updatingName) return;
            const startX = event.clientX;
            const startY = event.clientY;
            const startOffsetX = this.offsetX;
            const startOffsetY = this.offsetY;
            const startScale = this.scale;
            const canvas = this.$refs.editorCanvas.getBoundingClientRect();
            const move = pointer => {
                if (mode === 'move') {
                    this.offsetX = Math.max(-.2, Math.min(.2, startOffsetX + ((pointer.clientX - startX) / canvas.width)));
                    this.offsetY = Math.max(-.2, Math.min(.2, startOffsetY + ((pointer.clientY - startY) / canvas.height)));
                } else {
                    const delta = ((pointer.clientX - startX) + (pointer.clientY - startY)) / (canvas.width + canvas.height);
                    this.scale = Math.max(.6, Math.min(1.8, startScale + (delta * 3)));
                }
            };
            const up = () => {
                window.removeEventListener('pointermove', move);
                window.removeEventListener('pointerup', up);
                this.saveLayout();
            };
            window.addEventListener('pointermove', move);
            window.addEventListener('pointerup', up, {once: true});
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
            this.selectedVariantId = variantId;
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
                this.selectedDesignId = data.design_id;
                this.selectedDesignUrl = nextPreviewUrl;
                this.selectedLayoutUrl = data.layout_url;
                this.editorBackgroundUrl = nextBackgroundUrl;
            } catch (error) {
                this.selectedVariantId = previousVariantId;
                this.colourError = error.message || 'We could not update that colour. Please try again.';
            } finally {
                this.changingColour = false;
            }
        },
    }));
});
</script>
