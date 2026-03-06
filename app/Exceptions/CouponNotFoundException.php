<?php

namespace App\Exceptions;

use Exception;
use Symfony\Component\HttpFoundation\Response;

final class CouponNotFoundException extends Exception
{
    public function __construct(int|string $identifier)
    {
        parent::__construct(
            "Coupon '{$identifier}' not found.",
            Response::HTTP_NOT_FOUND,
        );
    }
}
