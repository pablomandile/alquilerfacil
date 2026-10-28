<?php

namespace Database\Seeders;

use App\Enums\ACargoDe;
use App\Enums\CategoriaGasto;
use App\Enums\CategoriaTemaAdmin;
use App\Enums\EstadoContrato;
use App\Enums\EstadoPropiedad;
use App\Enums\EstadoTemaAdmin;
use App\Enums\Indice;
use App\Enums\MedioPago;
use App\Enums\RolUsuario;
use App\Enums\TipoDocumentoContrato;
use App\Enums\TipoDocumentoGasto;
use App\Enums\TipoDocumentoPropiedad;
use App\Enums\TipoGasto;
use App\Enums\TipoPropiedad;
use App\Models\AdminThread;
use App\Models\Contract;
use App\Models\Expense;
use App\Models\Owner;
use App\Models\Payment;
use App\Models\Property;
use App\Models\RentCharge;
use App\Models\Tenant;
use App\Models\TenantMessage;
use App\Models\User;
use App\Services\Ajustes\AplicadorDeAjuste;
use App\Services\Ajustes\CalculadorDeAjuste;
use App\Services\Ajustes\PropuestaDeAjuste;
use App\Services\Cobranzas\GeneradorDeCargos;
use App\Services\Repartos\RepartidorEntreDuenos;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Los datos de la demo del portfolio (`demo:reset`, sólo con APP_DEMO=true).
 *
 * Tres propietarios, cuatro propiedades (tres alquiladas y un local libre) y la
 * historia de un año: ajustes por IPC e ICL calculados con los índices REALES
 * que ya están en la base, cargos de alquiler mes a mes, pagos, gastos con sus
 * facturas en PDF y el reparto entre dueños. Todo lo derivado (ajustes, cargos,
 * repartos, estado de cada cargo) lo calculan los servicios de la app, igual que
 * en la productiva.
 *
 * Las fechas son relativas a hoy, así la demo siempre se ve al día. Nada de
 * factories: en el server no está Faker (composer --no-dev).
 */
class DemoSeeder extends Seeder
{
    private CarbonImmutable $hoy;

    private CarbonImmutable $mesActual;

    private User $demo;

