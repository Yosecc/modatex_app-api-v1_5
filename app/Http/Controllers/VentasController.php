<?php

namespace App\Http\Controllers;

use App\Http\Controllers\CheckoutController;
use App\Http\Traits\StoreTraits;
use App\Models\BillingInfo;
use App\Models\ClientLocal;
use App\Models\ProductsDetail;
use App\Models\Store;
use App\Models\Ventas;
use Auth;
use Carbon\Carbon;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class VentasController extends Controller
{

    use StoreTraits;
    private $token;
    private  $url = 'https://www.modatex.com.ar/?c=Profile::purchases&';

    public function index(Request $request)
    {
        //recibe la pagina
        $page = isset($request->page) ? $request->page : 1;

        //agrega la pagina a la url
        $url = $this->url.'page='.$page;

        //si recibe un id, agrega el id a la url
        if(isset($request->id)){
            $url = $this->url.'id='.$request->id;
        }

        //obtiene el token del usuario logueado
        $this->token = Auth::user()->api_token;

        //hace la consulta a modatex
        $response = Http::withHeaders([
            'x-api-key' => $this->token,
        ])
        ->acceptJson()
        ->post($url,[ ]);

        // recupera los pedidos
        $pedidos = $response->collect();

        if(!$pedidos->count()){
            return response()->json(['page' => $page, 'orders' => [], 'billing' => null, 'order' => null ]);
        }

        if(!$pedidos['data']['length']){
            return response()->json(['page' => $page, 'orders' => [], 'order' => null,'billing' => $pedidos['data']['billing'] ]);
        }

        //recupera los pedidos
        $orders = collect($pedidos['data']['orders']);

        // Separa las los ids de las marcas y realiza las consultas de las marcas
        $stores = new StoresController();
        $marcas = $stores->consultaStoresRosa([
            'in' => $orders->pluck('store_id')->unique()
        ]);

        Carbon::setLocale('es');
        //agrupa los pedidos por fecha
        $f = $orders->sortByDesc('date')->groupBy('date')->map(function($g, $k) use($marcas, $orders){
            return [
                'date' => $k,
                'beautifiedDate' => Carbon::parse($k)->isoFormat('LL'),
                'data' => $g->map(function($pedido) use ($marcas,$orders){

                    // mapea los productos del pedido
                    $pedido['details'] = collect($pedido['details'])
                    ->groupBy('model_id')
                    ->map(function($grupo){
                        return [
                            'count' => $grupo->count(),
                            'name' => isset($grupo[0]['name']) ? $grupo[0]['name'] : $grupo[0]['code'],
                            'image' => isset($grupo[0]) && isset($grupo[0]['images'][0]) ? $grupo[0]['images'][0] : '',
                            'amount' => $grupo->sum('amount'),
                            'data' => $grupo,
                        ];
                    });

                    // calcula y agrega el estado del pedido
                    $pedido['estado_calculado'] = $this->getEstadoCalculado($pedido);

                    // recupera el logo
                    if(isset($pedido['store_brand'])){
                        $pedido['store_brand'] = env('URL_IMAGE').'/common/img/logo/'. $pedido['store_brand'];
                    }else{
                        $pedido['store_brand'] = '';
                    }

                    // calcula el metodo de pago
                    $metedodoPedido = new CheckoutController();
                    $pedido['payment_type_detail'] = $metedodoPedido->getMetodoPagoLocal($pedido['payment_type']);

                    // dd($this->completeNameEnvios($pedido));
                    $pedido['deliv_price_data']['descrip'] = $this->completeNameEnvios($pedido)['descrip'];
                    $pedido['deliv_price_data']['icon'] = $this->completeNameEnvios($pedido)['icon'];


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
          'unknown' 		 	    => ['descrip' => 'Desconocido', 'color' => 'gray'],
          'initiated' 		 	    => ['descrip' => 'Ingresado', 'color' => 'green'],
          'verified' 			    => ['descrip' => 'Verificado', 'color' => 'green'],
          'payment_pending'         => ['descrip' => 'Pago pendiente', 'color' => 'orange'],
          'payment_received'        => ['descrip' => 'Pago recibido', 'color' => 'orange'],
          'sent' 					=> ['descrip' => 'Enviado', 'color' => 'orange'],
          'closed' 		 		    => ['descrip' => 'Finalizado', 'color' => 'green'],
          'not_rated' 		 	    => ['descrip' => 'Sin calificar', 'color' => 'orange'],
          'rated' 			 	    => ['descrip' => 'Calificado', 'color' => 'green'],
          'canceled_by_store'       => ['descrip' => 'Cancelado', 'color' => 'red'],
          'canceled_by_customer'    => ['descrip' => 'Cancelado', 'color' => 'red']
    ];

    private $envios = [
      'RD'    => [ 'icon' => null, 'descrip' => 'Pick up by depot'],
      'CA'    => [ 'icon' => "https:\/\/www.modatex.com.ar\/modatexrosa3\/img\/logo-sm-ca.png", 'descrip' => 'Correo Argentino a'],
      'OCA'   => [ 'icon' => "https:\/\/www.modatex.com.ar\/modatexrosa3\/img\/logo-sm-oca.png", 'descrip' => 'Oca a'],
      'IP'    => [ 'icon' => null, 'descrip' => 'Integral Pack'],
      'OTHER' => [ 'icon' => null, 'descrip' => 'transporte tadicional de la empresa '],
      'MOTO'  => [ 'icon' => null, 'descrip' => 'Moto'],
      'CHAZKI'=> [ 'icon' => null, 'descrip' => 'Moto Express'],
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

      if(in_array($type, ['CHAZKI'])){
        $this->envios[$type]['descrip'] = $this->envios[$type]['descrip'];
        $this->envios[$type]['icon'] = 'https://www.modatex.com.ar/modatexrosa3/img/logo-sm-chazki.png';
      }

      return  $this->envios[$type];
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


      if(isset($status['message_parsed'])){


      $this->estado['textos'] = collect($status['message_parsed'])
                                ->map(function($texto) use ($pedido){
                                  if($texto['type'] == 'button'){
                                    $isWebview = false;
                                    if($texto['text'] == ' Realizar pago' || $texto['text'] == 'Realizar pago'){
                                      $isWebview = true;
                                    }
                                    if($texto['text'] == ' Subir comprobante de dep�sito o transferencia' || $texto['text'] == 'Subir comprobante de dep�sito o transferencia'){
                                      $isWebview = true;
                                    }

                                    $isWebview = true;

                                    $texto['redirect'] =  [ 'route' => 'link', 'params' => $texto['route'], 'extra_parmas' => $isWebview ? [ 'purchase_id' => $pedido['id'] ] : [] , 'beforeConfirm' => true, 'isWebview' => $isWebview ];
                                  }
                                  return $texto;
                                });
                              }




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
