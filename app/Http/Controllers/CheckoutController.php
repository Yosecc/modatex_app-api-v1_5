<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Objects\NotificationsPush;
use App\Models\Client;
use Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class CheckoutController extends Controller
{
    private $url = 'https://www.modatex.com.ar/modatexrosa3/?c=';
    private $token;

    private $envios = [[
                'id'           =>  1,
                'title'        => 'Envío a sucursal',
                'precios'      =>  ['Desde $740 a todo el país'],
                'description'  => 'Seleccionando esta opción te enviamos el pedido a la sucursal próxima que elijas en 4 a 7 días.',
                'description2' =>  'Esta tienda agregará un costo adicional de $70 por traslado hasta el transporte.',
                'active'       => false,
                'color'        => '#239B56',
                'icon'         => 'res://sucursal',
                'agregados'    =>[
                    // [
                    //     'concepto' => 'Costo de traslado',
                    //     'value' => 70
                    // ]
                ],
                'method'       => 'post_office',
                'isFree'       => false
            ],
            [
                'id'           =>  2,
                'title'        => 'Envío a domicilio',
                'precios'      =>  ['$380 en CABA','$480 en GBA','Desde $1140 al resto del país'],
                'description'  => 'Te enviamos el pedido a tu domicilio por moto de 48 a 72 horas, de 8:00hs a 20:00hs y, por Correo Argentino y OCA de 5 a 9 días hábiles.',
                'description2' =>  'Esta tienda agregará un costo adicional de $70 por traslado hasta el transporte.',
                'active'       =>  false,
                'color'        =>  '#CA6F1E',
                'icon'         =>  'res://enviocasa',
                'agregados'    => [
                    // [
                    //     "concepto"=> 'Costo de traslado',
                    //     "value"=> 70
                    // ]
                ],
                'method'       => 'home_delivery',
                'isFree'       =>  false
            ],
            [
                'id'               => 3,
                'title'            =>'Transporte tradicional',
                'precios'          => ['$150 por costo de traslado hasta el transporte elegido. Luego pagás el resto en destino.'],
                'description'      => 'Elegí el transporte que llega a tu ciudad.',
                'description2'     =>'Esta tienda agregará un costo adicional de $70 por manipulación y embalaje.',
                'active'           => false,
                'color'            => '#1976D2',
                'icon'             => 'res://envio',
                'agregados'        =>[
                    // [
                    //     'concepto' => 'Envío',
                    //     'value'    => 150
                    // ],
                    // [
                    //     'concepto' => 'Manipulación y embalaje',
                    //     'value'    => 70
                    // ],
                ],
                'method'           => 'transport',
                'isFree'           => false
            ],
            [
                'id'               => 4,
                'title'            =>'INTEGRALPACK',
                'precios'          => ['$850 por costo de servicio.'],
                'description'      =>'Envíos a terminal de omnibus en 48 a 72 horas. Buscá si llegamos a tu ciudad!',
                'description2'     => 'Esta tienda agregará un costo adicional de $70 por traslado hasta el transporte.',
                'active'           => false,
                'color'            => '#CDDC39',
                'icon'             => 'res://integralpack',
                'agregados'        =>[
                    // [
                    //     'concepto' => 'Envío',
                    //     'value'    => 850
                    // ],
                    // [
                    //     'concepto' => 'Costo de traslado',
                    //     'value'    => 70
                    // ],
                ],
                'method'           => 'integral_pack',
                'isFree'           => false
            ],
            [
                'id'           => 5,
                'title'        =>'Retiro por depósito',
                'precios'      => [],
                'description'  =>'Retira la compra en el depósito.',
                'description2' => 'El horario de atención para el retiro de los paquetes en el depósito de Flores, CABA es de Lunes a Viernes de 8:00hs a 15:00hs.',
                'active'       => false,
                'color'        => '#5E35B1',
                'icon'         => 'res://enviostore',
                'agregados'    => [],
                'method'       => 'store_pickup',
                'isFree'       => false
            ]];

    public function getEnvios(Request $request)
    {
        try {   
            $this->token = Auth::user()->api_token;

             $response = Http::withHeaders([
              'x-api-key' => $this->token,
            ])
            ->asForm()
            ->post($this->generateUrl(['controller' => 'Checkout','method' => 'shipping_prices']), 
                $request->all());


            if($response->json()['status'] != 'success'){
                throw new \Exception("No se encontraron resultados");
            }

            $envios = $this->envios;
            $datos = [];

            
            foreach ($response->json()['data'] as $key => $envio) {
                $indice = array_search($envio['method'], array_column($envios, 'method'));
                $data = $envios[$indice];

                $data['isFree'] = $envio['is_free'];

                $precios = [];
                if(isset($envio['extra_charges']) ){

                    $precios[] =
                        [
                            'value' => $envio['extra_charges']['cost'],
                            'concepto' => $envio['extra_charges']['descrip']
                        ];
                }

                if(isset($envio['curr'])){
                    $precios[] = [
                        'value' => $envio['curr'],
                        'concepto' =>  'Envío'
                    ];
                }

                $data['agregados'] = $precios;

                $datos[] = $data;
            }

            array_push($datos, $envios[array_search('store_pickup', array_column($envios, 'method'))]);

            return response()->json($datos);

        } catch (\Exception $e) {
                return response()->json(['message'=>$e->getMessage()],422);
        }
    }

    public function editClient(Request $request)
    {
        $this->validate($request, [
            'first_name' => 'required',
            'last_name'  => 'required',
            'cuit_dni'   => 'required',
        ]);

        $this->token = Auth::user()->api_token; 

        try {
            
        Client::where('num',Auth::user()->num)->update([
                            'first_name'   => $request->first_name,
                            'last_name'    => $request->last_name,
                            'cuit_dni'     => $request->cuit_dni]);
         return response()->json('OK');
       
        } catch (\Exception $e) {
            return response()->json($e->getMessage(),422);
        }

    }

    public function selectMethodEnvio(Request $request)
    {
        $this->validate($request, [
            'group_id' => 'required',
            'method'  => 'required',
        ]);

        $this->token = Auth::user()->api_token; 

        try {
            $response = Http::withHeaders([
              'x-api-key' => $this->token,
            ])
            ->asForm()
            ->post($this->generateUrl(['controller' => 'Checkout','method' => 'shipping_select_method']), $request->all());

              return response()->json($response->json());
            
        } catch (\Exception $e) {
            return response()->json($e->getMessage(),422);
        }
    }

    public function searchSucursales(Request $request)
    {
        $this->validate($request, [
            'group_id' => 'required',
            'zipcode'  => 'required',
        ]);

        $this->token = Auth::user()->api_token;

        try {
            $response = Http::withHeaders([
              'x-api-key' => $this->token,
            ])
            ->asForm()
            ->post($this->generateUrl(['controller' => 'Checkout','method' => 'shipping_branches']), 
                $request->all());

            $response = $response->json();

            if($response['status'] != 'success'){
                throw new \Exception("No se encontraron resultados");
            }

              return response()->json($response['data']);
            
        } catch (\Exception $e) {
            return response()->json(['message'=>$e->getMessage()],422);
        }
    }

    public function datosEnvio(Request $request)
    {
        $this->validate($request, [
            'group_id' => 'required',
            'method'     => 'required',
        ]);

        $this->token = Auth::user()->api_token;

        try {
            $response = Http::withHeaders([
              'x-api-key' => $this->token,
            ])
            ->asForm()
            ->post($this->generateUrl(['controller' => 'Checkout','method' => 'shipping_linked_data']), 
                $request->all());

            $response = $response->json();

            if($response['status'] != 'success'){
                throw new \Exception("No se encontraron resultados");
                // throw new \Exception($response->json());
            }

              return response()->json($response['data']);
            
        } catch (\Exception $e) {
            return response()->json(['message'=>$e->getMessage()],422);
        }
    }    

    public function envioDetail(Request $request)
    {
        $this->validate($request, [
            'group_id' => 'required',
            'method'     => 'required',
        ]);

        $this->token = Auth::user()->api_token;

        try {
            $response = Http::withHeaders([
              'x-api-key' => $this->token,
            ])
            ->asForm()
            ->post($this->generateUrl(['controller' => 'Checkout','method' => 'shipping_edit']), 
                $request->all());

            $response = $response->json();

            if($response['status'] != 'success'){
                throw new \Exception("No se encontraron resultados");
                // throw new \Exception($response->json());
            }

              return response()->json($response['data']);
            
        } catch (\Exception $e) {
            return response()->json(['message'=>$e->getMessage()],422);
        }
    }

    public function deleteShipping(Request $request)
    {
        $this->validate($request, [
            'group_id' => 'required',
            'id'         => 'required',
            'method'     => 'required',
        ]);

        $this->token = Auth::user()->api_token;

        try {
            $response = Http::withHeaders([
              'x-api-key' => $this->token,
            ])
            ->asForm()
            ->post($this->generateUrl(['controller' => 'Checkout','method' => 'shipping_remove']), 
                $request->all());

            $response = $response->json();

            if($response['status'] != 'success'){
                throw new \Exception("No se encontraron resultados");
            }
            if(isset($response['data'])){
              return response()->json($response['data']);
            }
            return response()->json($response);
        } catch (\Exception $e) {
            return response()->json(['message'=>$e->getMessage()],422);
        }
    }

    public function homeDeliveryProviders(Request $request)
    {
        try {   
            $this->token = Auth::user()->api_token;
             $response = Http::withHeaders([
              'x-api-key' => $this->token,
            ])
            ->asForm()
            ->post($this->generateUrl(['controller' => 'Checkout','method' => 'home_delivery_providers']), 
                $request->all());


            if($response->json()['status'] != 'success'){
                throw new \Exception("No se encontraron resultados");
            }
            if(isset($response->json()['data'])){
              return response()->json($response->json()['data']);
            }
            return response()->json($response->json()['data']);

        } catch (\Exception $e) {
                return response()->json(['message'=>$e->getMessage()],422);
        }
    }

    public function editServiceProvider(Request $request)
    {
        try {   
            $this->token = Auth::user()->api_token;
             $response = Http::withHeaders([
              'x-api-key' => $this->token,
            ])
            ->asForm()
            ->post($this->generateUrl(['controller' => 'Checkout','method' => 'edit_service_provider']), 
                $request->all());


            if($response->json()['status'] != 'success'){
                throw new \Exception("No se encontraron resultados");
            }
            if(isset($response->json()['data'])){
              return response()->json($response->json()['data']);
            }
            return response()->json($response->json()['data']);

        } catch (\Exception $e) {
                return response()->json(['message'=>$e->getMessage()],422);
        }
    }

    public function isDatosFacturacion(Request $request)
    {
        try {   
            $this->token = Auth::user()->api_token;

             $response = Http::withHeaders([
              'x-api-key' => $this->token,
            ])
            ->asForm()
            ->post($this->generateUrl(['controller' => 'Checkout','method' => 'billing_data']), $request->all());


            if($response->json()['status'] != 'success'){
                throw new \Exception("No se encontraron resultados");
            }
            if(isset($response->json()['data'])){
              return response()->json($response->json()['data']);
            }
            return response()->json($response->json()['data']);

        } catch (\Exception $e) {
                return response()->json(['message'=>$e->getMessage()],422);
        }
    }

    public function datosFacturacion(Request $request)
    {
        try {   
            $this->token = Auth::user()->api_token;
             $response = Http::withHeaders([
              'x-api-key' => $this->token,
            ])
            ->asForm()
            ->post($this->generateUrl(['controller' => 'Checkout','method' => 'billing_edit']), 
                $request->all());


            if($response->json()['status'] != 'success'){
                throw new \Exception("No se encontraron resultados");
            }
            if(isset($response->json()['data'])){
              return response()->json($response->json()['data']);
            }
            return response()->json($response->json()['data']);

        } catch (\Exception $e) {
                return response()->json(['message'=>$e->getMessage()],422);
        }
    }

    public function selectMethodPayment(Request $request)
    {
        try {   
            $this->token = Auth::user()->api_token;
             $response = Http::withHeaders([
              'x-api-key' => $this->token,
            ])
            ->asForm()
            ->post($this->generateUrl(['controller' => 'Checkout','method' => 'payment_select']), 
                $request->all());

            if($response->json()['status'] != 'success'){
                throw new \Exception("No se encontraron resultados");
            }

            return response()->json($response->json());

        } catch (\Exception $e) {
                return response()->json(['message'=>$e->getMessage()],422);
        }
    }

    public function getResumen(Request $request)
    {
        try {   
            $this->token = Auth::user()->api_token;
             $response = Http::withHeaders([
              'x-api-key' => $this->token,
            ])
            ->asForm()
            ->post($this->generateUrl(['controller' => 'Checkout','method' => 'summary']), 
                $request->all());

            // dd($response->json());

             if($response->json()['status'] == 'error'){
                throw new \Exception($response->json()['message']);
            }

            if($response->json()['status'] != 'success'){
                throw new \Exception("No se encontraron resultados");
            }
            if(isset($response->json()['data'])){
              return response()->json($response->json()['data']);
            }
            return response()->json($response->json()['data']);

        } catch (\Exception $e) {
                return response()->json(['message'=>$e->getMessage()],422);
        }
    }

    public function confirmarCompra(Request $request)
    {
        try {   
            $this->token = Auth::user()->api_token;
             $response = Http::withHeaders([
              'x-api-key' => $this->token,
            ])
            ->asForm()
            ->post($this->generateUrl(['controller' => 'Checkout','method' => 'confirm_purchase']), 
                $request->all());


            if($response->json()['status'] != 'success'){
                throw new \Exception("No se encontraron resultados");
            }
              
              try {
                $notification = new NotificationsPush(['notification'=>[
                  'title' => 'Gracias por tu compra',
                  "body" => 'Su compra ha sido procesada con éxito. Pronto nos comunicaremos'
                ]]);
                $notification->sendUserNotification(Auth::user()->num);
              } catch (\Exception $e) {
                
              }
              
            if(isset($response->json()['data'])){
              return response()->json($response->json()['data']);
            }
            return response()->json($response->json()['data']);

        } catch (\Exception $e) {
                return response()->json(['message'=>$e->getMessage()],422);
        }
    }

    public function couponUnselectAll(Request $request)
    {
        try {
            
            $this->token = Auth::user()->api_token;
             $response = Http::withHeaders([
              'x-api-key' => $this->token,
            ])
            ->asForm()
            ->post($this->generateUrl(['controller' => 'Coupons','method' => 'unselect_all']), 
                $request->all());

            if($response->json()['status'] != 'success'){
                throw new \Exception("Error");
            }

            return response()->json($response->json());

        } catch (\Exception $e) {
            return response()->json(['message'=>$e->getMessage()],422);
        }
    }

    public function couponSelect(Request $request)
    {
        try {
            
            $this->token = Auth::user()->api_token;
             $response = Http::withHeaders([
              'x-api-key' => $this->token,
            ])
            ->asForm()
            ->post($this->generateUrl(['controller' => 'Coupons','method' => 'select']), 
                $request->all());

            if($response->json()['status'] != 'success'){
                throw new \Exception("Error");
            }

            return response()->json($response->json());

        } catch (\Exception $e) {
            return response()->json(['message'=>$e->getMessage()],422);
        }
    }

    private function generateUrl($data)
    {
        return $this->url.$data['controller'].'::'.$data['method'];
    }
}
