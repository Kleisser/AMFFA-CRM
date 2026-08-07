<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Corrige mojibake CP437 en datos importados desde el CSV de Ventia
 * (guardado por Excel como "CSV (MS-DOS)": el texto UTF-8 quedó
 * decodificado como CP437 y re-encodificado como UTF-8).
 *
 * Patrón: "ñ" (C3 B1) aparece como "├▒" (E2 94 9C E2 96 92);
 * "á" (C3 A1) como "├í"; "Ñ" (C3 91) como "├æ"; etc.
 *
 * La reparación es reversible: convertir el mojibake a CP437 devuelve
 * los bytes UTF-8 originales. Idempotente: solo toca filas con el
 * patrón y valida que el resultado sea UTF-8 válido.
 */
class FixEncodingCp437 extends Command
{
    protected $signature = 'fix:encoding-cp437 {--dry-run : Mostrar los cambios sin aplicarlos}';

    protected $description = 'Corrige mojibake CP437 (ñ/tildes) en datos importados del CSV de Ventia';

    private const TABLAS = [
        'users' => ['name'],
        'gecros_vendedores' => ['nombre'],
        'contacts' => ['name', 'address', 'notes', 'custom_fields', 'source'],
        'localities' => ['name', 'partido'],
        'pipeline_stages' => ['name'],
        'ventas' => ['asesor', 'afiliado'],
    ];

    private const CLAVES = [
        'gecros_vendedores' => 'venafi_id',
    ];

    private const PREFIJO_MOJIBake = "\u{251C}"; // "├" (E2 94 9C): primer byte de todo UTF-8 de 2 bytes
    private const PREFIJO_MOJIBake2 = "\u{252C}"; // "┬" (E2 94 AC): primer byte C2 de UTF-8 de 2 bytes

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $total = 0;

        foreach (self::TABLAS as $tabla => $columnas) {
            foreach ($columnas as $col) {
                $total += $this->repararColumna($tabla, $col, $dryRun);
            }
        }

        $this->info(($dryRun ? '[DRY RUN] ' : '') . "Filas afectadas: {$total}");

