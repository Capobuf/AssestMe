<?php

declare(strict_types=1);

namespace App\Enums;

enum BillingFrequency: string
{
    case OneOff = 'one_off';
    case Monthly = 'monthly';
    case Yearly = 'yearly';
    case Custom = 'custom';
}
