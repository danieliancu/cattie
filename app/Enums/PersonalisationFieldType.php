<?php

namespace App\Enums;

enum PersonalisationFieldType: string
{
    case Text = 'text';
    case Textarea = 'textarea';
    case Select = 'select';
    case Date = 'date';
}
