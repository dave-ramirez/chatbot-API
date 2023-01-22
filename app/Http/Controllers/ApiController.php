<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Paquete;
use App\Models\Cliente;
use App\Models\ClienteToken;
use App\Models\AuditoriaIntegracion;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
class ApiController extends Controller
{


    public function login(Request $request)
    {
        $cedula = $request->get('cedula') ?? null;
        $numero = $request->get('numero') ?? null;
        $uniqueId = md5(uniqid());
        $respuesta = collect([
            'exito' => false,
            'desc_respuesta' => NULL,
            'id_transaccion' => $uniqueId,
        ]);

        if (empty($cedula) || empty($numero)) {
            $respuesta->put('desc_respuesta', 'No se han recibido los parametros');
        } else {
            try {
                $cliente = Cliente::where('clienteci', $cedula)
                ->first();

                
                    if (empty($cliente)) {
                        $respuesta->put('desc_respuesta', 'No se encontraron datos');
                    } else {
                        $checkNumero = $this->checkNumero($numero, $cliente);
                        if ($checkNumero == false) {
                            $respuesta->put('desc_respuesta', 'Los datos no coinciden');
                            $this->generarAuditoria($request , $respuesta ,$uniqueId, 'login');
                            return $respuesta;
                        }
                        $respuesta->put('desc_respuesta', 'OK');
                        $respuesta->put('exito', true);
                        $token = $this->generarToken($cliente->clientecodigo);
                        $respuesta->put('token', $token);
                    }
                 
            } catch (\Exception $e) {
                $respuesta->put('desc_respuesta', "Algo salio mal");
                info("Error en login " . $e->getMessage());

            }

        }

        $this->generarAuditoria($request , $respuesta ,$uniqueId, 'login');
        return $respuesta;

    }

   

    private function checkNumero($numero,$cliente)
    {

        $numeroBD= $cliente->clientecelular;

        if (strpos($numeroBD, '+5') !== false) {
            $numeroBD = preg_replace('/[^0-9]/', '', $numeroBD);
            $numeroBD = '+'.$numeroBD;
            return $numeroBD == $numero;
        }else {
            $numeroBD = preg_replace('/[^0-9]/', '', $numeroBD);

            if (strpos($numeroBD, '0') === 0) {
                $numeroBD = '+595'.substr($numeroBD, 1);
                return $numeroBD == $numero;
            } else{

                if (strpos($numeroBD, '5') === 0)
                {
                    $numeroBD = '+'.$numeroBD;
                }
                else{
                    $numeroBD = '+595'.$numeroBD;
                }
                return $numeroBD == $numero;
            }
        }


    }

    private function generarToken($codigo)
    {
        $token = md5(uniqid());
        $fechaExpiracion = Carbon::now()->addMinutes(3);
        $clienteToken = ClienteToken::where('cliente_id', $codigo)->first();
        if (!empty($clienteToken)) {
            $clienteToken->token = $token;
            $clienteToken->fecha_expiracion = $fechaExpiracion;
            $clienteToken->save();
            return $token;
        }

        $clienteToken = ClienteToken::create([
            'token' => $token,
            'cliente_id' => $codigo,
            'fecha_expiracion' => $fechaExpiracion,
        ]);
        return $token;

    }

    public function paquetes(Request $request)
    {
        $token = $request->get('token') ?? null;
        $uniqueId = md5(uniqid());
        $respuesta = collect([
            'exito' => false,
            'desc_respuesta' => NULL,
            'id_transaccion' => $uniqueId,
        ]);
        if (empty($token)) {
            $respuesta->put('desc_respuesta', 'No se han recibido los parametros');
        } else {
            try {


                $clienteToken = ClienteToken::where('token', $token)->where('fecha_expiracion', '>', Carbon::now())->first();

                if(empty($clienteToken)){
                    $respuesta->put('desc_respuesta', 'El token no es valido');
                }else{
                    $paquetes = Paquete::where('paquetes.estado', 'B')
                    ->where('clientes.clientecodigo', $clienteToken->cliente_id)
                    ->where('estadoembarquedescripcion', 'ASUNCION')
                    ->leftjoin('embarques', 'embarques.embarquecodigo', '=', 'paquetes.embarquecodigo')
                    ->leftjoin('clientes', 'clientes.clientecodigo', '=', 'paquetes.clientecodigo')
                    ->select( 'paquetes.paquetecodigo as codigo','paquetes.paquetepeso as peso', 'paquetes.paquetepeso2 as peso2' , 'paquetes.paqueteprecio as precio' , 'paquetes.tasa as cambioDia')
                    ->get();
                    
                    if (count($paquetes) == 0) {
                        $respuesta->put('desc_respuesta', 'No se encontraron paquetes disponibles');
                    } else {
                        $paquetes = $paquetes->map(function ($paquete) {
                            if( $paquete->cambioDia > 0)
                            {
                                $paquete->precioGuaranies = ceil($paquete->precio * $paquete->cambioDia);
                            }
                            else {
                                $paquete->precioGuaranies = 0;
                            }
                            return $paquete;
    
                        });
                        $respuesta->put('desc_respuesta', 'OK');
                        $respuesta->put('exito', true);
                    }
                    $respuesta->put('paquetes', $paquetes);
                    $respuesta->put('exito', true);
                    $clienteToken->fecha_expiracion = Carbon::now()->addMinutes(3);
                    $clienteToken->save();
                }
                
            } catch (\Exception $e) {
                $respuesta->put('desc_respuesta', "Algo salio mal");
                info("Error en paquetes " . $e->getMessage());

            }

        }
        $this->generarAuditoria($request , $respuesta ,$uniqueId, 'paquetes');
        return $respuesta;
        
    }


