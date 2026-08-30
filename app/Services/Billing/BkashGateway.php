<?php

namespace App\Services\Billing;

class BkashGateway extends AbstractStubGateway
{
    public function key(): string
    {
        return 'bkash';
    }
}