    public function run(): void
    {
        mt_srand(20261028);
        $this->hoy = CarbonImmutable::today();
        $this->mesActual = $this->hoy->startOfMonth();

        $this->demo = new User;
        $this->demo->forceFill([
            'name' => 'Usuario Demo',
            'email' => config('demo.email'),
            'password' => Hash::make(config('demo.password')),
            'rol' => RolUsuario::Admin,
            'email_verified_at' => now(),
        ])->save();

        $laura = Owner::query()->create([
            'nombre' => 'Laura Giménez', 'email' => 'laura.gimenez@example.com',
            'telefono' => '11 5555-1201', 'alias_cbu' => 'LAURA.GIMENEZ.CASA',
        ]);
        $martin = Owner::query()->create([
            'nombre' => 'Martín Rossi', 'email' => 'martin.rossi@example.com',
            'telefono' => '11 5555-1202',
        ]);
        $andres = Owner::query()->create([
            'nombre' => 'Andrés Paz', 'email' => 'andres.paz@example.com',
            'telefono' => '11 5555-1203', 'alias_cbu' => 'ANDRES.PAZ.MP',
        ]);

        // Cabildo: en condominio 50/50, ajuste trimestral por IPC.
        $cabildo = $this->propiedad('Cabildo 2300 4°B', 'Av. Cabildo', '2300', '4', 'B', 3, 68, [
            $laura->id => 50, $martin->id => 50,
        ]);
        $contratoCabildo = $this->contrato($cabildo, 'Sofía Ramírez', '11 4444-5555', Indice::Ipc, 3, 14, 420000, 10);

        // Thames: de Laura sola, ajuste cada cuatro meses por ICL. Arrancó hace
        // un año, así el ajuste de este mes queda propuesto para revisar.
        $thames = $this->propiedad('Thames 1450 2°A', 'Thames', '1450', '2', 'A', 2, 47, [$laura->id => 100]);
        $contratoThames = $this->contrato($thames, 'Diego Fernández', '11 4444-6666', Indice::Icl, 4, 12, 380000, 5);

        // Gorriti: contrato nuevo, todavía sin ningún ajuste.
        $gorriti = $this->propiedad('Gorriti 4800 PB', 'Gorriti', '4800', 'PB', null, 4, 92, [$andres->id => 100]);
        $contratoGorriti = $this->contrato($gorriti, 'Valentina Ortiz', '11 4444-7777', Indice::Ipc, 6, 4, 520000, 10);

        // Local libre: aparece en propiedades y en los gastos de los dueños.
        $local = $this->propiedad('Local Juramento 2100', 'Juramento', '2100', null, null, 1, 95, [$martin->id => 100], disponible: true);

        foreach ([$contratoCabildo, $contratoThames, $contratoGorriti] as $contrato) {
            $this->historia($contrato);
        }

        $this->pagos($contratoCabildo, ultimoImpago: false, parcialEsteMes: false);
        $this->pagos($contratoThames, ultimoImpago: true, parcialEsteMes: true);
        $this->pagos($contratoGorriti, ultimoImpago: false, parcialEsteMes: null);

        // El estado sólo se recalcula al registrar un pago: los cargos sin pagos
        // con el vencimiento pasado quedarían «pendiente» en vez de «vencido».
        RentCharge::query()->each(fn (RentCharge $cargo) => $cargo->actualizarEstado());

        $this->gastosDelInquilino($contratoCabildo, 'Expensas ordinarias', 68000, 'Edenor');
        $this->gastosDelInquilino($contratoThames, 'Expensas ordinarias', 52000, 'Edesur');
        $this->gastosDelInquilino($contratoGorriti, null, 0, 'Edenor');

        foreach ([$cabildo, $thames, $gorriti, $local] as $propiedad) {
            $this->abl($propiedad);
        }

        $this->gastosExtraordinarios($cabildo, $contratoCabildo, $thames);
        $this->documentos([$contratoCabildo, $contratoThames, $contratoGorriti], [$cabildo, $thames, $gorriti, $local]);
        $this->administracion($cabildo);

        // El aviso del mes pasado ya se les mandó a los de Cabildo y Thames.
        foreach ([$cabildo, $thames] as $propiedad) {
            TenantMessage::query()->create([
                'property_id' => $propiedad->id,
                'periodo' => $this->mesActual->subMonth(),
                'enviado_at' => $this->mesActual->subMonth()->setDay(3)->setTime(10, 30),
                'enviado_por' => $this->demo->id,
            ]);
        }
    }

    /** @param  array<int, int>  $duenos  owner_id => porcentaje */
    private function propiedad(
        string $alias, string $calle, string $numero, ?string $piso, ?string $depto,
        int $ambientes, int $m2, array $duenos, bool $disponible = false,
    ): Property {
        $propiedad = Property::query()->create([
            'alias' => $alias,
            'tipo' => $disponible ? TipoPropiedad::Local : TipoPropiedad::Departamento,
            'estado' => $disponible ? EstadoPropiedad::Disponible : EstadoPropiedad::Alquilada,
            'calle' => $calle, 'numero' => $numero, 'piso' => $piso, 'depto' => $depto,
            'localidad' => 'CABA', 'provincia' => 'Buenos Aires',
            'ambientes' => $ambientes, 'superficie_m2' => $m2,
        ]);

        $propiedad->owners()->attach(collect($duenos)->map(fn ($p) => ['porcentaje' => $p])->all());

        return $propiedad;
    }

