<?php

namespace App\Exceptions\Wallet;

use Exception;

class InvalidOrderStatusException extends Exception
{
    protected $message = 'Invalid order status for this operation';
}