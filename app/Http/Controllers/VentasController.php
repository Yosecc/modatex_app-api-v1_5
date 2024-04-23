<?php

namespace App\Http\Controllers;

use Auth;
use App\Models\Store;
use App\Models\Ventas;
use App\Models\BillingInfo;
use App\Models\ClientLocal;
use Illuminate\Http\Request;
use App\Models\ProductsDetail;
use Illuminate\Http\Client\Pool;
use App\Http\Traits\StoreTraits; 
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class VentasController extends Controller
{

    use StoreTraits;
    private $token;
    private  $url = 'https://www.modatex.com.ar/?c=Profile::purchases&';

    public function index(Request $request)
    {
      $page = isset($request->page) ? $request->page : 1;

      $url = $this->url.'page='.$page;

      if(isset($request->id)){
        $url = $this->url.'id='.$request->id;
      }

      $this->token = Auth::user()->api_token;
        // dd($this->token);
        $response = Http::withHeaders([
        'x-api-key' => $this->token,
      ])
      // ->asForm()
      ->acceptJson()
      ->post($url,[ ]);
      
      // dd($response->json());
        
      $pedidos = $response->collect();


      if(!$pedidos->count()){
        return response()->json(['page' => $page, 'orders' => [] ]); 
      }
      if(!$pedidos['data']['length']){
        return response()->json(['page' => $page, 'orders' => [] ]); 

      }
      $orders = collect($pedidos['data']['orders']);
      $stores = new StoresController();
      $marcas = $stores->consultaStoresRosa([
        'in' => $orders->pluck('store_id')->unique()
      ]);
        
      $f = $orders->sortByDesc('date')->groupBy('date')->map(function($g, $k) use($marcas, $orders){
        return [
          'date' => $k,
          'data' => $g->map(function($pedido) use ($marcas,$orders){
            
            // dd($pedido['details']);
            $pedido['details'] = collect($pedido['details'])
            ->groupBy('model_id')
            ->map(function($grupo){

              // dd($grupo);
              return [
                'count' => $grupo->count(),
                'name' => isset($grupo[0]['name']) ? $grupo[0]['name'] : $grupo[0]['code'],
                'image' => isset($grupo[0]) && isset($grupo[0]['images'][0]) ? $grupo[0]['images'][0] : '',
                'amount' => $grupo->sum('amount'),
                'data' => $grupo,
              ];
            });

            // dd($pedido['details']);

            $pedido['estado_calculado'] = $this->getEstadoCalculado($pedido);
           
            $pedido['store_brand'] = env('URL_IMAGE').'/common/img/logo/'. $pedido['store_brand'];
            return $pedido;
          })
        ];
      });

      $order = null;
      if(isset($request->id)){
        if($f->first()){
          $order = $f->first();
          if($order && isset($order['data']) && count($order['data'])){
            $order = $order['data'][0];
          }
        }
      }
      
      return response()->json([
        'page' => $page,
        'order' => isset($request->id) ? $order : null,
        'orders' => !isset($request->id) ? $f->values()->toArray():null,  
        'billing' => $pedidos['data']['billing'] 
      ]);
      // dd();
    }

    private $status_steps = [
          'unknown' 		 	   => ['descrip' => 'Desconocido', 'color' => 'gray'],
          'initiated' 		 	 => ['descrip' => 'Ingresado', 'color' => 'green'],
          'verified' 			 	 => ['descrip' => 'Verificado', 'color' => 'green'],
          'payment_pending'  => ['descrip' => 'Pago pendiente', 'color' => 'orange'],
          'payment_received' => ['descrip' => 'Pago recibido', 'color' => 'orange'],
          'sent' 					   => ['descrip' => 'Enviado', 'color' => 'orange'],
          'closed' 		 		   => ['descrip' => 'Finalizado', 'color' => 'green'],
          'not_rated' 		 	 => ['descrip' => 'Sin calificar', 'color' => 'orange'],
          'rated' 			 	   => ['descrip' => 'Calificado', 'color' => 'green'],
          'canceled_by_store'=> ['descrip' => 'Cancelado', 'color' => 'red'],
          'canceled_by_customer'=> ['descrip' => 'Cancelado', 'color' => 'red']
		    ];
    
    private $envios = [
      'RD'    => ['descrip' => 'Pick up by depot'],
      'CA'    => ['descrip' => 'Correo Argentino a'],
      'OCA'   => ['descrip' => 'Oca a'],
      'IP'    => ['descrip' => 'Integral Pack'],
      'OTHER' => ['descrip' => 'transporte tadicional de la empresa '],
      'MOTO'  => ['descrip' => 'Moto'],
    ];

    private $estado = [
      'name' => '',
      'key' => '',
      'textos' => [],
      'color' => ''
    ];

    private function completeNameEnvios($pedido)
    { 
      $type = $pedido['deliv_price_data']['type'];

      if(in_array($type, ['CA','OCA'])){
        $this->envios[$type]['descrip'] = $this->envios[$type]['descrip'].' '.$pedido['deliv_price_data']['service_type'];
      }
      if(in_array($type, ['OTHER'])){
        $this->envios[$type]['descrip'] = $this->envios[$type]['descrip'].' '.$pedido['deliv_price_data']['service_name'];
      }

      return  $this->envios[$type]['descrip'];

    }

    private function getEstadoCalculado($pedido)
    {
      
      // dd($this->estado, $pedido['status_map']);

      $status = $pedido['status_map'];
      // $this->estado['key'] = $status['key'];
      
      // $this->estado['name'] = $status['name'];
      // $this->estado['color'] = $this->status_steps[$this->estado['key']]['color'];

      $status = collect($status['steps'])->where('active', true)->first();
      // dd($this->estado, $status);

      $this->estado['name'] = $status['title'];
      $this->estado['color'] = $status['title_col'];


      $this->estado['textos'] = collect($status['message_parsed'])
                                ->map(function($texto){
                                  if($texto['type'] == 'button'){
                                    $texto['redirect'] =  [ 'route' => 'link', 'params' => $texto['route'], 'beforeConfirm' => true ];
                                  }
                                  return $texto;
                                });


      

      // if($this->estado['key'] == 'unknown'){
      //   $this->estado['name'] = 'Desconocido';
      //   $this->estado['textos'] = [
      //     [
      //       'type' => 'text',
      //       'text' => 'El estado de la compra es desconocido.'
      //     ],
      //   ];
      // }
      // else if( in_array($this->estado['key'], ['canceled_by_customer'] )) {
      //   $this->estado['textos'] = [
      //     [
      //       'type' => 'text',
      //       'text' => 'La compra fue cancelada.'
      //     ],
      //   ];

      // }
      // else if( in_array($this->estado['key'], ['canceled_by_store'] )) {
      //   $this->estado['textos'] = [
      //     [
      //       'type' => 'text',
      //       'text' => 'La tienda canceló la compra.'
      //     ],
      //   ];
      // }
      // else {
        
      //   if(in_array($this->estado['key'], ['initiated','verified'])){
      //     $this->estado['textos'] = [
      //       [
      //         'type' => 'text',
      //         'text' => 'Tu compra ha sido confirmada por la tienda.',
      //       ],
      //       [
      //         'type' => 'text',
      //         'text' => 'Te enviamos un mail que tiene el link que te permitirá realizar el pago.',
      //       ],
      //       [
      //         'type' => 'text',
      //         'text' => 'Si todavía no te llegó, esperá unos minutos hasta que llegue o buscalo en correo no deseado.',
      //         'fontSize' => 12
      //       ],
      //     ];
      //   }

      //   if(in_array($this->estado['key'], ['payment_pending'])){
      //     $this->estado['textos'] = [
      //       [
      //         'type' => 'text',
      //         'text' => 'La compra está lista para ser abonada.',
      //       ],
      //     ];
      //     if ($pedido['payment_type'] != 'B') {
      //       if (empty($pedido['modapago_link'])) {
      //         $this->estado['textos'][] = [
      //           'type' => 'text',
      //           'text' => 'Esperá el link que te permitirá realizar el pago.',
      //         ];
      //       } else {
      //         $this->estado['textos'][] = [
      //           'type' => 'button',
      //           'text' => 'Podés hacer el pago haciendo click aquí',
      //           'redirect' => [
      //             'route' => 'link',
      //             'params' => $pedido['modapago_link']
      //           ]
      //         ];
      //       }
      //     } else {
      //       $this->estado['textos'][] = [
      //         'type' => 'text',
      //         'text' => 'La tienda hará el envío una vez realizado el depósito o transferencia bancaria.',
      //       ];
      //       $this->estado['textos'][] = [
      //         'type' => 'text',
      //         'text' => 'Los datos bancarios fueron enviados a tu casilla de correo electrónico.',
      //       ];
      //       $this->estado['textos'][] = [
      //         'type' => 'button',
      //         'text' => 'Hacé click aquí para notificar el pago.',
      //         'redirect' => [
      //           'route' => 'link',
      //           'params' => 'https://www.modatex.com.ar/perfil?comprobante_deposito='.$pedido['id']
      //         ]
      //       ];
      //     }
      //   }

      //   if(in_array($this->estado['key'], ['payment_received'])){
      //     $this->estado['textos'] = [
      //       [
      //         'type' => 'text',
      //         'text' => 'La tienda recibió el pago.',
      //       ],
      //       [
      //         'type' => 'text',
      //         'text' => 'Tu compra está siendo preparada para ser enviada.',
      //       ],
      //     ];
         
      //   }

      //   if(in_array($this->estado['key'], ['sent','closed'])){
      //     if($pedido['deliv_status'] == 2){
      //       $this->estado['name'] = 'En depósito';
      //       $this->estado['textos'] = [
      //         [
      //           'type' => 'text',
      //           'text' => 'El paquete llegó a nuestro depósito.',
      //         ],
      //       ];

      //     } else if ($pedido['deliv_status'] == 11){
      //       $this->estado['name'] = 'Entregado';
      //       $this->estado['textos'] = [
      //         [
      //           'type' => 'text',
      //           'text' => "Lo retiró {$pedido['first_name']} {$pedido['last_name']}",
      //         ],
      //       ];
      //     } else if(in_array( $pedido['deliv_status'] , [3,4,7,8,9,10] )){
            
      //       $envio_name = $this->completeNameEnvios($pedido);

      //       $this->estado['textos'] = [
      //         [
      //           'type' => 'text',
      //           'text' => "Salió el {$pedido['deliv_update_date_beautified']}."
      //         ],
      //         [
      //           'type' => 'text',
      //           'text' => "Paquete enviado por {$envio_name}."
      //         ],
      //       ];

      //       if($pedido['deliv_price_data']['type'] == 'CA'){
      //         if( !empty( $pedido['deliv_reference'] ) ) {
                
      //           $this->estado['textos'][] = [
      //             'type' => 'text',
      //             'text' => "Podés hacerle el seguimiento a tu paquete con este número de guía:"
      //           ];
      //           $this->estado['textos'][] = [
      //             'type' => 'text',
      //             'text' => $pedido['deliv_reference'],
      //             'fontSize' => 18
      //           ];
      //           $this->estado['textos'][] = [
      //             'type' => 'button',
      //             'text' => 'Haciendo click aquí en Seguimientos de Envíos.',
      //             'redirect' => [
      //               'route' => 'link',
      //               'params' => 'https://www.correoargentino.com.ar/formularios/ondnc'
      //             ]
      //           ];
              
      //         }else{
      //           $this->estado['textos'][] = [
      //             'type' => 'text',
      //             'text' => "Podés hacerle el seguimiento a tu paquete con este número de guía SD/CP {$pedido['deliv_reference_id']}",
      //             'fontSize' => 18
      //           ];
      //         }
      //       }
      //       else if($pedido['deliv_price_data']['type'] == 'OCA'){
                          
      //         $this->estado['textos'][] = [
      //           'type' => 'text',
      //           'text' => "Podés hacerle el seguimiento a tu paquete con este número de guía",
      //         ];

      //         $this->estado['textos'][] = [
      //           'type' => 'button',
      //           'text' => $pedido['deliv_reference_id'],
      //           'redirect' => [
      //             'route' => 'link',
      //             'params' => "http://www5.oca.com.ar/ocaepakNet/Views/ConsultaTracking/TrackingConsult.aspx?numberTracking={$pedido['deliv_reference_id']}"
      //           ]
      //         ];

      //       }
      //       else if($pedido['deliv_price_data']['type'] == 'IP'){
              
      //         $this->estado['textos'][] = [
      //           'type' => 'text',
      //           'text' => "Podés hacerle el seguimiento a tu paquete con este número de guía",
      //           'fontSize' => 18
      //         ];

      //         $this->estado['textos'][] = [
      //           'type' => 'button',
      //           'text' => $pedido['deliv_reference_id']."-001",
      //           'redirect' => [
      //             'route' => 'link',
      //             'params' => "https://trackingonline.integralexpress.com/tracking_corpo.php?cod=8693&valor={$pedido['id']}-001"
      //           ]
      //         ];
      //       }
      //       else if($pedido['deliv_price_data']['type'] == 'OTHER' && !empty( $pedido['shipping_data']['receipt'] )){
              
      //         $this->estado['textos'][] = [
      //           'type' => 'text',
      //           'text' => "Para descargar el remito del transporte hacé click",
      //         ];

      //         $this->estado['textos'][] = [
      //           'type' => 'button',
      //           'text' => 'Descargar el remito',
      //           'redirect' => [
      //             'route' => 'link',
      //             'params' => "https://www.modatex.com.ar/common/descargarFile.php?file={$pedido['shipping_data']['receipt']}"
      //           ]
      //         ];
      //       }else{
      //         $this->estado['textos'][] = [
      //           'type' => 'text',
      //           'text' => "Tu número de guía es {$pedido['deliv_reference_id']}",
      //         ];
      //       }
            
      //       if( $pedido['deliv_price_data']['service_type'] == 'sucursal' ) { 
      //         if(isset($pedido['shipping_data'])){

      //           $this->estado['textos'][] = [
      //             'type' => 'text',
      //             'text' => "Lo retira {$pedido['shipping_data']['first_name']} {$pedido['shipping_data']['last_name']}",
      //           ];
      //         }
      //       }
      //     }else if( $pedido['deliv_status'] < 2 ){
      //       $this->estado['name'] = $this->status_steps[$this->estado['key']]['descrip'];
      //     }else{
      //       $this->estado['name'] = 'Desconocido';
      //       $this->estado['textos'] = [
      //         [
      //           'type' => 'text',
      //           'text' => "El estado del envío es desconocido."
      //         ],
              
      //       ];
      //     }
      //   }

      // }

      return $this->estado;
    }

    private function getDeliveryPriceIn($ventas_ids){

      $consultas = Http::pool(fn (Pool $pool) => 
        $ventas_ids->map(fn ($id) => 
          $pool->as($id)->asForm()->post($this->url, [
            'menu' => 'prev_venta_new',
            'venta_num' => $id,
          ])
        )
      );
      
      return $consultas;

    }

    private function getDeliveryPrice($venta)
    {

      $response = Http::asForm()->post($this->url, [
          'menu' => 'prev_venta_new',
          'venta_num' => $venta['num'],
      ]);

      return $response->collect()->all();
  }
}
