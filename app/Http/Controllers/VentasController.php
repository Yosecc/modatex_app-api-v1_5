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
class VentasController extends Controller
{

    private  $url = 'https://www.modatex.com.ar/main/home_db.php';

    public function index()
    {

    
        // ProductsDetail::where('MODA_NUM',1001072796)->get()->dd();
        // DB::table('MODELO_DETALE')->where('MODA_NUM',1001072796)->get()->dd();
        // dd(BillingInfo::where('CLIENT_NUM',1026071)->get());
        $ventas = Ventas::where('CLIENT_NUM',1026071)->latest()->get();

        $arreglo = function($venta){

            $venta->delivery_price = $this->getDeliveryPrice($venta);
            $venta->store = Store::GetStoreCD(['group_cd'=>$venta->group_cd, 'local_cd' => $venta->local_cd]);

            $productosIDS = [];

            foreach ($venta->detail as $key => $detail) {
              if( !in_array($detail['shop_modelo_num'],$productosIDS) ){
                $productosIDS[] = $detail['shop_modelo_num'];
              }
            }
            
            $productos = [];
            foreach ($productosIDS as $p => $id) {
              $product = new ProductsController();
              $product = $product->oneProduct($id);

              if(count($product)){
                $product = $product[0];
                $product['detalles'] = [];
              }
              
              foreach ($venta->detail as $d => $detail) {
                if($detail['shop_modelo_num'] == $id){
                  $product['detalles'][] = $detail;
                }
              }
              
              $productos[] = $product;
            }
            
            $venta->productos = $productos;

            return $venta;
        };

        $ventas = array_map($arreglo, collect($ventas)->all());

        return response()->json($ventas);
    }

    private function getDeliveryPrice($venta)
    {

        $response = Http::asForm()->post($this->url, [
            'menu' => 'prev_venta_new',
            'venta_num' => $venta->num,
        ]);

        return $response->collect()->all();
    }
}
