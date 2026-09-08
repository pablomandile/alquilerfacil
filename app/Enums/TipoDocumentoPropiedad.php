<?php

namespace App\Enums;

/**
 * Los papeles que acompañan a una propiedad: la escritura, el reglamento de
 * copropiedad, los planos, los impuestos, etc. No confundir con
 * {@see TipoDocumentoContrato}, que son los del contrato de alquiler.
 */
enum TipoDocumentoPropiedad: string implements Etiquetable
{
    case Escritura = 'escritura';
    case ReglamentoCopropiedad = 'reglamento_copropiedad';
    case Plano = 'plano';
    case BoletoCompraventa = 'boleto_compraventa';
    case ImpuestoInmobiliario = 'impuesto_inmobiliario';
    case Expensas = 'expensas';
    case Servicios = 'servicios';
    case Seguro = 'seguro';
    case Fotos = 'fotos';
    case Otro = 'otro';

    public function label(): string
    {
        return match ($this) {
            self::Escritura => 'Escritura',
            self::ReglamentoCopropiedad => 'Reglamento de copropiedad',
            self::Plano => 'Plano',
            self::BoletoCompraventa => 'Boleto de compraventa',
            self::ImpuestoInmobiliario => 'Impuesto inmobiliario / ABL',
            self::Expensas => 'Expensas',
            self::Servicios => 'Servicios (luz, gas, agua)',
            self::Seguro => 'Seguro',
            self::Fotos => 'Fotos',
            self::Otro => 'Otro',
        };
    }
}