    private function contrato(
        Property $propiedad, string $inquilino, string $telefono, Indice $indice,
        int $frecuencia, int $mesesAtras, int $monto, int $diaVencimiento,
    ): Contract {
        $inicio = $this->mesActual->subMonths($mesesAtras);

        return Contract::query()->create([
            'property_id' => $propiedad->id,
            'tenant_id' => Tenant::query()->create(['nombre' => $inquilino, 'telefono' => $telefono])->id,
            'fecha_inicio' => $inicio,
            'fecha_fin' => $inicio->addYears(3)->subDay(),
            'monto_base' => $monto,
            'monto_actual' => $monto,
            'dia_vencimiento' => $diaVencimiento,
            'deposito' => $monto,
            'indice' => $indice,
            'frecuencia_meses' => $frecuencia,
            'proximo_ajuste' => $inicio->addMonths($frecuencia),
            'redondeo' => 1000,
            'estado' => EstadoContrato::Activo,
            'gastos' => [
                ['categoria' => CategoriaGasto::Expensas->value, 'descripcion' => 'Expensas ordinarias', 'a_cargo_de' => ACargoDe::Inquilino->value],
                ['categoria' => CategoriaGasto::Luz->value, 'descripcion' => null, 'a_cargo_de' => ACargoDe::Inquilino->value],
                ['categoria' => CategoriaGasto::Abl->value, 'descripcion' => null, 'a_cargo_de' => ACargoDe::Propietarios->value],
            ],
        ]);
    }

    /**
     * Mes a mes desde el inicio: primero los ajustes que entran en vigencia
     * (calculados con los índices reales), después el cargo del mes. El ajuste
     * que vence este mes queda propuesto, para que se vea el flujo de revisión;
     * si el índice todavía no salió, el contrato queda esperándolo, como en la
     * realidad.
     */
    private function historia(Contract $contrato): void
    {
        $calculador = app(CalculadorDeAjuste::class);
        $aplicador = app(AplicadorDeAjuste::class);
        $cargos = app(GeneradorDeCargos::class);
        $esperando = false;

        for ($mes = $contrato->fecha_inicio->toImmutable()->startOfMonth(); $mes->lte($this->mesActual); $mes = $mes->addMonth()) {
            while (! $esperando && $contrato->proximo_ajuste !== null && $contrato->proximo_ajuste->lte($mes)) {
                $resultado = $calculador->calcular($contrato);

                if (! $resultado instanceof PropuestaDeAjuste) {
                    $esperando = true;
                    break;
                }

                $ajuste = $aplicador->proponer($resultado);

                if ($ajuste->vigencia_desde->gte($this->mesActual)) {
                    $esperando = true;
                    break;
                }

                $aplicador->aplicar($ajuste);
                $ajuste->update(['aplicado_at' => $ajuste->vigencia_desde->subDays(5)->setTime(11, 0)]);
                $contrato = $contrato->fresh() ?? $contrato;
            }

            $cargos->generarPara($contrato->fresh() ?? $contrato, $mes);
        }
    }

