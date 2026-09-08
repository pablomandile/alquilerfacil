<?php

namespace App\Enums;

/**
 * Los papeles de un gasto: la factura o la expensa del período, y el
 * comprobante de que se pagó.
 */
enum TipoDocumentoGasto: string implements Etiquetable
{
    case Factura = 'factura';
    case Comprobante = 'comprobante';
    case Otro = 'otro';

    public function label(): string
    {
        return match ($this) {
            self::Factura => 'Factura / expensa',
            self::Comprobante => 'Comprobante de pago',
            self::Otro => 'Otro',
        };
    }
}
