<?php

namespace App\Exceptions\Wallet;

use Exception;

class InvalidTopUpProviderException extends Exception
{
    protected $message = 'Invalid or inactive top-up provider';
}