    /**
     * Los meses viejos, pagos. `ultimoImpago` deja el del mes pasado sin pagar
     * (vencido); `parcialEsteMes` true paga la mitad del de este mes, false lo
     * paga entero y null lo deja pendiente.
     */
    private function pagos(Contract $contrato, bool $ultimoImpago, ?bool $parcialEsteMes): void
    {
        $cargos = RentCharge::query()->where('contract_id', $contrato->id)->orderBy('periodo')->get();

        foreach ($cargos as $cargo) {
            $periodo = $cargo->periodo->toImmutable();
            $esteMes = $periodo->equalTo($this->mesActual);

            if ($esteMes && $parcialEsteMes === null) {
                continue;
            }

            if ($ultimoImpago && $periodo->equalTo($this->mesActual->subMonth())) {
                continue;
            }

            $monto = $esteMes && $parcialEsteMes ? bcdiv((string) $cargo->monto, '2', 2) : (string) $cargo->monto;
            $fecha = $esteMes
                ? ($this->hoy->day > 1 ? $this->hoy->subDay() : $this->hoy)
                : $cargo->vencimiento->toImmutable()->subDays(mt_rand(0, 4));

            $pago = Payment::query()->create([
                'rent_charge_id' => $cargo->id,
                'fecha' => $fecha,
                'monto' => $monto,
                'medio' => mt_rand(1, 5) === 1 ? MedioPago::MercadoPago : MedioPago::Transferencia,
                'referencia' => 'Op. '.mt_rand(10000000, 99999999),
            ]);

            // Los comprobantes de los últimos meses, como los que manda el inquilino.
            if ($periodo->gte($this->mesActual->subMonths(2))) {
                $path = $this->guardarPdf("pagos/{$pago->id}", [
                    'COMPROBANTE DE TRANSFERENCIA',
                    '',
                    'Fecha: '.$fecha->format('d/m/Y'),
                    'Importe: $ '.number_format((float) $monto, 2, ',', '.'),
                    'Concepto: Alquiler '.$periodo->translatedFormat('F Y'),
                    'Origen: '.$contrato->tenant->nombre,
                    'Destino: '.$contrato->property->alias,
                    'Operación: '.$pago->referencia,
                ]);
                $pago->update([
                    'comprobante_path' => $path,
                    'comprobante_nombre' => 'transferencia-'.$periodo->format('Y-m').'.pdf',
                    'comprobante_mime' => 'application/pdf',
                ]);
            }
        }
    }

    /**
     * Expensas y luz a cargo del inquilino, del último año de contrato. Lo de
     * meses anteriores está pago; lo de este mes, pendiente. Las facturas de
     * los últimos tres meses tienen su PDF.
     */
    private function gastosDelInquilino(Contract $contrato, ?string $expensas, int $montoExpensas, string $distribuidora): void
    {
        $inicio = $contrato->fecha_inicio->toImmutable()->startOfMonth();
        $haceUnAnio = $this->mesActual->subMonths(11);
        $desde = $inicio->gt($haceUnAnio) ? $inicio : $haceUnAnio;

        for ($mes = $desde, $k = 0; $mes->lte($this->mesActual); $mes = $mes->addMonth(), $k++) {
            $pasado = $mes->lt($this->mesActual);

            if ($expensas !== null) {
                $gasto = $this->gasto($contrato->property, $contrato, CategoriaGasto::Expensas, $expensas, $mes,
                    round($montoExpensas * 1.028 ** $k, -2), $mes->setDay(10), ACargoDe::Inquilino, $pasado);
                $this->facturaSiEsReciente($gasto, $mes, "Expensas {$mes->translatedFormat('F Y')} — {$contrato->property->alias}");
            }

            $luz = $this->gasto($contrato->property, $contrato, CategoriaGasto::Luz, $distribuidora, $mes,
                mt_rand(2200, 6500) * 10 + mt_rand(0, 99) / 100, $mes->setDay(18), ACargoDe::Inquilino, $pasado);
            $this->facturaSiEsReciente($luz, $mes, "{$distribuidora} — factura {$mes->translatedFormat('F Y')}");
        }
    }

    /** ABL de los últimos seis meses, a cargo de los dueños y repartido. */
    private function abl(Property $propiedad): void
    {
        for ($k = 5; $k >= 0; $k--) {
            $mes = $this->mesActual->subMonths($k);
            $gasto = $this->gasto($propiedad, null, CategoriaGasto::Abl, 'ABL', $mes,
                mt_rand(180, 320) * 100, $mes->setDay(22), ACargoDe::Propietarios, $k > 0);
            app(RepartidorEntreDuenos::class)->repartir($gasto);
        }
    }

