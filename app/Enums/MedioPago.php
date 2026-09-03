<?php

namespace App\Enums;

enum MedioPago: string implements Etiquetable
{
    case Transferencia = 'transferencia';
    case Efectivo = 'efectivo';
    case Cheque = 'cheque';
    case MercadoPago = 'mercadopago';
    case Otro = 'otro';

    public function label(): string
    {
        return match ($this) {
            self::Transferencia => 'Transferencia',
            self::Efectivo => 'Efectivo',
            self::Cheque => 'Cheque',
            self::MercadoPago => 'Mercado Pago',
            self::Otro => 'Otro',
        };
    }
}
