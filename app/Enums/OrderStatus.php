<?php

namespace App\Enums;

enum OrderStatus: string
{
    case PLACED = 'placed';
    case ROUTED = 'routed';
    case DELIVERED = 'delivered';
}
