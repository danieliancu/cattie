<?php

namespace App\Enums;

enum ComposedDesignStatus: string
{
    case Ready = 'ready';
    case Approved = 'approved';
    case Failed = 'failed';
}
