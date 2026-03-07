<?php

namespace App\Enums;

enum ReturnRequestStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Returning = 'returning';
    case Rejected = 'rejected';
}
