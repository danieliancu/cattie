<div class="mt-5 overflow-x-auto rounded-2xl border border-rose/25">
    <table class="min-w-full border-collapse text-left text-sm">
        <caption class="sr-only">Estimated United Kingdom delivery times after production</caption>
        <thead class="bg-sand text-ink">
            <tr>
                <th scope="col" class="whitespace-nowrap px-4 py-3 font-bold">Delivery method</th>
                <th scope="col" class="whitespace-nowrap px-4 py-3 font-bold">After dispatch</th>
                <th scope="col" class="whitespace-nowrap px-4 py-3 font-bold">Estimated total</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-rose/20 bg-white text-muted">
            @foreach($methods as $method)
                <tr>
                    <th scope="row" class="whitespace-nowrap px-4 py-3 font-semibold text-ink">{{ $method['method'] }}</th>
                    <td class="whitespace-nowrap px-4 py-3">{{ $method['after_dispatch'] }}</td>
                    <td class="whitespace-nowrap px-4 py-3">{{ $method['estimated_total'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
<p class="mt-4 text-sm leading-6 text-muted">{{ $disclaimer }}</p>
