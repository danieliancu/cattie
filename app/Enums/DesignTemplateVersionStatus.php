<?php

namespace App\Enums;

enum DesignTemplateVersionStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Retired = 'retired';
}
