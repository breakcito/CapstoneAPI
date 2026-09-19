<?php

namespace App\Controllers;

use App\Services\MenuNavService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class MenuNavController extends Controller
{

    public function get_menu_navegacion(Request $request): JsonResponse
    {
        $authUser = $request->attributes->get('auth_user');
        $result = MenuNavService::get_menu_navegacion($authUser->id_rol);

        return response()->json($result);
    }
}
