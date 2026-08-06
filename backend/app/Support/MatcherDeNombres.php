<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Matchea nombres de asesores (planilla/lista externa) contra usuarios del CRM.
 * Estrategia: exacto normalizado > subconjunto de palabras (ambas direcciones)
 * > fuzzy con levenshtein <= 2. Si hay más de un candidato, no asigna.
 */
class MatcherDeNombres
{
    /** @var array<int, array{name: string, palabras: array}> */
    private array $candidatos = [];

    /**
     * @param array<int, string> $nombresPorId id de usuario => nombre
     */
    public function __construct(array $nombresPorId)
    {
        foreach ($nombresPorId as $id => $nombre) {
            $this->candidatos[$id] = [
                'name' => (string) $nombre,
                'palabras' => $this->palabras((string) $nombre),
            ];
        }
    }

    public function match(string $nombre): ?int
    {
        $palabrasExterno = $this->palabras($nombre);
        $normExterno = implode(' ', $palabrasExterno);

        $exactos = [];
        $subsets = [];
        $reversos = [];
        $fuzzy = [];

        foreach ($this->candidatos as $id => $candidato) {
            $palabrasCandidato = $candidato['palabras'];
            $normCandidato = implode(' ', $palabrasCandidato);

            if ($normCandidato === $normExterno) {
                $exactos[] = $id;
                continue;
            }

            if (count($palabrasCandidato) > 1 && $this->esSubconjunto($palabrasCandidato, $palabrasExterno)) {
                $subsets[] = $id;
                continue;
            }

            if (count($palabrasExterno) > 1 && $this->esSubconjunto($palabrasExterno, $palabrasCandidato)) {
                $reversos[] = $id;
                continue;
            }

            $distancia = levenshtein($normCandidato, $normExterno);
            if ($distancia <= 2) {
                $fuzzy[] = $id;
            }
        }

        foreach (['exactos', 'subsets', 'reversos'] as $tipo) {
            if (count($$tipo) === 1) {
                return $$tipo[0];
            }
            if (count($$tipo) > 1) {
                return null; // Ambiguo: no asignar.
            }
        }

        return count($fuzzy) === 1 ? $fuzzy[0] : null;
    }

    public function palabras(string $nombre): array
    {
        $normalizado = Str::ascii(Str::upper($nombre));
        $palabras = preg_split('/\s+/', preg_replace('/[^A-Z0-9\s]/', ' ', $normalizado) ?? '') ?? [];
        $palabras = array_values(array_filter($palabras, fn ($p) => $p !== ''));

        sort($palabras);

        return $palabras;
    }

    private function esSubconjunto(array $menor, array $mayor): bool
    {
        $counts = array_count_values($mayor);
        foreach ($menor as $palabra) {
            if (!isset($counts[$palabra]) || $counts[$palabra] <= 0) {
                return false;
            }
            $counts[$palabra]--;
        }

        return true;
    }
}
