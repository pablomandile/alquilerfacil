<?php

namespace Tests\Feature;

use App\Enums\EstadoAjuste;
use App\Enums\Indice;
use App\Models\Contract;
use App\Models\IndexValue;
use App\Services\Ajustes\AplicadorDeAjuste;
use App\Services\Ajustes\CalculadorDeAjuste;
use App\Services\Ajustes\IndiceNoDisponible;
use App\Services\Ajustes\PropuestaDeAjuste;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Tests\TestCase;

class CalculadorDeAjusteTest extends TestCase
{
    use RefreshDatabase;

    private CalculadorDeAjuste $calculador;

    protected function setUp(): void
    {
        parent::setUp();

        $this->calculador = new CalculadorDeAjuste;
    }

    /** @param  array<string, float|string>  $valores  fecha => valor */
    private function cargarIpc(array $valores): void
    {
        foreach ($valores as $fecha => $valor) {
            IndexValue::factory()->ipc($fecha, $valor)->create();
        }
    }

    private function contratoIpc(string $vigencia, float|int $monto = 400000, int $frecuencia = 3, int $redondeo = 0): Contract
    {
        return Contract::factory()->create([
            'indice' => Indice::Ipc,
            'frecuencia_meses' => $frecuencia,
            'proximo_ajuste' => $vigencia,
            'monto_base' => $monto,
            'monto_actual' => $monto,
            'redondeo' => $redondeo,
        ]);
    }

    public function test_el_coeficiente_sale_de_dividir_los_dos_numeros_indice(): void
    {
        $this->cargarIpc(['2026-04-01' => 100, '2026-07-01' => 106]);

        $propuesta = $this->calculador->calcular($this->contratoIpc('2026-08-01'));

        $this->assertInstanceOf(PropuestaDeAjuste::class, $propuesta);
        $this->assertSame('1.06000000', $propuesta->coeficiente);
        $this->assertSame('424000.00', $propuesta->montoNuevo);
        $this->assertSame('6.0000', $propuesta->variacionPorcentual);
    }

    /**
     * Un ajuste con vigencia el 1/8 y frecuencia 3 tiene que recoger la inflación
     * de mayo, junio y julio: IPC[julio] / IPC[abril].
     */
    public function test_la_ventana_toma_los_meses_transcurridos_antes_de_la_vigencia(): void
    {
        $this->cargarIpc(['2026-04-01' => 100, '2026-07-01' => 106]);

        $propuesta = $this->calculador->calcular($this->contratoIpc('2026-08-01'));

        $this->assertInstanceOf(PropuestaDeAjuste::class, $propuesta);
        $this->assertSame('2026-04-01', $propuesta->periodoIndiceDesde->toDateString());
        $this->assertSame('2026-07-01', $propuesta->periodoIndiceHasta->toDateString());
    }

    /**
     * El caso que más se va a dar en la práctica: el INDEC publica el IPC de un
     * mes a mediados del siguiente, así que el índice que cierra la ventana
     * todavía no existe. Tiene que decirlo, no calcular con lo que haya.
     */
    public function test_avisa_cuando_el_indice_todavia_no_esta_publicado(): void
    {
        $this->cargarIpc(['2026-04-01' => 100]); // Falta julio.

        $resultado = $this->calculador->calcular($this->contratoIpc('2026-08-01'));

        $this->assertInstanceOf(IndiceNoDisponible::class, $resultado);
        $this->assertSame('2026-07-01', $resultado->periodoFaltante->toDateString());
        $this->assertSame('2026-08-15', $resultado->publicacionEstimada->toDateString());
        $this->assertStringContainsString('Falta el IPC de julio de 2026', $resultado->motivo());
    }

    public function test_avisa_tambien_si_falta_el_indice_que_abre_la_ventana(): void
    {
        $this->cargarIpc(['2026-07-01' => 106]); // Falta abril.

        $resultado = $this->calculador->calcular($this->contratoIpc('2026-08-01'));

        $this->assertInstanceOf(IndiceNoDisponible::class, $resultado);
        $this->assertSame('2026-04-01', $resultado->periodoFaltante->toDateString());
    }

    public function test_redondea_al_multiplo_configurado_en_el_contrato(): void
    {
        $this->cargarIpc(['2026-04-01' => 100, '2026-07-01' => 106.37]);

        $propuesta = $this->calculador->calcular(
            $this->contratoIpc('2026-08-01', monto: 400000, redondeo: 1000)
        );

        // 400.000 x 1,0637 = 425.480 -> al millar más cercano, 425.000.
        $this->assertSame('425000.00', $propuesta->montoNuevo);
    }

    public function test_sin_redondeo_configurado_deja_los_centavos(): void
    {
        $this->cargarIpc(['2026-04-01' => 100, '2026-07-01' => 106.37]);

        $propuesta = $this->calculador->calcular($this->contratoIpc('2026-08-01', 400000));

        $this->assertSame('425480.00', $propuesta->montoNuevo);
    }

    /**
     * El ICL no se publica sábados, domingos ni feriados, así que para esas fechas
     * hay que tomar el último valor anterior disponible.
     */
    public function test_el_icl_usa_el_ultimo_valor_anterior_si_la_fecha_no_tiene_dato(): void
    {
        IndexValue::factory()->icl('2026-05-29', 30)->create(); // Viernes
        IndexValue::factory()->icl('2026-08-28', 33)->create(); // Viernes

        $contrato = Contract::factory()->porIcl()->create([
            'frecuencia_meses' => 3,
            'proximo_ajuste' => '2026-08-30', // Domingo
            'monto_base' => 500000,
            'monto_actual' => 500000,
        ]);

        $propuesta = $this->calculador->calcular($contrato);

        $this->assertInstanceOf(PropuestaDeAjuste::class, $propuesta);
        $this->assertSame('2026-05-29', $propuesta->periodoIndiceDesde->toDateString());
        $this->assertSame('2026-08-28', $propuesta->periodoIndiceHasta->toDateString());
        $this->assertSame('550000.00', $propuesta->montoNuevo);
    }

