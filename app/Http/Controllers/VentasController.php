<?php

namespace App\Http\Controllers;

use App\Models\BillingInfo;
use App\Models\ClientLocal;
use App\Models\ProductsDetail;
use App\Models\Store;
use App\Models\Ventas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\Pool;
use App\Http\Traits\StoreTraits; 
use Auth;
use Illuminate\Support\Facades\Http;

class VentasController extends Controller
{

    use StoreTraits;
    private $token;
    private  $url = 'https://www.modatex.com.ar/?c=Profile::purchases';

    public function index()
    {
      $this->token = Auth::user()->api_token;
      
        $response = Http::withHeaders([
        'x-api-key' => $this->token,
      ])
      // ->asForm()
      ->acceptJson()
      ->post($this->url);
    }
    /**
     * DEPRECADO
     */
    // public function index()
    // {

    //   $ventas = Ventas::where('CLIENT_NUM',Auth::user()->num)->latest()->paginate(6);
    //   $ventas_ids = $ventas->pluck('num');
    //   $local_cds = $ventas->pluck('local_cd')->unique();

    //   $detailsProductosIDs = $ventas->pluck('detail')->collapse()->pluck('shop_modelo_num')->unique();

    //   $deliveryPrices = $this->getDeliveryPriceIn($ventas_ids);

    //   $stores = Store::whereIn('LOCAL_CD', $local_cds)->get();

    //   $products = new ProductsController();
    //   $productos = collect($products->whereInProducts($detailsProductosIDs, [ 'isModels'=> false ]));

    //   $ventas = $ventas->map(function ($venta) use ($deliveryPrices, $stores, $productos) {
    //     if(isset($deliveryPrices[$venta['num']])){
    //       $venta['delivery_price'] = $deliveryPrices[$venta['num']]->json();
    //     }

    //     $venta['store'] = $this->dataArrangement($stores->where('GROUP_CD', $venta['group_cd'])->where('LOCAL_CD',$venta['local_cd'])->first());

    //     $products = [];

    //     foreach ($venta['detail'] as $key => $detalle) {
    //       $p = $productos->where('id',$detalle['shop_modelo_num'])->first();
    //       if($p){
    //         $products[] = $p;
    //       }
    //     }

    //     $venta['productos'] = $products;
        
    //     return $venta;
    //   });
      
    //   return response()->json($ventas);
    // }

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
