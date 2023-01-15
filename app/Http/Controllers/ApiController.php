<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Paquete;
use App\Models\Cliente;
use App\Models\AuditoriaIntegracion;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class ApiController extends Controller
{



    public function paquetes(Request $request)
    {
        $cedula = $request->get('cedula') ?? null;
        $uniqueId = md5(uniqid());
        $respuesta = collect([
            'exito' => false,
            'desc_respuesta' => NULL,
            'id_transaccion' => $uniqueId,
        ]);
        if (empty($cedula)) {
            $respuesta->put('desc_respuesta', 'No se han recibido los parametros');
        } else {
            try {
                $paquetes = Paquete::where('paquetes.estado', 'B')
                ->where('clienteci', $cedula)
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
        $cedula = $request->get('cedula') ?? null;
        $uniqueId = md5(uniqid());
        $respuesta = collect([
            'exito' => false,
            'desc_respuesta' => NULL,
            'id_transaccion' => $uniqueId,
        ]);
        if (empty($cedula)) {
            $respuesta->put('desc_respuesta', 'No se han recibido los parametros');
        } else {
            try {
                $paquetes = Paquete::where('paquetes.estado', 'B')
                ->where('clienteci', $cedula)
                ->where('estadoembarquedescripcion', 'ASUNCION')
                ->leftjoin('clientes', 'clientes.clientecodigo', '=', 'paquetes.clientecodigo')
                ->leftjoin('embarques', 'embarques.embarquecodigo', '=', 'paquetes.embarquecodigo')
                ->count();
                
    
                $respuesta->put('desc_respuesta', 'OK');
                $respuesta->put('paquetes', $paquetes);
                $respuesta->put('exito', true);
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
        $cedula = $request->get('cedula') ?? null;
        $uniqueId = md5(uniqid());
        $respuesta = collect([
            'exito' => false,
            'desc_respuesta' => NULL,
            'id_transaccion' => $uniqueId,
        ]);
        if (empty($cedula)) {
            $respuesta->put('desc_respuesta', 'No se han recibido los parametros');
        } else {
            try {
                $cliente = Cliente::where('clienteci', $cedula)
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
