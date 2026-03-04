<?php

namespace App\Enums;

enum InventoryChangeType: string
{
    case Addition   = 'addition';
    case Removal    = 'removal';
    case Sale       = 'sale';
    case Return     = 'return';
    case Adjustment = 'adjustment';
    case Transfer   = 'transfer';
}

