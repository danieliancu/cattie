@props(['status'])
@php
$label = $status->customerLabel();
$classes = match (true) {
    in_array($status, [\App\Enums\OrderStatus::Paid, \App\Enums\OrderStatus::Delivered], true) => 'bg-emerald-100 text-emerald-800',
    in_array($status, [\App\Enums\OrderStatus::Cancelled, \App\Enums\OrderStatus::Refunded, \App\Enums\OrderStatus::GenerationFailed, \App\Enums\OrderStatus::PaymentFailed, \App\Enums\OrderStatus::FulfilmentFailed], true) => 'bg-red-100 text-red-800',
    in_array($status, [\App\Enums\OrderStatus::Shipped, \App\Enums\OrderStatus::InProduction, \App\Enums\OrderStatus::SubmittedToFulfilment, \App\Enums\OrderStatus::PreparingPrintAsset], true) => 'bg-sky-100 text-sky-800',
    default => 'bg-sand text-ink',
};
@endphp
<span {{ $attributes->merge(['class' => "inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-bold $classes"]) }}>{{ $label }}</span>
