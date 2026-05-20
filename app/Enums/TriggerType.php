<?php

namespace App\Enums;

enum TriggerType: string
{
    case MANUAL = 'manual';
    case WEBHOOK = 'webhook';
    case SCHEDULED = 'scheduled';
}
