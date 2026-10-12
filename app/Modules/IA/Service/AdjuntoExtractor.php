<?php

namespace App\Modules\IA\Service;

use InvalidArgumentException;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Reader\XLSX\Reader;

/**
 * Traduce los adjuntos que sube el usuario a algo que MiniMax pueda leer.
 *
 * Dos destinos posibles:
 *   - imagen -> data URL. MiniMax-M3 la ve de verdad.
 *   - texto  -> texto plano inyectado en el prompt.
 *
 * Lo que NO entra: PDF y DOCX. MiniMax solo acepta imagen (jpeg/png/gif/webp),
 * asi que esos formatos se rechazan con un mensaje claro en vez de fallar.
 */
class AdjuntoExtractor
{
    /** Filas de Excel que se leen antes de truncar. */
    private const MAX_FILAS_XLSX = 200;

    /** Caracteres de texto plano que se envian antes de truncar. */
    private const MAX_CARACTERES = 60000;

    private const MIME_XLSX = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

    /**
     * @param  array<int, array{nombre?: string, mime?: string, base64?: string}>  $adjuntos
     * @return array<int, array{tipo: string, contenido: string}>
     *
     * @throws InvalidArgumentException si algo no es compatible o excede el peso
     */
    public static function procesar(array $adjuntos): array
    {
        $salida = [];

        foreach ($adjuntos as $adjunto) {
            $nombre = (string) ($adjunto['nombre'] ?? '');
            $mime = strtolower(trim((string) ($adjunto['mime'] ?? '')));
            $base64 = self::limpiarBase64((string) ($adjunto['base64'] ?? ''));

            if ($base64 === '') {
                throw new InvalidArgumentException("El archivo '{$nombre}' llego vacio.");
            }

            $binario = base64_decode($base64, true);

            if ($binario === false) {
                throw new InvalidArgumentException("El archivo '{$nombre}' no es un base64 valido.");
            }

            $maximo = (int) config('ia.max_adjuntos_mb') * 1024 * 1024;

            if (strlen($binario) > $maximo) {
                throw new InvalidArgumentException(sprintf(
                    "'%s' pesa %d KB y el limite es %d KB.",
                    $nombre === '' ? 'el archivo' : $nombre,
                    intdiv(strlen($binario), 1024),
                    intdiv($maximo, 1024),
                ));
            }

            $salida[] = self::clasificar($nombre, $mime, $binario);
        }

        return $salida;
    }

    /**
     * @return array{tipo: string, contenido: string}
     */
    private static function clasificar(string $nombre, string $mime, string $binario): array
    {
        $tipoMime = explode('/', $mime)[0] ?? '';
        $esImagen = in_array($mime, (array) config('ia.tipos_imagen'), true)
            || $tipoMime === 'image';

        // Las imagenes van directas. El nombre puede venir vacio (pegar un
        // screenshot desde el portapapeles), asi que no se exige.
        if ($esImagen) {
            return [
                'tipo' => 'imagen',
                'contenido' => "data:{$mime};base64,".base64_encode($binario),
            ];
        }

        if ($mime === self::MIME_XLSX || str_ends_with(strtolower($nombre), '.xlsx')) {
            return ['tipo' => 'texto', 'contenido' => self::leerXlsx($nombre, $binario)];
        }

        $esTexto = $tipoMime === 'text'
            || in_array($mime, ['application/json', 'application/xml', 'text/csv'], true)
            || str_ends_with(strtolower($nombre), '.txt')
            || str_ends_with(strtolower($nombre), '.csv')
            || str_ends_with(strtolower($nombre), '.json')
            || str_ends_with(strtolower($nombre), '.md');

        if ($esTexto) {
            $texto = mb_substr($binario, 0, self::MAX_CARACTERES);

            if (strlen($binario) > self::MAX_CARACTERES) {
                $texto .= "\n[contenido truncado por limite de caracteres]";
            }

            return [
                'tipo' => 'texto',
                'contenido' => "Contenido de '".($nombre === '' ? 'archivo' : $nombre)."':\n".$texto,
            ];
        }

        throw new InvalidArgumentException(sprintf(
            "El formato %s de '%s' todavia no es compatible. Sube imagenes (PNG/JPG/GIF/WEBP), Excel (.xlsx) o archivos de texto (csv, txt, json, md).",
            $mime === '' ? 'desconocido' : $mime,
            $nombre === '' ? 'el archivo' : $nombre,
        ));
    }

    /**
     * Aplana las hojas de un Excel a texto tabulado para mandarlo al prompt.
     */
    private static function leerXlsx(string $nombre, string $binario): string
    {
        $temporal = tempnam(sys_get_temp_dir(), 'ia_xlsx_');

        if ($temporal === false) {
            throw new InvalidArgumentException('No se pudo crear el archivo temporal para leer el Excel.');
        }

        try {
            file_put_contents($temporal, $binario);

            $reader = new Reader;
            $reader->open($temporal);

            $lineas = [];
            $total = 0;
            $truncado = false;

            foreach ($reader->getSheetIterator() as $hoja) {
                foreach ($hoja->getRowIterator() as $fila) {
                    if ($total >= self::MAX_FILAS_XLSX) {
                        $truncado = true;
                        break 2;
                    }

                    $lineas[] = implode("\t", array_map(
                        static fn (Cell $celda) => (string) $celda->getValue(),
                        $fila->getCells(),
                    ));
                    $total++;
                }
            }

            $reader->close();

            $contenido = "Contenido de '".($nombre === '' ? 'archivo' : $nombre)."' (".$total." filas):\n"
                .implode("\n", $lineas);

            if ($truncado) {
                $contenido .= "\n[se leyieron solo las primeras ".self::MAX_FILAS_XLSX.' filas]';
            }

            return $contenido;
        } catch (\Throwable $e) {
            throw new InvalidArgumentException(
                "No se pudo leer el Excel '".($nombre === '' ? 'archivo' : $nombre)."': ".$e->getMessage(),
                0,
                $e,
            );
        } finally {
            @unlink($temporal);
        }
    }

    /**
     * El frontend puede mandar un data URL completo; se queda con el base64.
     */
    private static function limpiarBase64(string $base64): string
    {
        $base64 = trim($base64);

        if (preg_match('#^data:[^;]+;base64,#', $base64)) {
            $base64 = (string) preg_replace('#^data:[^;]+;base64,#', '', $base64);
        }

        return preg_replace('/\s+/', '', $base64) ?? $base64;
    }
}
