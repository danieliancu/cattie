@props(['tone' => 'light', 'avatars' => ['1', '2', '3']])
{{-- Shared "Loved by families" social-proof group used by both the mobile and desktop heroes.
     tone="light" = white text (over the photo, mobile); tone="dark" = ink text (over the cream hero, desktop).
     avatars = which review images to show (mobile shows a few; desktop shows everyone). --}}
@php
    $nameClass = $tone === 'dark' ? 'text-ink' : 'text-white';
    $ratingClass = $tone === 'dark' ? 'text-muted' : 'text-white/80';
    $scoreClass = $tone === 'dark' ? 'text-ink' : 'text-white';
@endphp
<div {{ $attributes->merge(['class' => 'flex items-center gap-3']) }}>
    <div class="flex -space-x-3">
        @foreach($avatars as $avatar)<img src="{{ asset('images/reviews/'.$avatar.'.jfif') }}" alt="" aria-hidden="true" class="h-9 w-9 rounded-full border-2 border-white object-cover shadow-sm">@endforeach
    </div>
    <div class="text-xs leading-tight">
        <p class="font-bold {{ $nameClass }}">Loved by families</p>
        <p class="mt-0.5 {{ $ratingClass }}"><span class="text-green-400" aria-hidden="true">★★★★★</span> <span class="font-semibold {{ $scoreClass }}">4.9/5</span></p>
    </div>
</div>
