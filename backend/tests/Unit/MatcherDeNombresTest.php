<?php

namespace Tests\Unit;

use App\Support\MatcherDeNombres;
use PHPUnit\Framework\TestCase;

class MatcherDeNombresTest extends TestCase
{
    public function test_match_exacto_normalizado(): void
    {
        $matcher = new MatcherDeNombres([1 => 'María de los Ángeles Pérez']);
        $this->assertSame(1, $matcher->match('MARIA DE LOS ANGELES PEREZ'));
    }

    public function test_match_subconjunto_de_palabras(): void
    {
        $matcher = new MatcherDeNombres([1 => 'Juan Ignacio Mendy']);
        $this->assertSame(1, $matcher->match('JUAN IGNACIO MENDY PEREZ'));
    }

    public function test_match_inverso_subconjunto(): void
    {
        $matcher = new MatcherDeNombres([1 => 'Carlos Alberto Suarez']);
        $this->assertSame(1, $matcher->match('CARLOS SUAREZ'));
    }

    public function test_match_fuzzy_con_typo_leve(): void
    {
        $matcher = new MatcherDeNombres([1 => 'Sebastian Nicolas Ruiz']);
        $this->assertSame(1, $matcher->match('SEBASTIAN NICOLAS RUZ'));
    }

    public function test_no_matchea_nombres_distintos(): void
    {
        $matcher = new MatcherDeNombres([1 => 'Juan Perez', 2 => 'Ana Diaz']);
        $this->assertNull($matcher->match('Fulano Desconocido'));
    }

    public function test_no_asigna_si_es_ambiguo(): void
    {
        $matcher = new MatcherDeNombres([1 => 'Juan Peres', 2 => 'Juan Perel']);
        $this->assertNull($matcher->match('JUAN PEREZ'));
    }
}