    private function gastosExtraordinarios(Property $cabildo, Contract $contratoCabildo, Property $thames): void
    {
        $mes = $this->mesActual->subMonths(2);
        $calefon = $this->gasto($cabildo, $contratoCabildo, CategoriaGasto::Gas, 'Service del calefón', $mes,
            96000, $mes->setDay(20), ACargoDe::Mitades, true, TipoGasto::Extraordinario);
        app(RepartidorEntreDuenos::class)->repartir($calefon);
        $this->documentoDeGasto($calefon, TipoDocumentoGasto::Factura, 'Service del calefón — Gasista matriculado');

        $mes = $this->mesActual->subMonths(3);
        $pintura = $this->gasto($thames, null, CategoriaGasto::Reparacion, 'Pintura completa del departamento', $mes,
            450000, $mes->setDay(25), ACargoDe::Propietarios, true, TipoGasto::Extraordinario);
        app(RepartidorEntreDuenos::class)->repartir($pintura);
        $this->documentoDeGasto($pintura, TipoDocumentoGasto::Factura, 'Pintura — presupuesto aceptado y factura');
    }

    private function gasto(
        Property $propiedad, ?Contract $contrato, CategoriaGasto $categoria, string $descripcion,
        CarbonImmutable $periodo, float $monto, CarbonImmutable $vencimiento, ACargoDe $aCargoDe,
        bool $pagado, ?TipoGasto $tipo = null,
    ): Expense {
        return Expense::query()->create([
            'property_id' => $propiedad->id,
            'contract_id' => $contrato?->id,
            'tipo' => $tipo ?? $categoria->tipoPorDefecto(),
            'categoria' => $categoria,
            'descripcion' => $descripcion,
            'periodo' => $periodo,
            'monto' => $monto,
            'vencimiento' => $vencimiento,
            'a_cargo_de' => $aCargoDe,
            'pagado' => $pagado,
            'fecha_pago' => $pagado ? $vencimiento->subDays(mt_rand(1, 4)) : null,
        ]);
    }

    private function facturaSiEsReciente(Expense $gasto, CarbonImmutable $mes, string $titulo): void
    {
        if ($mes->lt($this->mesActual->subMonths(2))) {
            return;
        }

        $this->documentoDeGasto($gasto, TipoDocumentoGasto::Factura, $titulo);

        if ($gasto->pagado) {
            $this->documentoDeGasto($gasto, TipoDocumentoGasto::Comprobante, "Pago — {$titulo}");
        }
    }

    private function documentoDeGasto(Expense $gasto, TipoDocumentoGasto $tipo, string $titulo): void
    {
        $lineas = [
            mb_strtoupper($titulo),
            '',
            'Período: '.$gasto->periodo->translatedFormat('F Y'),
            'Importe: $ '.number_format((float) $gasto->monto, 2, ',', '.'),
            'Vencimiento: '.$gasto->vencimiento?->format('d/m/Y'),
        ];

        if ($tipo === TipoDocumentoGasto::Comprobante) {
            $lineas[] = 'Pagado el '.$gasto->fecha_pago?->format('d/m/Y');
        }

        $path = $this->guardarPdf("gastos/{$gasto->id}", $lineas);

        $gasto->documents()->create([
            'tipo' => $tipo,
            'nombre_original' => Str::slug($titulo).'.pdf',
            'path' => $path,
            'mime' => 'application/pdf',
            'tamano' => Storage::disk('local')->size($path),
            'subido_por' => $this->demo->id,
        ]);
    }

