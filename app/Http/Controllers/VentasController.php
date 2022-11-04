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
use Auth;
class VentasController extends Controller
{

    private  $url = 'https://www.modatex.com.ar/main/home_db.php';

    public function index()
    {

        // $f = Http::withHeaders([
        //   'x-api-key' => 'LcaAWMG94LZgcRM6k8VfsHQxBSPcP5PmbrYGRhv7',
        //   'Cookie' => 'PHPSESSID=9scr813epdsm392jk9k535m9p7'
        // ])
        // ->acceptJson()
        // ->get('https://www.modatex.com.ar/document/calification_ajax.php?ajax=true&page_hidden=1&jsonReturn=1&filter=');
        // dd($f->json());
        // dd($f->cookies());
        // $g = Http::withHeaders([
        //   'x-api-key' => 'LcaAWMG94LZgcRM6k8VfsHQxBSPcP5PmbrYGRhv7',
        //   'Cookie' => $f->headers()['Set-Cookie'][0]
        // ])
        // ->acceptJson()
        // ->get('https://www.modatex.com.ar/document/calification_ajax.php?ajax=true&page_hidden=1&jsonReturn=1&filter=');

        // dd($g->json());
        // ProductsDetail::where('MODA_NUM',1001072796)->get()->dd();
        // DB::table('MODELO_DETALE')->where('MODA_NUM',1001072796)->get()->dd();
        // dd(BillingInfo::where('CLIENT_NUM',1026071)->get());
        $ventas = Ventas::where('CLIENT_NUM',Auth::user()->num)->latest()->paginate(6);
//dd($ventas);
        $arreglo = function($venta){
// dd($venta);
            $venta['delivery_price'] = $this->getDeliveryPrice($venta);
            $venta['store'] = Store::GetStoreCD(['group_cd'=>$venta['group_cd'], 'local_cd' => $venta['local_cd']]);

            $productosIDS = [];

            foreach ($venta['detail'] as $key => $detail) {
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
              
              foreach ($venta['detail'] as $d => $detail) {
                if($detail['shop_modelo_num'] == $id){
                  $product['detalles'][] = $detail;
                }
              }
              
              $productos[] = $product;
            }
            
            $venta['productos'] = $productos;

            return $venta;
        };
// dd(collect($ventas)->all());
        $ventas = array_map($arreglo, collect($ventas)->all()['data']);
// dd($ventas);
        return response()->json($ventas);
    }

    private function getDeliveryPrice($venta)
    {
// dd($venta);
        $response = Http::asForm()->post($this->url, [
            'menu' => 'prev_venta_new',
            'venta_num' => $venta['num'],
        ]);

        return $response->collect()->all();
    }
}
