<?php

namespace App\Enums;

enum ArtworkSessionStatus: string
{
    case AwaitingUpload = 'awaiting_upload';
    case PreparingPhoto = 'preparing_photo';
    case Generating = 'generating';
    case PreviewReady = 'preview_ready';
    case Failed = 'failed';
    case Approved = 'approved';
    case Expired = 'expired';
}
