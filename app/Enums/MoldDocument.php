<?php

namespace App\Enums;

enum MoldDocument: string
{
    case FactoryApproval = 'factory_approval';
    case ClientApproval = 'client_approval';
    case Contract = 'contract';

    public function label(): string
    {
        return match ($this) {
            self::FactoryApproval => 'Fabrika PDF',
            self::ClientApproval => 'Firma PDF',
            self::Contract => 'Sözleşme PDF',
        };
    }
}
