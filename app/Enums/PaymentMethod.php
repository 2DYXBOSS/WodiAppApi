<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Cash = 'cash';
    case OrangeMoney = 'orange_money';
    case MtnMomo = 'mtn_momo';
    case Wave = 'wave';
    case Card = 'card';
    case Other = 'other';
}