    /**
     * @param  list<Contract>  $contratos
     * @param  list<Property>  $propiedades
     */
    private function documentos(array $contratos, array $propiedades): void
    {
        foreach ($contratos as $contrato) {
            $path = $this->guardarPdf("contratos/{$contrato->id}", [
                'CONTRATO DE LOCACIÓN',
                '',
                'Inmueble: '.$contrato->property->alias,
                'Locatario: '.$contrato->tenant->nombre,
                'Plazo: del '.$contrato->fecha_inicio->format('d/m/Y').' al '.$contrato->fecha_fin->format('d/m/Y'),
                'Alquiler inicial: $ '.number_format((float) $contrato->monto_base, 0, ',', '.'),
                'Actualización: '.$contrato->indice->label().' cada '.$contrato->frecuencia_meses.' meses',
                '',
                'Documento de ejemplo de la demo de Alquiler Fácil.',
            ]);

            $contrato->documents()->create([
                'tipo' => TipoDocumentoContrato::ContratoFirmado,
                'nombre_original' => 'contrato-'.Str::slug($contrato->property->alias).'.pdf',
                'path' => $path,
                'mime' => 'application/pdf',
                'tamano' => Storage::disk('local')->size($path),
                'subido_por' => $this->demo->id,
            ]);
        }

        foreach ($propiedades as $propiedad) {
            $path = $this->guardarPdf("propiedades/{$propiedad->id}", [
                'ESCRITURA TRASLATIVA DE DOMINIO',
                '',
                'Inmueble: '.$propiedad->alias,
                'Superficie: '.$propiedad->superficie_m2.' m²',
                '',
                'Documento de ejemplo de la demo de Alquiler Fácil.',
            ]);

            $propiedad->documents()->create([
                'tipo' => TipoDocumentoPropiedad::Escritura,
                'nombre_original' => 'escritura-'.Str::slug($propiedad->alias).'.pdf',
                'path' => $path,
                'mime' => 'application/pdf',
                'tamano' => Storage::disk('local')->size($path),
                'subido_por' => $this->demo->id,
            ]);
        }
    }

    private function administracion(Property $propiedad): void
    {
        $tema = AdminThread::query()->create([
            'property_id' => $propiedad->id,
            'titulo' => 'Filtración en el palier del 4° piso',
            'categoria' => CategoriaTemaAdmin::Reclamo,
            'estado' => EstadoTemaAdmin::Abierto,
        ]);

        foreach ([
            18 => 'Se informa a la administración por mail. Mancha de humedad en el techo del palier, cerca del ascensor.',
            9 => 'La administración manda un plomero a revisar. Dice que viene de una cañería del 5°.',
            2 => 'Pendiente el arreglo definitivo y el repintado. Reclamado de nuevo.',
        ] as $diasAtras => $detalle) {
            $tema->entries()->create([
                'fecha' => $this->hoy->subDays($diasAtras),
                'detalle' => $detalle,
                'registrado_por' => $this->demo->id,
            ]);
        }
    }

    /**
     * Guarda un PDF de una página en el disco privado, con el mismo esquema de
     * rutas que los controladores (`<carpeta>/<id>/<ulid>.pdf`).
     *
     * @param  list<string>  $lineas
     */
    private function guardarPdf(string $carpeta, array $lineas): string
    {
        $path = $carpeta.'/'.Str::ulid().'.pdf';
        Storage::disk('local')->put($path, $this->pdf($lineas));

        return $path;
    }

    /**
     * Un PDF 1.4 mínimo escrito a mano (una página A4, Helvetica): alcanza para
     * que el visor de la app lo muestre y no hace falta ninguna librería.
     *
     * @param  list<string>  $lineas
     */
    private function pdf(array $lineas): string
    {
        $texto = "BT\n/F1 13 Tf\n56 780 Td\n16 TL\n";
        foreach ($lineas as $linea) {
            $winAnsi = (string) mb_convert_encoding($linea, 'Windows-1252', 'UTF-8');
            $texto .= '('.addcslashes($winAnsi, '()\\').") Tj T*\n";
        }
        $texto .= 'ET';

        $objetos = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
            '<< /Length '.strlen($texto)." >>\nstream\n{$texto}\nendstream",
        ];

        $pdf = "%PDF-1.4\n";
        $posiciones = [];
        foreach ($objetos as $i => $objeto) {
            $posiciones[] = strlen($pdf);
            $pdf .= ($i + 1)." 0 obj\n{$objeto}\nendobj\n";
        }

        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objetos) + 1)."\n0000000000 65535 f \n";
        foreach ($posiciones as $posicion) {
            $pdf .= sprintf("%010d 00000 n \n", $posicion);
        }

        return $pdf.'trailer << /Size '.(count($objetos) + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF\n";
    }
}
