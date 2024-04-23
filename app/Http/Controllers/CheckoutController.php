<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Objects\NotificationsPush;
use App\Models\Client;
use Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class CheckoutController extends Controller
{
    private $url = 'https://www.modatex.com.ar/?c=';
    private $token;

    private $metdosPagos = [
            [
                'id'=> 1,
                'name'=> 'Tarjeta de Crédito / Débito',
                'modapago'=> true,
                'descripcion'=> 'Selecciona esta opción si deseas abonar con tarjeta de crédito. Fácil, seguro y rápido.',
                'logos'=> ['~/assets/visa.jpg', '~/assets/master.jpg', '~/assets/american.jpg', '~/assets/cencosud.jpg', '~/assets/argencard.jpg'],
                'active'=> false,
                'detalle'=> 'Luego de recibir este pedido, la tienda te enviará por mail dentro de las 48 hs, el cupón para pagar esta compra con tu tarjeta.',
                'method'=> 'card'
            ],
            [
                'id' => 2,
                'name' => 'Efectivo',
                'modapago' => true,
                'descripcion' => 'Si quieres obtener un cupón de pago para abonar en efectivo, selecciona esta opción.',
                'logos' => ['~/assets/pagofacil.jpg', '~/assets/rapipagos.jpg'],
                'active' => false,
                'detalle' => 'Luego de recibir este pedido, la tienda te enviará por mail dentro de las 48 hs, el cupón para pagar esta compra en los puntos de Pago Fácil, Rapipagos o Cobro Express.',
                'method' => 'cash'
            ],
            [
                'id' =>  3,
                'name' =>  'Transferencia o depósito bancario',
                'modapago' =>  false,
                'descripcion' =>  'Seleccionando aquí, podrás realizar una transferencia o depósito bancario.',
                'logos' =>  ['~/assets/santanderrio.png', '~/assets/bancocomafi.jpg'],
                'active' =>  false,
                'detalle' =>  'Luego de recibir este pedido, la tienda te enviará por mail dentro de las 48 hs, los datos bancarios.',
                'method' =>  'bank'
            ],
        ];

    private $envios = [
            'post_office'=>[
                'id'           =>  1,
                'title'        => 'Envío a sucursal',
                'active'       => false,
                'color'        => '#239B56',
                'icon'         => 'res://sucursal',
                'agregados'    => [],
                'method'       => 'post_office',
                'isFree'       => false,
                'active'     => false
            ],
            'home_delivery'=>[
                'id'           => 2,
                'title'        => 'Envío a domicilio',
                'active'       => false,
                'color'        => '#CA6F1E',
                'icon'         => 'res://enviocasa',
                'agregados'    => [],
                'method'       => 'home_delivery',
                'isFree'       => false,
                'active'     => false
            ],
            'transport'=>[
                'id'           => 3,
                'title'        =>'Transporte tradicional',
                'active'       => false,
                'color'        => '#1976D2',
                'icon'         => 'res://envio',
                'agregados'    => [],
                'method'       => 'transport',
                'isFree'       => false,
                'active'     => false
            ],
            'integral_pack'=>[
                'id'           => 4,
                'title'        =>'INTEGRALPACK',
                'active'       => false,
                'color'        => '#CDDC39',
                'icon'         => 'res://integralpack',
                'agregados'    => [],
                'method'       => 'integral_pack',
                'isFree'       => false,
                'active'     => false
            ],
            'store_pickup'=>[
                'id'           => 5,
                'title'        =>'Retiro por depósito',
                'active'       => false,
                'color'        => '#5E35B1',
                'icon'         => 'res://enviostore',
                'agregados'    => [],
                'method'       => 'store_pickup',
                'isFree'       => false,
                'active'     => false
            ]];

    
    public function getEnvios(Request $request)
    {
       
       try {   
            $this->token = Auth::user()->api_token;

             $response = Http::withHeaders([
              'x-api-key' => $this->token,
              'x-api-device' => 'APP'
            ])
            ->asForm()
            ->post($this->generateUrl(['controller' => 'Checkout','method' => 'shipping_prices']), 
                $request->all());

            // dd($response->json());
            if($response->json()['status'] != 'success'){
                throw new \Exception("No se encontraron resultados");
            }

            // dd($this->generateUrl(['controller' => 'Checkout','method' => 'shipping_prices']), $response->json());

            $reques = Request::create('/dummy', 'GET', ['local_cd' => $request->local_cd]);
            $d = $this->getMetodos($reques);
            $metodos = json_decode($d->getContent(),true);
            
            $metodosAll = collect($metodos['data']);

            $envios = $this->envios;
            $datos = [];
            $shippingPricesData = $response->json()['data'];

            // dd($metodosAll,$shippingPricesData );
            
            foreach ( $metodosAll as $key => $envio) {
                
                $data = $envios[$key];

                $data['isFree'] = isset($shippingPricesData[$key]) ? $shippingPricesData[$key]['is_free'] : $data['isFree'];

                $precios = [];
                if(isset($shippingPricesData[$key]['extra_charges']) ){

                    $precios[] =
                        [
                            'value' => intval($shippingPricesData[$key]['extra_charges']['cost']),
                            'concepto' => $shippingPricesData[$key]['extra_charges']['descrip']
                        ];
                }

                if(isset($shippingPricesData[$key]['curr'])){
                    $precios[] = [
                        'value' => intval($shippingPricesData[$key]['curr']),
                        'concepto' =>  'Envío'
                    ];
                }

                $data['agregados'] = $precios;
                $data['body'] = collect($envio['descrip_parsed']);

                $texts = [];

                // 
                if(isset($envio['price']['is_free']) && $envio['price']['is_free']){

                    $precios = [];

                    if(isset($envio['price']['curr_ca'])){
                        $texts[] = [
                            'type' => 'text',
                            'text' => 'undefined',
                            'children' => [
                                [
                                    'type' => 'text',
                                    'text' => '$'.$envio['price']['curr_ca'],
                                    'textDecoration' => 'line-through',
                                    'color' => '#f44336',
                                    'fontWeight' => '600'
                                ],
                                [
                                    'type' => 'text',
                                    'text' => 'Sin costo de envío',
                                    'backgroundColor' => '#4caf50',
                                    'color' => 'white',
                                    'padding' => '5 12',
                                    'borderRadius' => 12,
                                    'fontWeight' => '600'
                                ],
                                [
                                    'type' => 'text',
                                    'text' => ' a todo el país',
                                    'fontWeight' => '600'
                                ],
                            ]
                        ];
                    }

                    if(isset($envio['price']['curr_caba'])){
                        $texts[] = [
                            'type' => 'text',
                            'text' => 'undefined',
                            'children' => [
                                [
                                    'type' => 'text',
                                    'text' => '$'.$envio['price']['curr_caba'],
                                    'textDecoration' => 'line-through',
                                    'color' => '#f44336',
                                    'fontWeight' => '600'
                                ],
                                [
                                    'type' => 'text',
                                    'text' => 'Sin costo de envío',
                                    'backgroundColor' => '#4caf50',
                                    'color' => 'white',
                                    'padding' => '5 12',
                                    'borderRadius' => 12,
                                    'fontWeight' => '600'
                                ],
                                [
                                    'type' => 'text',
                                    'text' => ' en CABA',
                                    'fontWeight' => '600'
                                ],
                            ]
                        ];
                    }

                    if(isset($envio['price']['curr_gba'])){
                        $texts[] = [
                            'type' => 'text',
                            'text' => 'undefined',
                            'children' => [
                                [
                                    'type' => 'text',
                                    'text' => '$'.$envio['price']['curr_gba'],
                                    'textDecoration' => 'line-through',
                                    'color' => '#f44336',
                                    'fontWeight' => '600'
                                ],
                                [
                                    'type' => 'text',
                                    'text' => 'Sin costo de envío',
                                    'backgroundColor' => '#4caf50',
                                    'color' => 'white',
                                    'padding' => '5 12',
                                    'borderRadius' => 12,
                                    'fontWeight' => '600'
                                ],
                                [
                                    'type' => 'text',
                                    'text' => ' en GBA',
                                    'fontWeight' => '600'
                                ],
                            ]
                        ];
                    }

                    if(isset($envio['price']['curr_other'])){
                        $texts[] = [
                            'type' => 'text',
                            'text' => 'Desde $'.$envio['price']['curr_other'].' al resto del país',
                            'fontWeight' => '600'
                        ];
                    }

                    if(isset($envio['price']['curr'])){
                        $texts[] = [
                            'type' => 'text',
                            'text' => 'undefined',
                            'children' => [
                                [
                                    'type' => 'text',
                                    'text' => '$'.$envio['price']['curr'],
                                    'textDecoration' => 'line-through',
                                    'color' => '#f44336',
                                    'fontWeight' => '600'
                                ],
                                [
                                    'type' => 'text',
                                    'text' => 'Sin costo de traslado',
                                    'backgroundColor' => '#4caf50',
                                    'color' => 'white',
                                    'padding' => '5 12',
                                    'borderRadius' => 12,
                                    'fontWeight' => '600'
                                ],
                            ]
                        ];
                    }   
                }else{
                    // dd($envio['price']);
                    if(isset($envio['price']['curr_ca'])){
                        $texts[] = [
                            'type' => 'text',
                            'text' => 'undefined',
                            'children' => [
                                [
                                    'type' => 'image',
                                    'src' => '~/assets/icons/ca_logo.png',
                                    'width' => 40,
                                    'height' => 'auto',
                                ],
                                [
                                    'type' => 'text',
                                    'text' => '$'.$envio['price']['curr_ca'],
                                    'backgroundColor' => '#ff5722',
                                    'color' => 'white',
                                    'padding' => '5 12',
                                    'borderRadius' => 12,
                                    'fontWeight' => '600'
                                ],
                                [
                                    'type' => 'text',
                                    'text' => ' a todo el país',
                                    'fontWeight' => '600'
                                ],
                            ]
                        ];
                    }
                    if(isset($envio['price']['curr_oca'])){
                        $texts[] = [
                            'type' => 'text',
                            'text' => 'undefined',
                            'children' => [
                                [
                                    'type' => 'image',
                                    'src' => '~/assets/icons/oca_logo.png',
                                    'width' => 40,
                                    'height' => 'auto',
                                ],
                                [
                                    'type' => 'text',
                                    'text' => '$'.$envio['price']['curr_oca'],
                                    'backgroundColor' => '#ff5722',
                                    'color' => 'white',
                                    'padding' => '5 12',
                                    'borderRadius' => 12,
                                    'fontWeight' => '600'
                                ],
                                [
                                    'type' => 'text',
                                    'text' => ' a todo el país',
                                    'fontWeight' => '600'
                                ],
                            ]
                        ];
                    }
                    if(isset($envio['price']['curr_caba'])){
                        $texts[] = [
                            'type' => 'text',
                            'text' => 'undefined',
                            'children' => [
                                [
                                    'type' => 'text',
                                    'text' => '$'.$envio['price']['curr_caba'],
                                    'backgroundColor' => '#ff5722',
                                    'color' => 'white',
                                    'padding' => '5 12',
                                    'borderRadius' => 12,
                                    'fontWeight' => '600'
                                ],
                                [
                                    'type' => 'text',
                                    'text' => ' en CABA',
                                    'fontWeight' => '600'
                                ],
                            ]
                        ];
                    }
                    if(isset($envio['price']['curr_gba'])){
                        $texts[] = [
                            'type' => 'text',
                            'text' => 'undefined',
                            'children' => [
                                [
                                    'type' => 'text',
                                    'text' => '$'.$envio['price']['curr_gba'],
                                    'backgroundColor' => '#ff5722',
                                    'color' => 'white',
                                    'padding' => '5 12',
                                    'borderRadius' => 12,
                                    'fontWeight' => '600'
                                ],
                                [
                                    'type' => 'text',
                                    'text' => ' en GBA',
                                    'fontWeight' => '600'
                                ],
                            ]
                        ];
                    }
                    if(isset($envio['price']['curr_other_oca'])){
                        $texts[] = [
                            'type' => 'text',
                            'text' => 'undefined',
                            'children' => [
                                [
                                    'type' => 'image',
                                    'src' => '~/assets/icons/oca_logo.png',
                                    'width' => 40,
                                    'height' => 'auto',
                                ],
                                [
                                    'type' => 'text',
                                    'text' => '$'.$envio['price']['curr_other_oca'],
                                    'backgroundColor' => '#ff5722',
                                    'color' => 'white',
                                    'padding' => '5 12',
                                    'borderRadius' => 12,
                                    'fontWeight' => '600'
                                ],
                                [
                                    'type' => 'text',
                                    'text' => ' al resto del país',
                                    'fontWeight' => '600'
                                ],
                            ]
                        ];
                    }
                    if(isset($envio['price']['curr_other_ca'])){
                        $texts[] = [
                            'type' => 'text',
                            'text' => 'undefined',
                            'children' => [
                                [
                                    'type' => 'image',
                                    'src' => '~/assets/icons/ca_logo.png',
                                    'width' => 40,
                                    'height' => 'auto',
                                ],
                                [
                                    'type' => 'text',
                                    'text' => '$'.$envio['price']['curr_other_ca'],
                                    'backgroundColor' => '#ff5722',
                                    'color' => 'white',
                                    'padding' => '5 12',
                                    'borderRadius' => 12,
                                    'fontWeight' => '600'
                                ],
                                [
                                    'type' => 'text',
                                    'text' => ' al resto del país',
                                    'fontWeight' => '600'
                                ],
                            ]
                        ];
                    }
                    if(isset($envio['price']['curr'])){
                        $t = '';

                        if($envio['price']['method'] == 'transport'){
                            $t = ' por costo de traslado hasta el transporte elegido.';
                        }elseif($envio['price']['method'] == 'integral_pack'){
                            $t = ' por costo de servicio.';
                        }
                        $texts[] = [
                            'type' => 'text',
                            'text' => 'undefined',
                            'children' => [
                                [
                                    'type' => 'text',
                                    'text' => '$'.$envio['price']['curr'],
                                    'backgroundColor' => '#ff5722',
                                    'color' => 'white',
                                    'padding' => '5 12',
                                    'borderRadius' => 12,
                                    'fontWeight' => '600'
                                ],
                                [
                                    'type' => 'text',
                                    'text' => $t,
                                    'fontWeight' => '600'
                                ],
                            ]
                        ];
                    }
                }

                $data['body'] = $data['body']->concat($texts);

                if(isset($envio['extra_charge'])){

                    $envio['extra_charge_parsed'] = collect($envio['extra_charge_parsed'])->reject(function ($element) {
                        return $element['text'] === '';
                    })->map(function ($element) use($envio) {
                        $element['html'] = $envio['extra_charge'];
                        return $element;
                    });

                    $data['body'] = $data['body']->concat($envio['extra_charge_parsed']);
                }

                $data['body'] = $data['body']->reject(function ($element) {
                    return $element['text'] === '';
                });

                $datos[] = $data;
            }

            $response = Http::withHeaders([
                'x-api-key' => $this->token,
                'x-api-device' => 'APP'
              ])
              ->asForm()
              ->post($this->generateUrl(['controller' => 'Checkout','method' => 'shipping_method']), 
                  $request->all());

                //   dd($response->collect());
            $seleccion = $response->collect();

            if(isset($seleccion['data'])){
                $datos = collect($datos)->map(function($dato) use ($seleccion){
                    if(isset($seleccion['data']['shipping_method'])){
                        if($dato['method'] == $seleccion['data']['shipping_method']){
                            $dato['active'] = true;
                        }
                    }
                     return $dato;
                });
            }
            
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
              'x-api-device' => 'APP'
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
              'x-api-device' => 'APP' 
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
              'x-api-device' => 'APP'
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
              'x-api-device' => 'APP'
            ])
            ->asForm()
            ->post($this->generateUrl(['controller' => 'Checkout','method' => 'shipping_edit']), 
                $request->all());

                // dd($response->body());
            $response = $response->json();
                // dd();
            if($response['status'] != 'success'){
                throw new \Exception(json_encode($response));
                // throw new \Exception($response->json());
            }

              return response()->json($response['data']);
            
        } catch (\Exception $e) {
            return response()->json($e->getMessage(),422);
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
              'x-api-device' => 'APP'
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
              'x-api-device' => 'APP'
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
              'x-api-device' => 'APP'
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
              'x-api-device' => 'APP'
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
              'x-api-device' => 'APP'
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
            // dd($this->generateUrl(['controller' => 'Checkout','method' => 'payment_select']));
            $this->token = Auth::user()->api_token;
             $response = Http::withHeaders([
              'x-api-key' => $this->token,
              'x-api-device' => 'APP'
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
              'x-api-device' => 'APP'
            ])
            ->asForm()
            ->post($this->generateUrl(['controller' => 'Checkout','method' => 'summary']), 
                $request->all());

             if($response->json()['status'] == 'error'){
                throw new \Exception(json_encode($response->json()));
            }

            if($response->json()['status'] != 'success'){
                throw new \Exception("{ message: 'No se encontraron resultados' }");
            }
            if(isset($response->json()['data'])){
              return response()->json($response->json()['data']);
            }
            return response()->json($response->json()['data']);

        } catch (\Exception $e) {
                return response()->json($e->getMessage(),422);
        }
    }

    public function confirmarCompra(Request $request)
    {
        //
        // // return $this->token;
        // return response()->json(['message'=>$this->token],422);
        try {   
            // dd($this->generateUrl(['controller' => 'Checkout','method' => 'confirm_purchase']));
            
            $data = $request->all();
            $data['device'] = 'mobile';
            $this->token = Auth::user()->api_token;
             $response = Http::withHeaders([
              'x-api-key' => $this->token,
              'x-api-device' => 'APP',
            ])
            ->asForm()
            ->post($this->generateUrl(['controller' => 'Checkout','method' => 'buy']), $data);
            
            if(isset($response->json()['status']) && $response->json()['status'] != 'success'){
                throw new \Exception("No se encontraron resultados");
            }

            if(!isset($response->json()['status'])){
                throw new \Exception($response->json());

            }
              
              try {
                if(isset($response->json()['data'])){

                //     $notification = new NotificationsPush(
                //         [
                //             'notification' => [
                //                 'title' => 'Gracias por tu compra',
                //                 "body" => 'Su compra ha sido procesada con éxito. Pronto nos comunicaremos'
                //             ],
                //             'data' => [
                //                 'redirect' => [
                //                     'route' => '/order',
                //                     'params' => [
                //                         'id' => $response->json()['data']['purchase_id']
                //                     ]
                //                 ]
                //             ]
                //     ]);
                }
                // $notification->sendUserNotification(Auth::user()->num);

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
              'x-api-device' => 'APP'
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
              'x-api-device' => 'APP'
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

    public function getMetodos(Request $request)
    {
        try {
             $localCd = $request->input('local_cd');

            $this->token = Auth::user()->api_token;
            $response = Http::withHeaders([
             'x-api-key' => $this->token,
             'x-api-device' => 'APP'
           ])
           ->asForm()
           ->post($this->generateUrl(['controller' => 'Shipping','method' => 'all_methods']).'&store_id='.$localCd);

        //    dd($response->json());
           return response()->json($response->json());
        } catch (\Throwable $th) {
            return response()->json($th->getMessage(),422);
        }
    }

    public function getMetodosPagos(Request $request)
    {
        return response()->json($this->metdosPagos);
    }

    public function shippingSelectAddress(Request $request)
    {
        try {

            $this->token = Auth::user()->api_token;
           $response = Http::withHeaders([
            'x-api-key' => $this->token,
            'x-api-device' => 'APP'
          ])
          ->asForm()
          ->post($this->generateUrl(['controller' => 'Checkout','method' => 'shipping_select_address']), $request->all());

       //    dd();
          return response()->json($response->json());
       } catch (\Throwable $th) {
           return response()->json($th->getMessage(),422);
       }
    }

    public function getHorarios()
    {
        try {

            $this->token = Auth::user()->api_token;
           $response = Http::withHeaders([
            'x-api-key' => $this->token,
            'x-api-device' => 'APP'
          ])
        //   ->asForm()
          ->post($this->generateUrl(['controller' => 'DropOffTime','method' => 'get']),[]);

            $horarios = $response->collect();
            // dd($horarios['data']);
            $horarios = collect($horarios['data'])->map(function($horario){
                return [
                    'id' => $horario['id'],
                    'name' => $horario['start']." ".$horario['end']." - ".$horario['disclaimer'],
                ];
            });
          return response()->json($horarios);
       } catch (\Throwable $th) {
           return response()->json($th->getMessage(),422);
       }
    }

    private function generateUrl($data)
    {
        return $this->url.$data['controller'].'::'.$data['method'];
    }
}
