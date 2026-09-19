<?php
namespace App\Modules\System\Data;

use App\Models\UnidadMedida;
use Illuminate\Support\Facades\DB;

class UnidadesMedidaSystemData
{
    public static function listar(): array
    {
        return UnidadMedida::orderBy('nombre')->get()->toArray();
    }

    public static function obtener(int $id): ?UnidadMedida
    {
        return UnidadMedida::find($id);
    }

    public static function crear(string $nombre, string $abreviatura): int
    {
        return UnidadMedida::insertGetId([
            'nombre' => $nombre,
            'abreviatura' => $abreviatura,
        ]);
    }

    public static function editar(int $id, string $nombre, string $abreviatura): bool
    {
        return UnidadMedida::where('id', $id)->update([
            'nombre' => $nombre,
            'abreviatura' => $abreviatura,
        ]) > 0;
    }

    public static function eliminar(int $id): bool
    {
        return UnidadMedida::where('id', $id)->delete() > 0;
    }

    public static function ya_existe(string $nombre, string $abreviatura, ?int $excluir_id = null): bool
    {
        $q = UnidadMedida::query();
        $q->where(function ($w) use ($nombre, $abreviatura) {
            $w->orWhereRaw('LOWER(nombre) = ?', [mb_strtolower($nombre)]);
            $w->orWhereRaw('LOWER(abreviatura) = ?', [mb_strtolower($abreviatura)]);
        });
        if ($excluir_id !== null) $q->where('id', '!=', $excluir_id);
        return $q->exists();
    }

    public static function tiene_conversiones_o_uso(int $id): bool
    {
        $enConv = DB::table('conversion_unidad_medida')
            ->where('id_unidad_medida_a', $id)
            ->orWhere('id_unidad_medida_b', $id)
            ->exists();
        if ($enConv) return true;

        $uso = DB::table('lote_producto')->where('id_unidad_medida', $id)->exists()
            || DB::table('producto')->where('id_unidad_medida_base', $id)->exists();
        return $uso;
    }
}
