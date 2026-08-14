<?php

namespace App\Enums;

enum OrderStatus: string
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
}
