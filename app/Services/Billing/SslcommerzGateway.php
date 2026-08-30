<?php

namespace App\Services\Billing;

class SslcommerzGateway extends AbstractStubGateway
{
    public function key(): string
    {
        return 'sslcommerz';
    }
}
