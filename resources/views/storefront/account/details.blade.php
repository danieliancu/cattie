<x-layouts.storefront title="My Details | Kattie.uk" description="Manage your Kattie.uk contact and delivery details.">
<section class="shell py-12 sm:py-20"><div class="mx-auto max-w-5xl">
    <p class="eyebrow">My Account</p>
    <h1 class="mt-3 font-display text-5xl">My Details</h1>
    <p class="mt-4 text-muted">Your details will be used to make checkout quicker.</p>
    @include('storefront.account._nav')

    <div class="mt-10 rounded-[2rem] bg-white p-6 sm:p-9" x-data="{
        status: 'idle',
        async save(name, value) { await this.saveFields({[name]: value}); },
        async saveFields(fields) {
            this.status = 'saving';
            try {
                const response = await fetch(@js(route('account.details.update')), {
                    method: 'PATCH',
                    headers: {'X-CSRF-TOKEN': @js(csrf_token()), 'Accept': 'application/json', 'Content-Type': 'application/json'},
                    body: JSON.stringify(fields),
                });
                this.status = response.ok ? 'saved' : 'error';
            } catch (e) {
                this.status = 'error';
            }
        },
        saveAll(root) {
            const fields = {};
            root.querySelectorAll('[name]').forEach((input) => { if (input.name !== 'country') fields[input.name] = input.value; });
            this.saveFields(fields);
        }
    }" @customer-details-field-changed="save($event.detail.name, $event.detail.value)"
       @customer-details-fields-changed="saveFields($event.detail.fields)" x-ref="detailsRoot">
        <x-customer-details-form :values="$values" :lookup-url="route('address-lookup')"
            :autocomplete-url="route('address-autocomplete')"
            :autocomplete-resolve-url-template="route('address-autocomplete.resolve', ['placeId' => '__PLACE_ID__'])" />
        <div class="mt-6 flex items-center gap-4">
            <button type="button" class="button-primary" @click="saveAll($refs.detailsRoot)">Save</button>
            <p class="text-sm font-bold" x-cloak>
                <span x-show="status === 'saving'" class="text-muted">Saving…</span>
                <span x-show="status === 'saved'" class="text-emerald-700">✓ Saved</span>
                <span x-show="status === 'error'" class="text-red-700">Couldn't save — try again</span>
            </p>
        </div>
    </div>
</div></section>
</x-layouts.storefront>
