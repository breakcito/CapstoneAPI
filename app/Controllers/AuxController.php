<?php

namespace App\Controllers;

use App\Services\AlmacenesService;
use App\Services\ContratistasService;
use App\Services\EmpleadosService;
use App\Services\LotesProductosService;
use App\Services\ProductosService;
use App\Services\RolesService;
use App\Services\UbicacionService;
use App\Services\UnidadesMedidaService;
use App\Shared\Enums\_Generic\EstadoBase;
use App\Shared\Enums\_Generic\Periodo;
use App\Shared\Enums\_Generic\TipoProducto;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Enum;

class AuxController extends Controller
{
    public function get_almacenes(Request $request): JsonResponse
    {
        $id_almacen = $request->input('id_almacen') ? (int) $request->input('id_almacen') : null;
        $id_empleado_responsable = $request->input('id_empleado_responsable') ? (int) $request->input('id_empleado_responsable') : null;

        return response()->json(AlmacenesService::get_almacenes(
            id_almacen: $id_almacen,
            id_empleado_responsable: $id_empleado_responsable,
        ));
    }

    /**
     * Obtener lotes disponibles en el almacén de destino para productos de OC.
     */
    public function get_lotes_disponibles(Request $request): JsonResponse
    {
        $id_almacen = (int) $request->input('id_almacen');
        $ids_productos = $request->input('ids_productos');

        if (!$id_almacen || empty($ids_productos) || !is_array($ids_productos)) {
            return response()->json(ApiResponse::error('ID de almacén y arreglo de productos son requeridos'), 400);
        }

        return response()->json(LotesProductosService::get_lotes_disponibles($id_almacen, $ids_productos));
    }
    public function get_empleados(Request $request): JsonResponse
    {
        $id_empleado = $request->input('id_empleado') ? (int) $request->input('id_empleado') : null;
        $estado_val = $request->input('estado');
        $estado = $estado_val ? EstadoBase::from($estado_val) : EstadoBase::Activo;
        $id_almacen_excluyente = $request->input('id_almacen_excluyente') ? (int) $request->input('id_almacen_excluyente') : null;
        $con_cuenta = $request->has('con_cuenta') ? $request->boolean('con_cuenta') : null;

        $result = EmpleadosService::get_empleados(
            id_empleado: $id_empleado,
            estado: $estado,
            id_almacen_excluyente: $id_almacen_excluyente,
            con_cuenta: $con_cuenta
        );

        return response()->json($result);
    }

    /**
     * Obtener los roles disponibles para asignar
     */
    public function get_roles_disponibles(Request $request): JsonResponse
    {
        $id_rol = $request->input('id_rol') ? (int) $request->input('id_rol') : null;
        $estado_val = $request->input('estado');
        $estado = $estado_val ? EstadoBase::from($estado_val) : EstadoBase::Activo;

        $result = RolesService::get_roles(
            id_rol: $id_rol,
            estado: $estado
        );

        return response()->json($result);
    }


    /**
     * Obtiene las unidades de medida. Acepta filtros opcionales.
     */
    public function get_unidades_medida(Request $request): JsonResponse
    {
        $id_unidad_medida = $request->input('id_unidad_medida') ? (int) $request->input('id_unidad_medida') : null;
        $incluir_conversiones = $request->input('incluir_conversiones') ? (bool) $request->input('incluir_conversiones') : null;

        $result = UnidadesMedidaService::get_unidades(
            id_unidad_medida: $id_unidad_medida,
            incluir_conversiones: $incluir_conversiones
        );

        return response()->json($result);
    }

