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
        
        $id = $request->get('id');
        $uniqueId = md5(uniqid());
        $respuesta = collect([
            'exito' => false,
            'desc_respuesta' => NULL,
            'id_transaccion' => $uniqueId,
        ]);
        if (empty($id)) {
            $respuesta->put('desc_respuesta', 'No se ha enviado el id del cliente');
        } else {
            try {
                $paquetes = Paquete::where('estado', 'B')
                ->where('clientecodigo', $id)
                ->where('estadoembarquedescripcion', 'ASUNCION')
                ->leftjoin('embarques', 'embarques.embarquecodigo', '=', 'paquetes.embarquecodigo')
                ->select( 'paquetes.paquetecodigo as codigo','paquetes.paquetepeso as peso', 'paquetes.paquetepeso2 as peso2' , 'paquetes.paqueteprecio as precio')
                ->get();
                
                if (count($paquetes) == 0) {
                    $respuesta->put('desc_respuesta', 'No se encontraron paquetes disponibles');
                } else {
                    $respuesta->put('desc_respuesta', 'OK');
                    $respuesta->put('exito', true);
                }
                $respuesta->put('paquetes', $paquetes);
                $respuesta->put('exito', true);
            } catch (\Exception $e) {
                $respuesta->put('desc_respuesta', $e->getMessage());

            }

        }
        $this->generarAuditoria($request , $respuesta ,$uniqueId, 'paquetes');
        return $respuesta;
        
    }


    public function paquetes_cantidad(Request $request)
    {
        
        $id = $request->get('id');
        $uniqueId = md5(uniqid());
        $respuesta = collect([
            'exito' => false,
            'desc_respuesta' => NULL,
            'id_transaccion' => $uniqueId,
        ]);
        if (empty($id)) {
            $respuesta->put('desc_respuesta', 'No se ha enviado el id del cliente');
        } else {
            try {
                $paquetes = Paquete::where('estado', 'B')
                ->where('clientecodigo', $id)
                ->where('estadoembarquedescripcion', 'ASUNCION')
                ->leftjoin('embarques', 'embarques.embarquecodigo', '=', 'paquetes.embarquecodigo')
                ->count();
                
    
                $respuesta->put('desc_respuesta', 'OK');
                $respuesta->put('paquetes', $paquetes);
                $respuesta->put('exito', true);
            } catch (\Exception $e) {
                $respuesta->put('desc_respuesta', $e->getMessage());

            }

        }
        $this->generarAuditoria($request , $respuesta ,$uniqueId, 'paquetes_cantidad');
        return $respuesta;
        
    }

    public function cliente(Request $request)
    {
        $numero = $request->get('numero') ?? null;
        $uniqueId = md5(uniqid());
        $respuesta = collect([
            'exito' => false,
            'desc_respuesta' => NULL,
            'id_transaccion' => $uniqueId,
        ]);
        if (empty($numero)) {
            $respuesta->put('desc_respuesta', 'No se ha enviado el numero del cliente');
        } else {
            try {
                $cliente = Cliente::where('clientetelefono', $numero)
                ->select(
                    DB::raw("CONCAT(clientenombre, ' ', clienteapellido , ' ' , clientecodigo) as nombre"),

                )
                ->first();
                
                if (empty($cliente))
                    $respuesta->put('desc_respuesta', 'No se ha encontrado el cliente');
                else
                {
                    $cliente = $cliente->nombre ;
                    $respuesta->put('desc_respuesta', 'OK');
                    $respuesta->put('cliente', $cliente);

                }
                $respuesta->put('exito', true);
            } catch (\Exception $e) {
                $respuesta->put('desc_respuesta', $e->getMessage());

            }
    
        }
        $this->generarAuditoria($request , $respuesta ,$uniqueId, 'cliente');
        return $respuesta;
    }

    public function sucursal(Request $request)
    {
       $id = $request->get('id');
       $uniqueId = md5(uniqid());
       $respuesta = collect([
           'exito' => false,
           'desc_respuesta' => NULL,
           'id_transaccion' => $uniqueId,
       ]);
         if (empty($id)) {
              $respuesta->put('desc_respuesta', 'No se ha enviado el id del cliente');
         } else {
              try {
                $sucursal = Cliente::where('clientecodigo', $id)
                ->leftjoin('sucursal', 'sucursal.sucursal', '=', 'clientes.sucursal')
                ->select(
                     'nombre as sucursal'
                )
                ->first();
                
                if (empty($sucursal)) {
                    $respuesta->put('desc_respuesta', 'No se ha encontrado la sucursal del cliente');
                }
                else{
                    $sucursal = $sucursal->sucursal;
                    $respuesta->put('desc_respuesta', 'OK');
                    $respuesta->put('sucursal', $sucursal);
                }
                $respuesta->put('exito', true);
              } catch (\Exception $e) {
                $respuesta->put('desc_respuesta', $e->getMessage());
              }

        }
        $this->generarAuditoria($request , $respuesta ,$uniqueId , 'sucursal');
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
