<?php

namespace App\Enums;

enum BackingStatus: string
{
    case PENDING = 'pending';
    case COMPLETED = 'completed';
    case REFUNDED = 'refunded';
}
