@props(['text'])
{{--
    Intro copy that is clamped to two lines on mobile, with an inline "… See more"
    sitting at the end of the second line. Desktop always shows the full text.

    The toggle only appears when the copy actually overflows, so short intros are
    never given a pointless control. Measurement re-runs on resize because the
    clamp is breakpoint-dependent.
--}}
<div
    class="relative mt-4 sm:mt-6"
    x-data="{
        expanded: false,
        overflowing: false,
        measure() {
            if (this.expanded) return;
            this.overflowing = this.$refs.copy.scrollHeight > this.$refs.copy.clientHeight + 1;
        },
    }"
    x-init="$nextTick(() => measure()); window.addEventListener('resize', () => measure())"
>
    {{-- The clamp is a static class, not an Alpine binding: bound, the copy would
         paint in full and collapse once Alpine boots, shifting the layout on every
         load. The full text stays in the DOM either way — this only clips it
         visually, so search engines still see all of it. --}}
    <p x-ref="copy" class="line-clamp-2 leading-7 text-muted sm:line-clamp-none sm:text-lg sm:leading-8" :class="expanded && '!line-clamp-none'">{{ $text }}</p>

    <button type="button" x-show="overflowing && ! expanded" x-cloak @click="expanded = true"
            class="see-more-fade absolute bottom-0 right-0 pl-8 font-semibold text-ink sm:hidden" style="top:28px;">… See more</button>

    <button type="button" x-show="expanded" x-cloak @click="expanded = false; $nextTick(() => measure())"
            class="mt-1 font-semibold text-ink sm:hidden">See less</button>
</div>
