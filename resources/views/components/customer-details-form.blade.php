@props(['values' => [], 'lookupUrl', 'autocompleteUrl' => null, 'autocompleteResolveUrlTemplate' => null, 'showSaveDefaultCheckbox' => false])
<div class="grid gap-5 sm:grid-cols-2" x-data="{
    postcode: @js($values['postcode'] ?? ''),
    query: '',
    suggestions: [],
    searching: false,
    async search() {
        if (! this.query || this.query.trim().length < 3) { this.suggestions = []; return; }
        this.searching = true;
        try {
            const response = await fetch(@js($autocompleteUrl) + '?q=' + encodeURIComponent(this.query), {headers: {'Accept': 'application/json'}});
            const data = await response.json();
            this.suggestions = data.suggestions ?? [];
        } catch (e) {
            this.suggestions = [];
        } finally {
            this.searching = false;
        }
    },
    async pickSuggestion(id, root) {
        this.suggestions = [];
        this.query = '';
        try {
            const url = @js($autocompleteResolveUrlTemplate).replace('__PLACE_ID__', encodeURIComponent(id));
            const response = await fetch(url, {headers: {'Accept': 'application/json'}});
            const data = await response.json();
            if (! data.resolved) return;
            root.querySelector('[name=address_line_1]').value = data.address_line_1 ?? '';
            root.querySelector('[name=address_line_2]').value = data.address_line_2 ?? '';
            root.querySelector('[name=city]').value = data.city ?? '';
            root.querySelector('[name=county]').value = data.county ?? '';
            if (data.postcode) this.postcode = data.postcode;
            root.querySelector('[name=address_line_1]').dispatchEvent(new Event('blur'));
            root.querySelector('[name=city]').dispatchEvent(new Event('blur'));
        } catch (e) {}
    },
}" x-ref="root">
    <h2 class="font-display text-xl sm:col-span-2">Contact details</h2>

    <label class="block"><span class="form-label" for="cdf-first-name">First name</span>
        <input id="cdf-first-name" class="form-control" type="text" name="first_name" autocomplete="given-name"
            value="{{ $values['first_name'] ?? '' }}" required maxlength="100"
            @blur="$dispatch('customer-details-field-changed', {name: 'first_name', value: $event.target.value})"></label>

    <label class="block"><span class="form-label" for="cdf-last-name">Last name</span>
        <input id="cdf-last-name" class="form-control" type="text" name="last_name" autocomplete="family-name"
            value="{{ $values['last_name'] ?? '' }}" required maxlength="100"
            @blur="$dispatch('customer-details-field-changed', {name: 'last_name', value: $event.target.value})"></label>

    <label class="block"><span class="form-label" for="cdf-email">Email address</span>
        <input id="cdf-email" class="form-control" type="email" name="email" autocomplete="email"
            value="{{ $values['email'] ?? '' }}" required maxlength="254"
            @blur="$dispatch('customer-details-field-changed', {name: 'email', value: $event.target.value})"></label>

    <label class="block"><span class="form-label" for="cdf-phone">Phone (optional)</span>
        <input id="cdf-phone" class="form-control" type="tel" name="phone" autocomplete="tel"
            value="{{ $values['phone'] ?? '' }}" maxlength="30"
            @blur="$dispatch('customer-details-field-changed', {name: 'phone', value: $event.target.value})"></label>

    <h2 class="mt-2 font-display text-xl sm:col-span-2">Delivery address</h2>

    @if($autocompleteUrl)
        <label class="block sm:col-span-2"><span class="form-label" for="cdf-address-search">Search for your address</span>
            <div class="relative">
                <input id="cdf-address-search" class="form-control pr-10" type="text" autocomplete="off"
                    autocorrect="off" spellcheck="false" role="combobox" aria-autocomplete="list"
                    data-lpignore="true" data-1p-ignore="true" data-form-type="other"
                    x-model="query" @input.debounce.300ms="search()" placeholder="Start typing your address…">
                <i data-lucide="chevron-down" class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted" aria-hidden="true"></i>
            </div>
            <template x-if="suggestions.length > 0">
                <div class="mt-2 rounded-xl border border-rose/30 bg-white p-2">
                    <template x-for="s in suggestions" :key="s.id">
                        <button type="button" class="block w-full rounded-lg px-3 py-2 text-left text-sm hover:bg-sand/40"
                            x-text="s.description" @click="pickSuggestion(s.id, $refs.root)"></button>
                    </template>
                </div>
            </template>
            <p class="mt-2 text-xs text-muted">Or enter your address manually below.</p>
        </label>
    @endif

    <label class="block sm:col-span-2"><span class="form-label" for="cdf-address-1">Address line 1</span>
        <input id="cdf-address-1" class="form-control" type="text" name="address_line_1" autocomplete="shipping address-line1"
            value="{{ $values['address_line_1'] ?? '' }}" required maxlength="150"
            @blur="$dispatch('customer-details-field-changed', {name: 'address_line_1', value: $event.target.value})"></label>

    <div class="grid grid-cols-3 gap-5 sm:col-span-2">
        <label class="col-span-2 block"><span class="form-label" for="cdf-address-2">Address line 2 (optional)</span>
            <input id="cdf-address-2" class="form-control" type="text" name="address_line_2" autocomplete="shipping address-line2"
                value="{{ $values['address_line_2'] ?? '' }}" maxlength="150"
                @blur="$dispatch('customer-details-field-changed', {name: 'address_line_2', value: $event.target.value})"></label>

        <label class="col-span-1 block"><span class="form-label" for="cdf-postcode">Postcode</span>
            <input id="cdf-postcode" class="form-control" type="text" name="postcode" autocomplete="shipping postal-code"
                value="{{ $values['postcode'] ?? '' }}" x-model="postcode" required maxlength="10"
                @blur="$dispatch('customer-details-field-changed', {name: 'postcode', value: postcode})"></label>
    </div>

    <label class="block"><span class="form-label" for="cdf-city">Town or city</span>
        <input id="cdf-city" class="form-control" type="text" name="city" autocomplete="shipping address-level2"
            value="{{ $values['city'] ?? '' }}" required maxlength="100"
            @blur="$dispatch('customer-details-field-changed', {name: 'city', value: $event.target.value})"></label>

    <label class="block"><span class="form-label" for="cdf-county">County (optional)</span>
        <input id="cdf-county" class="form-control" type="text" name="county" autocomplete="shipping address-level1"
            value="{{ $values['county'] ?? '' }}" maxlength="100"
            @blur="$dispatch('customer-details-field-changed', {name: 'county', value: $event.target.value})"></label>

    <input type="hidden" name="country" value="GB">

    @if($showSaveDefaultCheckbox)
        <label class="flex items-center gap-2 text-sm sm:col-span-2">
            <input type="checkbox" name="save_default_address" value="1" class="h-4 w-4 rounded border-rose/40 text-coral focus:ring-coral">
            Save this as my default address
        </label>
    @endif
</div>
