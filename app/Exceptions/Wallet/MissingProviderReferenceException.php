<?php

namespace App\Exceptions\Wallet;

use Exception;

class MissingProviderReferenceException extends Exception
{
    protected $message = 'Provider reference is required for this payment method';
}