    /**
     * Crear una nueva unidad de medida en el catálogo.
     */
    public function crear_unidad_medida(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|min:2|max:64',
            'abreviatura' => 'required|string|min:1|max:8',
        ], [
            'nombre.required' => 'El nombre es requerido',
            'nombre.min' => 'El nombre debe tener al menos 2 caracteres',
            'nombre.max' => 'El nombre no puede tener más de 64 caracteres',
            'abreviatura.required' => 'La abreviatura es requerida',
            'abreviatura.max' => 'La abreviatura no puede tener más de 8 caracteres',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()));
        }

        $result = UnidadesMedidaService::crear_unidad_medida(
            nombre: $request->input('nombre'),
            abreviatura: $request->input('abreviatura'),
        );

        return response()->json($result);
    }


    /**
     * Catálogo de productos.
     */
    public function get_productos(Request $request): JsonResponse
    {
        return response()->json(ProductosService::get_productos());
    }

    /**
     * Crear un nuevo producto
     */
    public function crear_producto(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_unidad_medida_base' => 'required|integer',
            'nombre' => 'required|string|max:128',
            'tipo_producto' => 'nullable|string|max:128',
            'es_perecible' => 'required|boolean',
            'stock_minimo_base' => 'nullable|numeric|min:0',
            'tiempo_espera_vencimiento' => 'nullable|integer|min:0',
            'periodo_espera_vencimiento' => 'nullable|string|max:32',
        ], [
            'id_unidad_medida_base.required' => 'La unidad de medida es requerida',
            'nombre.required' => 'El nombre es requerido',
            'es_perecible.required' => 'Debe indicar si es perecible',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()));
        }

        $result = ProductosService::crear_producto(
            id_unidad_medida_base: $request->integer('id_unidad_medida_base'),
            nombre: $request->string('nombre'),
            tipo_producto: $request->input('tipo_producto'),
            es_perecible: $request->boolean('es_perecible'),
            stock_minimo_base: (float) ($request->input('stock_minimo_base') ?? 0),
            tiempo_espera_vencimiento: $request->input('tiempo_espera_vencimiento') !== null ? (int) $request->input('tiempo_espera_vencimiento') : null,
            periodo_espera_vencimiento: $request->input('periodo_espera_vencimiento')
        );

        return response()->json($result);
    }

    /**
     * Catálogo de contratistas. Acepta filtro opcional por mina.
     */
    public function get_contratistas(Request $request): JsonResponse
    {
        $id_mina = $request->input('id_mina') ? (int) $request->input('id_mina') : null;
        $id_contratista = $request->input('id_contratista') ? (int) $request->input('id_contratista') : null;

        return response()->json(ContratistasService::get_contratistas(id_mina: $id_mina, id_contratista: $id_contratista));
    }

    public function crear_contratista(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_mina' => 'required|integer',
            'nombre' => 'required|string|max:255',
            'apellido' => 'required|string|max:255',
            'genero' => 'nullable|string|max:16',
            'dni' => 'nullable|string|max:20',
            'ruc' => 'nullable|string|max:20',
            'carnet_extranjeria' => 'nullable|string|max:20',
            'pasaporte' => 'nullable|string|max:20',
            'fecha_nacimiento' => 'nullable|date',
            'direccion' => 'nullable|string|max:255',
            'telefono' => 'nullable|string|max:32',
            'email' => 'nullable|email|max:128',
            'foto' => 'nullable|image|mimes:jpg,png,jpeg|max:2048',
            'ids_labor' => 'nullable|array',
            'ids_labor.*' => 'integer',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()));
        }

        $result = ContratistasService::crear_contratista(
            nombre: (string) $request->input('nombre'),
            apellido: (string) $request->input('apellido'),
            dni: $request->input('dni'),
            foto: $request->file('foto')
        );

        return response()->json($result);
    }

    /**
     * Departamentos del Perú. Filtro opcional ?id_departamento.
     */
    public function get_departamentos(Request $request): JsonResponse
    {
        $id_departamento = $request->input('id_departamento') ? (int) $request->input('id_departamento') : null;

        return response()->json(UbicacionService::get_departamentos(
            id_departamento: $id_departamento
        ));
    }

    /**
     * Provincias del Perú. Filtros opcionales ?id_provincia y ?id_departamento.
     */
    public function get_provincias(Request $request): JsonResponse
    {
        $id_provincia = $request->input('id_provincia') ? (int) $request->input('id_provincia') : null;
        $id_departamento = $request->input('id_departamento') ? (int) $request->input('id_departamento') : null;

        return response()->json(UbicacionService::get_provincias(
            id_provincia: $id_provincia,
            id_departamento: $id_departamento
        ));
    }

    /**
     * Distritos del Perú. Filtros opcionales ?id_distrito, ?id_provincia, ?id_departamento.
     */
    public function get_distritos(Request $request): JsonResponse
    {
        $id_distrito = $request->input('id_distrito') ? (int) $request->input('id_distrito') : null;
        $id_provincia = $request->input('id_provincia') ? (int) $request->input('id_provincia') : null;
        $id_departamento = $request->input('id_departamento') ? (int) $request->input('id_departamento') : null;

        return response()->json(UbicacionService::get_distritos(
            id_distrito: $id_distrito,
            id_provincia: $id_provincia,
            id_departamento: $id_departamento
        ));
    }
}