        return self::SUCCESS;
    }

    private function repararColumna(string $tabla, string $col, bool $dryRun): int
    {
        $pk = self::CLAVES[$tabla] ?? 'id';

        $rows = DB::table($tabla)
            ->select($pk, $col)
            ->whereRaw("HEX(`$col`) LIKE ? OR HEX(`$col`) LIKE ?", ['%E2949C%', '%E294AC%'])
            ->orderBy($pk)
            ->get();

        $cont = 0;

        foreach ($rows as $row) {
            $value = $row->{$col};
            $fixed = $this->fixValue($value);

            if ($fixed === null || $fixed === $value) {
                continue;
            }

            $cont++;

            if ($dryRun) {
                if ($cont <= 5) {
                    $this->line("  {$tabla}.{$col} #{$row->{$pk}}:");
                    $this->line("    antes : {$this->recortar($value)}");
                    $this->line("    despues: {$this->recortar($fixed)}");
                }
                continue;
            }

            DB::table($tabla)->where($pk, $row->{$pk})->update([$col => $fixed]);
        }

        if ($cont > 0) {
            $this->info("{$tabla}.{$col}: {$cont}" . ($dryRun ? ' (a corregir)' : ' corregidas'));
        }

        return $cont;
    }

    private function fixValue(mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return $value;
        }

        if (!str_contains((string) $value, self::PREFIJO_MOJIBake)
            && !str_contains((string) $value, self::PREFIJO_MOJIBake2)) {
            return $value;
        }

        $decoded = json_decode((string) $value, true);
        if (is_array($decoded) || is_object($decoded)) {
            if (json_last_error() === JSON_ERROR_NONE) {
                $this->fixRecursivo($decoded);
                $encoded = json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

                return $encoded === false ? $value : $encoded;
            }
        }

        return $this->fixString((string) $value);
    }

    private function fixRecursivo(array|object &$node): void
    {
        foreach ($node as $key => &$child) {
            if (is_string($child)) {
                if (str_contains($child, self::PREFIJO_MOJIBake) || str_contains($child, self::PREFIJO_MOJIBake2)) {
                    $child = $this->fixString($child);
                }
            } elseif (is_array($child) || is_object($child)) {
                $this->fixRecursivo($child);
            }
        }
        unset($child);
    }

    private function fixString(string $s): string
    {
        // Cada "├" (U+251C) es el primer byte UTF-8 (0xC3/C2) decodificado como
        // CP437; el carácter siguiente es el byte de continuación. Revertir
        // ambos devuelve los bytes UTF-8 originales.
        $chars = preg_split('//u', $s, -1, PREG_SPLIT_NO_EMPTY);
        if ($chars === false) {
            return $s;
        }

        $fixed = '';
        $n = count($chars);

        for ($i = 0; $i < $n; $i++) {
            $char = $chars[$i];
            $siguiente = $chars[$i + 1] ?? null;

            if ($siguiente !== null && isset(self::CP437_BYTES[$siguiente])) {
                if ($char === "\u{251C}") { // ├ => primer byte 0xC3
                    $fixed .= "\xC3" . chr(self::CP437_BYTES[$siguiente]);
                    $i++;
                    continue;
                }
                if ($char === "\u{252C}") { // ┬ => primer byte 0xC2
                    $fixed .= "\xC2" . chr(self::CP437_BYTES[$siguiente]);
                    $i++;
                    continue;
                }
            }

            $fixed .= $char;
        }

        // Guardia: solo aplicar si el resultado es UTF-8 válido y de verdad cambió algo.
        if ($fixed === $s || !mb_check_encoding($fixed, 'UTF-8')) {
            return $s;
        }

        return $fixed;
    }

    /**
     * Carácter CP437 (resultado de decodificar el byte de continuación 0x80-0xBF)
     * => byte original. Solo bytes de continuación de UTF-8 de 2 bytes.
     */
    private const CP437_BYTES = [
        "\u{00C7}" => 0x80, // Ç
        "\u{00FC}" => 0x81, // ü
        "\u{00E9}" => 0x82, // é
        "\u{00E2}" => 0x83, // â
        "\u{00E4}" => 0x84, // ä
        "\u{00E0}" => 0x85, // à
        "\u{00E5}" => 0x86, // å
        "\u{00E7}" => 0x87, // ç
        "\u{00EA}" => 0x88, // ê
        "\u{00EB}" => 0x89, // ë
        "\u{00E8}" => 0x8A, // è
        "\u{00EF}" => 0x8B, // ï
        "\u{00EE}" => 0x8C, // î
        "\u{00EC}" => 0x8D, // ì
        "\u{00C4}" => 0x8E, // Ä
        "\u{00C5}" => 0x8F, // Å
        "\u{00C9}" => 0x90, // É
        "\u{00E6}" => 0x91, // æ
        "\u{00C6}" => 0x92, // Æ
        "\u{00F4}" => 0x93, // ô
        "\u{00F6}" => 0x94, // ö
        "\u{00F2}" => 0x95, // ò
        "\u{00FB}" => 0x96, // û
        "\u{00F9}" => 0x97, // ù
        "\u{00FF}" => 0x98, // ÿ
        "\u{00D6}" => 0x99, // Ö
        "\u{00DC}" => 0x9A, // Ü
        "\u{00A2}" => 0x9B, // ¢
        "\u{00A3}" => 0x9C, // £
        "\u{00A5}" => 0x9D, // ¥
        "\u{20A7}" => 0x9E, // ₧
        "\u{0192}" => 0x9F, // ƒ
        "\u{00E1}" => 0xA0, // á
        "\u{00ED}" => 0xA1, // í
        "\u{00F3}" => 0xA2, // ó
        "\u{00FA}" => 0xA3, // ú
        "\u{00F1}" => 0xA4, // ñ
        "\u{00D1}" => 0xA5, // Ñ
        // Variante observada en los CSV de Ventia: el byte 0xA9 se decodificó
        // como U+00AE (®) en lugar del ⌐ de CP437. Revertir "├®" => "é" y
        // "┬®" => "©" (originales C3 A9 / C2 A9).
        "\u{00AE}" => 0xA9, // ® (byte 0xA9 decodificado con tabla distinta)
        "\u{00AA}" => 0xA6, // ª
        "\u{00BA}" => 0xA7, // º
        "\u{00BF}" => 0xA8, // ¿
        "\u{2310}" => 0xA9, // ⌐
        "\u{00AC}" => 0xAA, // ¬
        "\u{00BD}" => 0xAB, // ½
        "\u{00BC}" => 0xAC, // ¼
        "\u{00A1}" => 0xAD, // ¡
        "\u{00AB}" => 0xAE, // «
        "\u{00BB}" => 0xAF, // »
        "\u{2591}" => 0xB0, // ░
        "\u{2592}" => 0xB1, // ▒
        "\u{2593}" => 0xB2, // ▓
        "\u{2502}" => 0xB3, // │
        "\u{2524}" => 0xB4, // ┤
        "\u{2561}" => 0xB5, // ╡
        "\u{2562}" => 0xB6, // ╢
        "\u{2556}" => 0xB7, // ╖
        "\u{2555}" => 0xB8, // ╕
        "\u{2563}" => 0xB9, // ╣
        "\u{2551}" => 0xBA, // ║
        "\u{2557}" => 0xBB, // ╗
        "\u{255D}" => 0xBC, // ╝
        "\u{255C}" => 0xBD, // ╜
        "\u{255B}" => 0xBE, // ╛
        "\u{2510}" => 0xBF, // ┐
    ];

    private function recortar(string $s): string
    {
        return mb_strlen($s) > 60 ? mb_substr($s, 0, 60) . '…' : $s;
    }
}
