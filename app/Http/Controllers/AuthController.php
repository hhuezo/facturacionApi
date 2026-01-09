<?php

namespace App\Http\Controllers;

use App\Models\UsuarioEmpresa;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class AuthController extends Controller
{

    public function login(Request $request)
    {
        try {

            $usuario = $request->user;
            $clave   = $request->password;

            $datosUsuario = DB::table('general_usuarios as usuario')
                ->join('general_roles as roles', 'usuario.idRol', '=', 'roles.id')
                ->select(
                    'usuario.id',
                    'usuario.nombre',
                    'usuario.email',
                    'usuario.usuario',
                    'usuario.idRol',
                    'roles.nombre as nombreRol',
                    'usuario.clave'
                )
                ->where('usuario.usuario', $usuario)
                ->where('usuario.eliminado', 'N')
                ->first();

            // Usuario no existe
            if (!$datosUsuario) {
                return response()->json([
                    'success' => false,
                    'message' => 'USUARIO_CLAVE_INCORRECTO'
                ], 401);
            }

            // Validación de contraseña
            if (!password_verify($clave, $datosUsuario->clave)) {
                return response()->json([
                    'success' => false,
                    'message' => 'USUARIO_CLAVE_INCORRECTO'
                ], 401);
            }

            // Empresas asignadas al usuario
            $empresas = UsuarioEmpresa::with('empresa:id,nombre')
                ->where('idUsuarioAsignado', $datosUsuario->id)
                ->where('eliminado', 'N')
                ->get()
                ->pluck('empresa')
                ->filter()          // por si alguna relación viene null
                ->unique('id')
                ->values();

            unset($datosUsuario->clave);

            $datosUsuario->empresas = $empresas;

            // Respuesta OK
            return response()->json([
                'success' => true,
                'data'    => $datosUsuario
            ], 200);

        } catch (Throwable $e) {

            return response()->json([
                'success' => false,
                'message' => 'ERROR_INTERNO'
            ], 500);
        }
    }


}
