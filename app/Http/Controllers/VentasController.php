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
// dd($url);
      $this->token = Auth::user()->api_token;
      $response = Http::withHeaders([
        'x-api-key' => $this->token,
      ])
      // ->asForm()
      ->acceptJson()
      ->post($url,[ ]);
      
      // dd($response->body());
      $pedidos = $response->collect();
      

      if(!$pedidos->count()){
        return response()->json(['page' => $page, 'orders' => [], 'billing' => null, 'order' => null ]); 
      }
      // dd($pedidos,$pedidos['data']['length'],$pedidos->count());
      if(!$pedidos['data']['length']){
        return response()->json(['page' => $page, 'orders' => [], 'order' => null,'billing' => $pedidos['data']['billing'] ]); 

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
           
            if(isset($pedido['store_brand'])){
              $pedido['store_brand'] = env('URL_IMAGE').'/common/img/logo/'. $pedido['store_brand'];
            }else{
              $pedido['store_brand'] = '';
            }
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


      if(isset($status['message_parsed'])){
       
      
      $this->estado['textos'] = collect($status['message_parsed'])
                                ->map(function($texto){
                                  if($texto['type'] == 'button'){
                                    $texto['redirect'] =  [ 'route' => 'link', 'params' => $texto['route'], 'beforeConfirm' => true ];
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
