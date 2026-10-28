<?php

/*
| Investor Pass list prices may be stored in USD or other currencies. M-Pesa
| always collects whole Kenyan shillings. When no manual checkout_amount is
| saved on the plan, these rates convert the list price at checkout time.
|
| Administrators can still override with an explicit KES checkout_amount on
| the plan. That override takes precedence and is not re-calculated.
*/

return [

    'fx_to_kes' => [
        'USD' => (float) env('SUBSCRIPTION_USD_TO_KES', 129),
        'EUR' => env('SUBSCRIPTION_EUR_TO_KES') !== null ? (float) env('SUBSCRIPTION_EUR_TO_KES') : null,
        'GBP' => env('SUBSCRIPTION_GBP_TO_KES') !== null ? (float) env('SUBSCRIPTION_GBP_TO_KES') : null,
    ],

];
