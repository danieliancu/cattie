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
}
