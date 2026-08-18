<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum OrderStatus: string implements HasColor, HasLabel
{
    case Draft = 'draft';
    case Personalising = 'personalising';
    case GeneratingArtwork = 'generating_artwork';
    case AwaitingApproval = 'awaiting_approval';
    case Approved = 'approved';
    case AwaitingPayment = 'awaiting_payment';
    case Paid = 'paid';
    case PreparingPrintAsset = 'preparing_print_asset';
    case SubmittedToFulfilment = 'submitted_to_fulfilment';
    case InProduction = 'in_production';
    case Shipped = 'shipped';
    case Delivered = 'delivered';
    case GenerationFailed = 'generation_failed';
    case PaymentFailed = 'payment_failed';
    case FulfilmentFailed = 'fulfilment_failed';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';

    public function customerLabel(): string
    {
        return match ($this) {
            self::Draft, self::Personalising, self::GeneratingArtwork, self::AwaitingApproval, self::Approved => 'Being prepared',
            self::AwaitingPayment => 'Awaiting payment',
            self::Paid => 'Paid',
            self::PreparingPrintAsset => 'Preparing your order',
            self::SubmittedToFulfilment => 'Sent to production',
            self::InProduction => 'In production',
            self::Shipped => 'Shipped',
            self::Delivered => 'Delivered',
            self::GenerationFailed, self::FulfilmentFailed => 'Order issue',
            self::PaymentFailed => 'Payment issue',
            self::Cancelled => 'Cancelled',
            self::Refunded => 'Refunded',
        };
    }

    public function getLabel(): string
    {
        return $this->customerLabel();
    }

    // Badge colours for the admin (Filament) — mirrors the storefront order-status pill
    // (resources/views/components/order-status-pill.blade.php) so "Paid" reads green everywhere.
    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Paid, self::Delivered => 'success',
            self::Cancelled, self::Refunded, self::GenerationFailed, self::PaymentFailed, self::FulfilmentFailed => 'danger',
            self::Shipped, self::InProduction, self::SubmittedToFulfilment, self::PreparingPrintAsset => 'info',
            default => 'gray',
        };
    }
}
