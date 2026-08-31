<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Platform Fee Rate
    |--------------------------------------------------------------------------
    |
    | This value determines the fee percentage charged by the platform
    | on campaign disbursements and used in statistics calculations.
    | Expressed as a decimal (e.g., 0.05 = 5%, 0.10 = 10%).
    |
    */

    'platform_fee' => env('PLATFORM_FEE_RATE', 0.05),

];
