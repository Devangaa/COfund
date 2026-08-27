<?php

namespace App\Enums;

enum TransactionType: string
{
    case PAYMENT = 'payment';
    case DISBURSEMENT = 'disbursement';
    case REFUND = 'refund';
    case PLATFORM_FEE = 'platform_fee';
    case DEPOSIT = 'deposit';
    case WITHDRAWAL = 'withdrawal';
}
