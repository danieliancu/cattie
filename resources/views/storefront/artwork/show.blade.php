<x-layouts.storefront :title="'Create your '.$session->product->name.' | Cattie.uk'" description="Upload your photo, preview your personalised artwork and approve the version you love.">
<section class="shell py-12 sm:py-20" @if(in_array($session->status->value,['preparing_photo','generating'])) x-data="artworkProgress(@js(route('artwork.status',$session->public_id)),@js(config('artwork.poll_interval_ms')))" x-init="start()" @endif>
<div class="mx-auto max-w-5xl"><p class="eyebrow text-center">{{ $session->product->name }}</p>
@if($session->status->value==='awaiting_upload')
<div class="mx-auto mt-8 max-w-2xl text-center"><h1 class="font-display text-5xl">Upload your photo</h1><p class="mt-4 text-muted">Use a clear photo where the face is easy to see.</p><form action="{{ route('artwork.upload',$session->public_id) }}" method="POST" enctype="multipart/form-data" class="mt-10">@csrf<label class="flex min-h-72 cursor-pointer flex-col items-center justify-center rounded-[2rem] border-2 border-dashed border-coral/50 bg-white p-8"><span class="font-display text-3xl">Choose a favourite photo</span><span class="mt-3 text-sm text-muted">JPEG, PNG or WebP · up to 10 MB</span><input class="mt-6 block max-w-full" type="file" name="photo" accept="image/jpeg,image/png,image/webp" required></label>@error('photo')<p class="mt-3 text-red-700">{{ $message }}</p>@enderror<button class="button-primary mt-7 w-full">Create my artwork →</button></form></div>
@elseif(in_array($session->status->value,['preparing_photo','generating']))
<div class="mx-auto mt-20 max-w-xl text-center" role="status" aria-live="polite"><div class="mx-auto h-20 w-20 animate-pulse rounded-full bg-gradient-to-br from-rose to-sky"></div><h1 class="mt-8 font-display text-5xl">Creating your artwork…</h1><p class="mt-4 text-muted" x-text="message">Preparing your photo…</p><p class="mt-8 text-sm text-muted">You can safely refresh this page.</p></div>
@elseif($session->status->value==='failed')
<div class="mx-auto mt-16 max-w-xl text-center"><h1 class="font-display text-5xl">We couldn’t create your artwork this time.</h1><p class="mt-5 text-muted">Your photo is still safe. You can try again.</p><form method="POST" action="{{ route('artwork.regenerate',$session->public_id) }}" class="mt-8">@csrf<button class="button-primary">Try again</button></form></div>
@elseif($session->status->value==='expired')
<div class="mx-auto mt-16 max-w-xl text-center"><h1 class="font-display text-5xl">This artwork session has expired.</h1><a href="{{ route('products.index') }}" class="button-primary mt-8">Choose a gift</a></div>
@else
@php($successful=$session->generations->filter(fn($g)=>$g->status->value==='succeeded')->flatMap(fn($g)=>$g->assets->where('kind','web_preview')))
@php($selected=$session->approvedAsset??$session->currentGeneration?->assets->firstWhere('kind','web_preview')??$successful->last())
@php($previewName=collect($session->personalisation_snapshot)->pluck('value')->first(fn($value)=>filled($value))??'Your name')
@php($productMockupUrl=$session->product->previewMockupUrl()??asset('images/mockups/glass-clean.png'))
@php($productMockupAlt=$session->product->usesStaticMockupBoundary()?'Blank product preview':'Personalised product mockup')
<div class="mt-8 grid gap-10 lg:grid-cols-[1fr_.72fr]">
<div x-data="{previewMode:'glass'}">
<div class="mb-4 flex justify-center gap-2" role="group" aria-label="Preview type"><button type="button" @click="previewMode='glass'" :class="previewMode==='glass'?'bg-ink text-white':'bg-white text-ink'" class="rounded-full px-4 py-2 text-sm font-bold">On the bottle</button><button type="button" @click="previewMode='artwork'" :class="previewMode==='artwork'?'bg-ink text-white':'bg-white text-ink'" class="rounded-full px-4 py-2 text-sm font-bold">Artwork only</button></div>
<div x-show="previewMode==='artwork'" class="overflow-hidden rounded-[2.5rem] bg-sand shadow-2xl"><img src="{{ route('artwork.assets',[$session->public_id,$selected]) }}" alt="Your generated artwork" class="aspect-[2/3] w-full object-contain"></div>
<div x-show="previewMode==='glass'" class="relative mx-auto aspect-[2/3] max-w-[34rem] overflow-hidden rounded-[2.5rem] {{ $session->product->usesStaticMockupBoundary()?'bg-white':'bg-zinc-900' }} shadow-2xl">
<img src="{{ $productMockupUrl }}" alt="{{ $productMockupAlt }}" class="absolute inset-0 z-0 h-full w-full {{ $session->product->usesStaticMockupBoundary()?'object-contain':'object-cover' }}">
@if($session->product->usesStaticMockupBoundary())
<div class="absolute inset-x-6 bottom-6 z-20 rounded-2xl bg-white/95 p-4 text-center text-sm font-semibold text-ink shadow">Bottle placement preview is being prepared. Review the generated artwork using “Artwork only”.</div>
@else
<div class="absolute z-10 overflow-hidden bg-[#f7a7b7]" style="left:31.2%;top:34.2%;width:38%;height:53.8%;border-radius:8% 8% 22% 22%">
<div class="absolute inset-0 opacity-95" aria-hidden="true">@foreach(range(0,17) as $index)<span class="absolute whitespace-nowrap font-display font-bold {{ $index%3===0?'text-white':($index%3===1?'text-[#d93f65]':'text-[#fff4f6]') }}" style="left:{{ ($index*37)%82-8 }}%;top:{{ ($index*23)%92-3 }}%;font-size:{{ 12+($index%4)*5 }}px;transform:rotate({{ [-90,-8,0,90][$index%4] }}deg)">{{ $previewName }}</span>@endforeach</div>
<img src="{{ route('artwork.assets',[$session->public_id,$selected]) }}" alt="Your character on the personalised name background" class="absolute bottom-[3%] left-1/2 z-20 h-[83%] w-[88%] -translate-x-1/2 object-contain object-bottom">
<div class="pointer-events-none absolute inset-0 z-30 bg-gradient-to-r from-white/25 via-transparent to-cyan-500/15"></div></div>
<div class="pointer-events-none absolute z-20 border border-white/20 bg-gradient-to-r from-white/10 via-transparent to-cyan-300/10" style="left:31.2%;top:34.2%;width:38%;height:53.8%;border-radius:8% 8% 22% 22%"></div>
@endif
</div>
@if($successful->count()>1)<div class="mt-5 flex gap-3">@foreach($successful as $asset)<form method="POST" action="{{ route('artwork.approve',$session->public_id) }}">@csrf<input type="hidden" name="asset_id" value="{{ $asset->id }}"><button title="Choose this version" class="rounded-xl focus:ring-2 focus:ring-coral"><img src="{{ route('artwork.assets',[$session->public_id,$asset]) }}" alt="Choose previous artwork version" class="h-24 w-20 rounded-xl object-cover"></button></form>@endforeach</div>@endif
</div>
<div class="self-center"><p class="eyebrow">{{ $session->artworkStyle->name }}</p><h1 class="mt-4 font-display text-5xl">{{ $session->status->value==='approved'?'This is the one.':'Your artwork is ready.' }}</h1><p class="mt-5 leading-7 text-muted">See your full character over a background made from their name.</p>
@if($session->status->value==='approved')<form method="POST" action="{{ route('artwork.cart',$session->public_id) }}" class="mt-9">@csrf<button class="button-primary w-full">Add to basket</button></form><form method="POST" action="{{ route('artwork.change',$session->public_id) }}" class="mt-4 text-center">@csrf<button class="font-bold text-coral underline">Change artwork</button></form>
@else<form method="POST" action="{{ route('artwork.approve',$session->public_id) }}" class="mt-9">@csrf<input type="hidden" name="asset_id" value="{{ $selected->id }}"><button class="button-primary w-full">Love it — continue</button></form>@if($session->generations->count()<config('artwork.max_generations_per_session'))<form method="POST" action="{{ route('artwork.regenerate',$session->public_id) }}" class="mt-4 text-center">@csrf<button class="font-bold text-muted underline">Try another version</button></form>@else<p class="mt-5 text-center text-sm text-muted">You’ve seen all available versions.</p>@endif
@endif</div></div>
@endif
</div></section>
<script>document.addEventListener('alpine:init',()=>Alpine.data('artworkProgress',(url,interval)=>({message:'Preparing your photo…',start(){const poll=async()=>{const r=await fetch(url,{headers:{Accept:'application/json'}});if(!r.ok)return;const d=await r.json();this.message=d.message;if(['preview_ready','failed','approved','expired'].includes(d.status)){location.reload();return}setTimeout(poll,interval)};setTimeout(poll,interval)}})))</script>
</x-layouts.storefront>
