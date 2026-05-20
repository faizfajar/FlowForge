<?php

namespace App\Enums;

enum StepType: string
{
    case HTTP_CALL = 'HTTP_CALL';
    case SCRIPT = 'SCRIPT';
    case DELAY = 'DELAY';
    case CONDITION = 'CONDITION';
}
