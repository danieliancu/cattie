<x-layouts.storefront title="Order Support | Kattie.uk" description="Something wrong with your Kattie.uk order? Tell us what happened and we'll make it right.">
<section class="shell py-12 sm:py-20"><div class="mx-auto max-w-2xl">
    <p class="eyebrow">Order Support</p>
    <h1 class="mt-3 font-display text-5xl">Something wrong with your order?</h1>
    <p class="mt-5 text-lg leading-8 text-muted">We'll make it right.</p>
    <p class="mt-3 leading-7 text-muted">If we've made a mistake, we'll replace it — quickly and without fuss. Tell us what happened and send us a photo so we can help.</p>

    @if ($errors->has('order_details'))
        <p class="mt-6 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-800">{{ $errors->first('order_details') }}</p>
    @endif

    @php($showForm = ! (auth()->check() && $orders && $orders->isEmpty()))

    <form method="POST" action="{{ route('order-support.store') }}" enctype="multipart/form-data" class="mt-8 rounded-[2rem] bg-white p-7 sm:p-9">
        @csrf

        @auth
            @if ($orders && $orders->isEmpty())
                <p class="rounded-2xl bg-sand p-5 text-sm text-muted">You don't have any orders yet, so there's nothing to report a problem with. If you think this is wrong, please get in touch another way.</p>
            @else
                <div class="mb-6">
                    <label for="order_number" class="form-label">Order number</label>
                    <select id="order_number" name="order_number" required aria-describedby="order_number-help" class="form-control">
                        @foreach ($orders as $order)
                            <option value="{{ $order->number }}" @selected(old('order_number', $selectedOrderNumber) === $order->number)>
                                {{ $order->number }} — {{ ($order->placed_at ?? $order->created_at)->format('j F Y') }}@if($order->items->first())· {{ $order->items->first()->product_name }}@endif
                            </option>
                        @endforeach
                    </select>
                    <p id="order_number-help" class="mt-2 text-xs text-muted">Choose the order you need help with.</p>
                    @error('order_number')<p class="mt-2 text-sm font-semibold text-red-700">{{ $message }}</p>@enderror
                </div>
            @endif
        @else
            <div class="mb-6">
                <label for="order_number" class="form-label">Order number</label>
                <input id="order_number" name="order_number" type="text" required value="{{ old('order_number', $selectedOrderNumber) }}" class="form-control" placeholder="e.g. CAT-2608-ABC123">
                @error('order_number')<p class="mt-2 text-sm font-semibold text-red-700">{{ $message }}</p>@enderror
            </div>
            <div class="mb-6">
                <label for="email" class="form-label">Email address</label>
                <input id="email" name="email" type="email" required value="{{ old('email') }}" class="form-control" placeholder="The email used on your order">
                @error('email')<p class="mt-2 text-sm font-semibold text-red-700">{{ $message }}</p>@enderror
            </div>
        @endauth

        @if ($showForm)
            <div class="mb-6">
                <label for="message" class="form-label">What went wrong?</label>
                <textarea id="message" name="message" rows="6" required minlength="10" maxlength="5000" aria-describedby="message-help" class="form-control">{{ old('message') }}</textarea>
                <p id="message-help" class="mt-2 text-xs text-muted">Tell us what happened and what you expected to receive.</p>
                @error('message')<p class="mt-2 text-sm font-semibold text-red-700">{{ $message }}</p>@enderror
            </div>

            <div class="mb-7">
                <label for="photo" class="form-label">Photo <span class="font-normal text-muted">(optional)</span></label>
                <input id="photo" name="photo" type="file" accept="image/jpeg,image/png,image/webp" class="form-control">
                <p class="mt-2 text-xs text-muted">JPEG, PNG or WebP, up to 10 MB.</p>
                @error('photo')<p class="mt-2 text-sm font-semibold text-red-700">{{ $message }}</p>@enderror
            </div>

            <button type="submit" class="button-primary w-full">Send</button>
        @endif
    </form>
</div></section>
</x-layouts.storefront>
