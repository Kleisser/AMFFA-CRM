<?php

namespace Tests\Feature;

use App\Console\Commands\FixEncodingCp437;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

class FixEncodingCp437Test extends TestCase
{
    use RefreshDatabase;

    private function fixValue(mixed $value): mixed
    {
        $cmd = new FixEncodingCp437();
        $method = new ReflectionMethod($cmd, 'fixValue');
        $method->setAccessible(true);

        return $method->invoke($cmd, $value);
    }

    public function test_revierte_n_tilde_y_acentos(): void
    {
        $this->assertSame('Peña Peña', $this->fixValue('Pe├▒a Pe├▒a'));
        $this->assertSame('Cerdá', $this->fixValue('Cerd├í'));
        $this->assertSame('Cerdé', $this->fixValue('Cerd├®'));
        $this->assertSame('Andrés', $this->fixValue('Andr├®s'));
        $this->assertSame('Belén', $this->fixValue('Bel├®n'));
        $this->assertSame('José', $this->fixValue('Jos├®'));
        $this->assertSame('María', $this->fixValue('Mar├¡a'));
        $this->assertSame('Nº', $this->fixValue('N┬║'));
        $this->assertSame("¿Qué", $this->fixValue('┬┐Qu├®'));
        $this->assertSame('años', $this->fixValue('a├▒os'));
    }

    public function test_no_toca_texto_limpio(): void
    {
        $limpio = 'Texto normal sin mojibake';
        $this->assertSame($limpio, $this->fixValue($limpio));
        $this->assertSame(null, $this->fixValue(null));
        $this->assertSame('', $this->fixValue(''));
    }

    public function test_corrige_json_recursivo(): void
    {
        $json = json_encode(['nombre' => 'Bel├®n', 'detalle' => ['obs' => 'a├▒os']], JSON_UNESCAPED_UNICODE);
        $fixed = $this->fixValue($json);
        $decoded = json_decode($fixed, true);
        $this->assertSame('Belén', $decoded['nombre']);
        $this->assertSame('años', $decoded['detalle']['obs']);
    }
}