    public function paquetes_cantidad(Request $request)
    {
        $token = $request->get('token') ?? null;
        $uniqueId = md5(uniqid());
        $respuesta = collect([
            'exito' => false,
            'desc_respuesta' => NULL,
            'id_transaccion' => $uniqueId,
        ]);
        if (empty($token)) {
            $respuesta->put('desc_respuesta', 'No se han recibido los parametros');
        } else {
            try {

                $clienteToken = ClienteToken::where('token', $token)->where('fecha_expiracion', '>', Carbon::now())->first();

                if (empty($clienteToken)) {
                    $respuesta->put('desc_respuesta', 'El token no es valido');
                    return $respuesta;
                }else {
                    $paquetes = Paquete::where('paquetes.estado', 'B')
                    ->where('clientes.clientecodigo', $clienteToken->cliente_id)
                    ->where('estadoembarquedescripcion', 'ASUNCION')
                    ->leftjoin('clientes', 'clientes.clientecodigo', '=', 'paquetes.clientecodigo')
                    ->leftjoin('embarques', 'embarques.embarquecodigo', '=', 'paquetes.embarquecodigo')
                    ->count();
                    
        
                    $respuesta->put('desc_respuesta', 'OK');
                    $respuesta->put('paquetes', $paquetes);
                    $respuesta->put('exito', true);
                    // update the token expiration date
                    $clienteToken->fecha_expiracion = Carbon::now()->addMinutes(3);
                    $clienteToken->save();
                }

                
            } catch (\Exception $e) {
                $respuesta->put('desc_respuesta', "Algo salio mal");
                info("Error en paquetes_cantidad " . $e->getMessage());

            }

        }
        $this->generarAuditoria($request , $respuesta ,$uniqueId, 'paquetes_cantidad');
        return $respuesta;
        
    }

    public function cliente(Request $request)
    {
        $token = $request->get('token') ?? null;
        $uniqueId = md5(uniqid());
        $respuesta = collect([
            'exito' => false,
            'desc_respuesta' => NULL,
            'id_transaccion' => $uniqueId,
        ]);
        if (empty($token)) {
            $respuesta->put('desc_respuesta', 'No se han recibido los parametros');
        } else {
            try {


                $clienteToken = ClienteToken::where('token', $token)->where('fecha_expiracion', '>', Carbon::now())->first();
                if (empty($clienteToken)) {
                    $respuesta->put('desc_respuesta', 'El token no es valido');
                    return $respuesta;
                }else {
                    $cliente = Cliente::where('clientecodigo', $clienteToken->cliente_id)
                    ->leftjoin('sucursal', 'sucursal.sucursal', '=', 'clientes.sucursal')
                    ->select(
                        DB::raw("CONCAT(clientenombre, ' ', clienteapellido ) as nombre"),
                        'clientecodigo as codigo',
                        'sucursal.nombre as sucursal'
                    )
                    ->first();
                    
                    if (empty($cliente)) {
                        $respuesta->put('desc_respuesta', 'No se ha encontrado el cliente');
                    }else {
                        $respuesta->put('desc_respuesta', 'OK');
                        $respuesta->put('cliente', $cliente);
    
                    }
                    $respuesta->put('exito', true);
                    $clienteToken->fecha_expiracion = Carbon::now()->addMinutes(3);
                    $clienteToken->save();
                }
                
            } catch (\Exception $e) {
                $respuesta->put('desc_respuesta', "Algo salio mal");
                info("Error en paquetes_cantidad " . $e->getMessage());
            }
    
        }
        $this->generarAuditoria($request , $respuesta ,$uniqueId, 'cliente');
        return $respuesta;
    }




    private function generarAuditoria($request , $respuesta ,$uniqueId, $funcion)
    {

        AuditoriaIntegracion::create([
            'uniqueid' => $uniqueId,
            'funcion' => $funcion,
            'ips' => $request->ip(),
            'metodo' => $request->method(),
            'request' => json_encode($request->all()),
            'response' => json_encode($respuesta),
            'user_agent' => $request->header('User-Agent') ?? null,
        ]);


    }
}
