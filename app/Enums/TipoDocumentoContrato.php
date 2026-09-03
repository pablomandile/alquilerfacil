<?php

namespace App\Enums;

/**
 * Los papeles que acompañan a un contrato de alquiler. No confundir con
 * {@see TipoDocumento}, que es el tipo de documento de identidad del inquilino.
 */
enum TipoDocumentoContrato: string implements Etiquetable
{
    case ContratoFirmado = 'contrato_firmado';
    case Garantia = 'garantia';
    case Pagare = 'pagare';
    case Inventario = 'inventario';
    case SeguroCaucion = 'seguro_caucion';
    case Recibo = 'recibo';
    case Otro = 'otro';

    public function label(): string
    {
        return match ($this) {
            self::ContratoFirmado => 'Contrato firmado',
            self::Garantia => 'Garantía',
            self::Pagare => 'Pagaré',
            self::Inventario => 'Inventario',
            self::SeguroCaucion => 'Seguro de caución',
            self::Recibo => 'Recibo',
            self::Otro => 'Otro',
        };
    }
}