    /**
     * Con los números que publica el INDEC, un ajuste trimestral con vigencia el
     * 1/8/2026 tiene que dar la inflación compuesta de mayo, junio y julio
     * (2,15 % + 1,89 % + 2,11 % encadenados = 6,28 %).
     */
    public function test_con_los_valores_reales_del_indec_da_la_inflacion_del_trimestre(): void
    {
        $this->cargarIpc([
            '2026-04-01' => 11363.0904,
            '2026-05-01' => 11607.3937,
            '2026-06-01' => 11826.4103,
            '2026-07-01' => 12076.3937,
        ]);

        $propuesta = $this->calculador->calcular($this->contratoIpc('2026-08-01', 450000));

        $this->assertEqualsWithDelta(6.28, (float) $propuesta->variacionPorcentual, 0.01);
        $this->assertSame('478248.16', $propuesta->montoNuevo);
    }

    public function test_reconoce_una_baja_si_hubo_deflacion(): void
    {
        $this->cargarIpc(['2026-04-01' => 100, '2026-07-01' => 98]);

        $propuesta = $this->calculador->calcular($this->contratoIpc('2026-08-01', 400000));

        $this->assertTrue($propuesta->esBaja());
        $this->assertSame('392000.00', $propuesta->montoNuevo);
    }

    public function test_aplicar_actualiza_el_monto_y_corre_la_fecha_del_proximo_ajuste(): void
    {
        $this->cargarIpc(['2026-04-01' => 100, '2026-07-01' => 106]);
        $contrato = $this->contratoIpc('2026-08-01', 400000);

        $aplicador = new AplicadorDeAjuste;
        $ajuste = $aplicador->proponer($this->calculador->calcular($contrato));

        $this->assertSame(EstadoAjuste::Propuesto, $ajuste->estado);
        $this->assertSame('400000.00', (string) $contrato->fresh()->monto_actual);

        $aplicador->aplicar($ajuste);
        $contrato->refresh();

        $this->assertSame('424000.00', (string) $contrato->monto_actual);
        $this->assertSame('2026-11-01', $contrato->proximo_ajuste->toDateString());
        $this->assertSame(EstadoAjuste::Aplicado, $ajuste->fresh()->estado);
    }

    public function test_aplicar_con_monto_editado_respeta_lo_pactado(): void
    {
        $this->cargarIpc(['2026-04-01' => 100, '2026-07-01' => 106]);
        $contrato = $this->contratoIpc('2026-08-01', 400000);

        $aplicador = new AplicadorDeAjuste;
        $ajuste = $aplicador->proponer($this->calculador->calcular($contrato));

        $aplicador->aplicar($ajuste, '420000');

        $this->assertSame('420000.00', (string) $contrato->fresh()->monto_actual);
        // Los valores del índice quedan intactos, para poder ver después cuánto
        // daba la cuenta contra lo que se terminó pactando.
        $this->assertSame('106.00000000', (string) $ajuste->fresh()->valor_indice_hasta);
    }

    public function test_rechazar_corre_la_fecha_sin_tocar_el_alquiler(): void
    {
        $this->cargarIpc(['2026-04-01' => 100, '2026-07-01' => 106]);
        $contrato = $this->contratoIpc('2026-08-01', 400000);

        $aplicador = new AplicadorDeAjuste;
        $ajuste = $aplicador->proponer($this->calculador->calcular($contrato));

        $aplicador->rechazar($ajuste, 'Acordado con el inquilino no aumentar este trimestre.');
        $contrato->refresh();

        $this->assertSame('400000.00', (string) $contrato->monto_actual);
        $this->assertSame('2026-11-01', $contrato->proximo_ajuste->toDateString());
        $this->assertSame(EstadoAjuste::Rechazado, $ajuste->fresh()->estado);
    }

    public function test_proponer_dos_veces_no_duplica_la_propuesta(): void
    {
        $this->cargarIpc(['2026-04-01' => 100, '2026-07-01' => 106]);
        $contrato = $this->contratoIpc('2026-08-01');

        $aplicador = new AplicadorDeAjuste;
        $aplicador->proponer($this->calculador->calcular($contrato));
        $aplicador->proponer($this->calculador->calcular($contrato->fresh()));

        $this->assertSame(1, $contrato->adjustments()->count());
    }

    public function test_no_pisa_un_ajuste_ya_aplicado(): void
    {
        $this->cargarIpc(['2026-04-01' => 100, '2026-07-01' => 106]);
        $contrato = $this->contratoIpc('2026-08-01', 400000);

        $aplicador = new AplicadorDeAjuste;
        $ajuste = $aplicador->proponer($this->calculador->calcular($contrato));
        $aplicador->aplicar($ajuste, '420000');

        // Volver a proponer para la misma vigencia tiene que devolver el aplicado
        // tal cual está, sin recalcularlo ni pisar el monto que se pactó.
        $devuelto = $aplicador->proponer(
            $this->calculador->calcular($contrato->fresh(), Date::parse('2026-08-01'))
        );

        $this->assertSame(EstadoAjuste::Aplicado, $devuelto->estado);
        $this->assertSame('420000.00', (string) $devuelto->monto_nuevo);
    }
}
