<?php

namespace App\Http\Controllers;

// use App\Http\Middleware\Auth;
use App\Http\Controllers\ProductsController;
use App\Models\Cart;
use App\Models\Products;
use App\Models\Store;
use Auth;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;


class CartController extends Controller
{

    private $url = 'https://www.modatex.com.ar/modatexrosa3/';
    private $token;

    // public function getCarts($client_id, Request $request){
    //     $carts = new Cart($request->all());
    //     $carts = $carts->Client($client_id)->getCarts();

    //     return response()->json(['status'=> true,'carts'=> $carts],200);
    // }

    // public function getProductsCar(Request $request){
    //     $params['is_store'] = true;
    //     $products = new Products($params);
    //     $products = $products->getInProducts(explode(",", $request->id));

    //     return response()->json(['status'=> true, 'products' => $products]);

    // }

    public function addCar(Request $request)
    {
      // dd($request->all());
      try {
        foreach ($request->all() as $key => $value) {

            $cart = Cart::where('CLIENT_NUM',Auth::user()->num)
                ->where('GROUP_CD', $value['group_cd'])
                ->where('LOCAL_CD', $value['local_cd'])
                ->where('MODELO_NUM', $value['product_id'])
                ->where('MODELO_DETALE_NUM', $value['models_id'])
                ->where('SIZE_NUM', $value['size_id'])
                ->where('COLOR_NUM', $value['color_id'])
                ->where('STAT_CD',1000)
                ->first();
                // dd($cart);

            if(!$cart){
              $cart = new Cart();
              $cart->DISCOUNT_NUM      = 0;
              $cart->DISCOUNT_DESC     = '';
              $cart->DISCOUNT_PRICE    = 0;
              $cart->DISC_CNT_NUM      = 0;
              $cart->DISC_CNT_DESC     = '';
              $cart->DISC_CNT_PRICE    = 0;
              $cart->WORK_NUM          = Auth::user()->num;
              $cart->CLIENT_NUM        = Auth::user()->num;
              $cart->GROUP_CD          = $value['group_cd'];
              $cart->LOCAL_CD          = $value['local_cd'];
              $cart->MODELO_NUM        = $value['product_id'];
              $cart->MODELO_DETALE_NUM = $value['models_id'];
              $cart->SIZE_NUM          = $value['size_id'];
              $cart->COLOR_NUM         = $value['color_id'];
              $cart->PRICE             = $value['price'];
            }

            
            $cart->CANTIDAD          = $value['cantidad'];
            $cart->TOTAL_PRICE       = $value['total_price'];
            
            $cart->save();


            \Log::info($cart);
        }
      } catch (\Exception $e) {
          return response()->json(['status'=> false,'message'=> $e->getMessage()], 422);  
      }
      return response()->json(['status'=> true]);
    }
    //ACTUALIZAR UN PRODUCTO DEL CARRO
    public function updatedCar(Request $request){
      try {
        // dd($request->all());
        $cart = Cart::where('CLIENT_NUM',Auth::user()->num)
                ->where('GROUP_CD', $request->group_cd)
                ->where('LOCAL_CD', $request->local_cd)
                ->where('MODELO_NUM', $request->product_id)
                ->where('MODELO_DETALE_NUM', $request->modelo_actual)
                ->where('STAT_CD',1000)
                ->update([
                  'MODELO_DETALE_NUM' => $request->models_id,
                  'SIZE_NUM'          => $request->size_id,
                  'COLOR_NUM'         => $request->color_id,
                  'TOTAL_PRICE'       => $request->total_price,
                  'PRICE'             => $request->price,
                  'CANTIDAD'          => $request->cantidad
                ]);

      } catch (\Exception $e) {
        return response()->json(['status'=> false,'message'=> $e->getMessage()], 422); 
      }
      return response()->json(['status'=> true]);
    }


    public $selectCar = ['CART.NUM','CART.CLIENT_NUM','CART.GROUP_CD','CART.LOCAL_CD','CART.MODELO_NUM','CART.MODELO_DETALE_NUM','CART.SIZE_NUM','CART.COLOR_NUM','CART.PRICE','CART.CANTIDAD','CART.TOTAL_PRICE','LOCAL.LOCAL_NAME','LOCAL.LIMIT_PRICE'];

    // TRAE TODOS LOS STORE/CARRITOS
    public function getCarts(Request $request){ 

        $carts = Cart::
                  select($this->selectCar)
                  ->where('CART.CLIENT_NUM',Auth::user()->num)
                  ->where('CART.STAT_CD',1000)
                  ->orderBy('CART.INSERT_DATE','desc')
                  ->join('LOCAL', 'LOCAL.LOCAL_CD', '=', 'CART.LOCAL_CD')
                  ->where('LOCAL.STAT_CD', 1000)
                  ->where('LOCAL.LOGO_FILE_NAME','!=','')
                  ->where('LOCAL.LOGO_FILE_NAME','!=',NULL)
                  ->get();

        $stores_ids = array_unique(Arr::pluck($carts->all(), ['LOCAL_CD']));

        $stores = Store::whereIn('LOCAL_CD',$stores_ids)->where('STAT_CD', 1000)->get();

        if($stores){

          $stores = $stores->map(fn ($store)=>
             $this->arregloCart($store,$carts)
          );
          
          return response()->json(['stores' => $stores]);
        }

        return response()->json(['message' => 'No se encontraron carros abiertos' ], 422);
    }

    //TRAE UN SOLO CARRO SEGUN LA MARCA
    public function getCart($store_id)
    {
      $carts = Cart::select($this->selectCar)
                  ->where('CART.CLIENT_NUM',Auth::user()->num)
                  ->where('CART.STAT_CD',1000)
                  ->where('CART.LOCAL_CD', $store_id)
                  ->orderBy('CART.INSERT_DATE','desc')
                  ->join('LOCAL', 'LOCAL.LOCAL_CD', '=', 'CART.LOCAL_CD')
                  ->where('LOCAL.STAT_CD', 1000)
                  ->get();

      $stores_ids = array_unique(Arr::pluck($carts->all(), ['LOCAL_CD']));

      $stores = Store::whereIn('LOCAL_CD',$stores_ids)->where('STAT_CD', 1000)->get();
      if($stores){

        $stores = $stores->map(fn ($store)=>
          $this->arregloCart($store,$carts)
        );

        $stores = Arr::collapse($stores);

        return response()->json($stores);

      }

      return response()->json(['message' => 'No se encontraron carros abiertos para esta marca' ], 422);
      
    }
    
    private function arregloCart($store, $carts)
    {
      $products = $carts->where('LOCAL_CD',$store['LOCAL_CD']);
      $suma = 0;
      $cart_ids = [];
      foreach ($products->all() as $key => $value) {
        $suma += (floatval($value['PRICE']) * $value['CANTIDAD']);
        $cart_ids[] = $value['NUM'];
      }

      return [
        "id"             => $store['LOCAL_CD'],
        "company"        => $store['GROUP_CD'],
        "name"           => $store['LOCAL_NAME'],
        "limit_price"    => floatval($store['LIMIT_PRICE']),
        "logo"           => env('URL_IMAGE').'/common/img/logo/'.$store['LOGO_FILE_NAME'],
        "products_count" => $products->count(), 
        "total"          => $suma, 
        "is_limit"       => $suma >= floatval($store['LIMIT_PRICE']),
        'cart_ids'       => $cart_ids
      ];

    }

    // TRAE LOS PRODUCTOS DE UN CARRITO
    public function getProductsCart($store_id)
    {
      $carts = Cart::
                where('CART.CLIENT_NUM',Auth::user()->num)
                // select($this->selectCar)
                ->where('CART.STAT_CD',1000)
                ->where('CART.LOCAL_CD', $store_id)
                ->orderBy('CART.INSERT_DATE','desc')
                ->join('LOCAL', 'LOCAL.LOCAL_CD', '=', 'CART.LOCAL_CD')
                ->where('LOCAL.STAT_CD', 1000)
                ->get();

      $productos = [];
      $products_id = [];

      foreach ($carts as $key => $value) {
        if(!in_array($value['MODELO_NUM'], $products_id)){
          $products_id[] = $value['MODELO_NUM'];
        }
      }
      $p = new ProductsController();
      $productos = $p->whereInProducts($products_id);
      
      $arregloProduct = function($product) use ($carts){

        $combinaciones = [];

        foreach ($carts as $key => $cart) {

          if($cart['MODELO_NUM'] == $product['id']){
            if(count($product['models'])){
              $filteredSize = Arr::where(collect($product['models'])->all(), function ($value, $key) use ($cart) {
                return $value['size_id'] == $cart['SIZE_NUM'];
              });

              [$keysSize, $valuesSize] = Arr::divide(collect($filteredSize)->all());
              
              $filteredColor = Arr::where($product['colors'], function ($value, $key) use ($cart) {
                return $value['id'] == $cart['COLOR_NUM'];
              });

              [$keysColor, $valuesColor] = Arr::divide(collect($filteredColor)->all());

              // dd($cart);
              $combinaciones[] = [
                "sizes"           => $product['sizes'],
                "colors"          => $product['colors'],
                "colorActive"     => count($valuesColor) ? $valuesColor[0]['code']:null,
                "talleActive"     => count($valuesSize) ? $valuesSize[0]['size']:null,
                "product_id"      => $product['id'],
                "cantidad"        => $cart['CANTIDAD'],
                "combinacion_key" => $key,
                "descripcion"     => $product['name'],
                "cart_id"         => $cart['NUM'],
                "modelo" => $cart['MODELO_DETALE_NUM']
              ];
            }
          }
        }

        // dd($product);
        return [
          "images"        => $product['images'],
          "precio"        => $product['price'],
          "id"            => $product['id'],
          "descripcion"   => $product['name'],
          "store"         => [
            "id"          => $product['store_data']['id'],
            "company"     => $product['store_data']['company'],
            "name"        => $product['store_data']['name'],
            "limit_price" => $product['store_data']['min'],
            "logo"        => $product['store_data']['logo'],
          ],
          "sizes"         => $product['sizes'],
          "colors"        => $product['colors'],
          "combinacion"   => $combinaciones,
          "models"        => $product['models']
        ];
      
      };


      $productos = array_map($arregloProduct, $productos);

      return response()->json(['products' => $productos]);
   
    }

    // BORRA UN PRODUCTO DEL CARRITO SEGUN ID porducto y el id modelo
    public function deleteModelo(Request $request)
    {
      try {
        $cart = Cart::where('CLIENT_NUM',Auth::user()->num)
              ->where('MODELO_NUM',$request->product_id)
              ->where('MODELO_DETALE_NUM', $request->modelo)
              ->where('STAT_CD',1000)
              ->first();

        if($cart){
          $cart->STAT_CD = 5000;
          $cart->save();

          return response()->json(['status'=> true]);
        }else{
          throw new \Exception("Modelo no encontrado");
        }
      } catch (\Exception $e) {
        return response()->json(['message' => $e->getMessage() ], 422); 
      }
    }

    //BORRA VARIOS PRODUCTOS DEL CARRITO SEGUN ID DEL CART
    public function deleteCarts(Request $request)
    {
      try {
        $carts = Cart::where('CLIENT_NUM',Auth::user()->num)
              ->whereIn('NUM',$request->cart_ids)
              ->where('STAT_CD',1000)
              ->get();

        if($carts){
          foreach ($carts->all() as $key => $cart) {
            $cart->STAT_CD = 5000;
            $cart->save();
          }

          return response()->json(['status'=> true]);
        }else{
          throw new \Exception("Modelos no encontrados");
        }
      } catch (\Exception $e) {
        return response()->json(['status'=> false, 'message' => $e->getMessage() ], 422); 
      }
    }

    //BORRA UN PRODUCTO SEGUN EL ID DEL MODELO
    public function deleteProduct(Request $request)
    {
       try {

        $carts = Cart::where('CLIENT_NUM',Auth::user()->num)
              ->where('MODELO_NUM',$request->product_id)
              ->where('STAT_CD',1000)
              ->get();

        if($carts){

          foreach ($carts as $key => $cart) {
            $cart->STAT_CD = 5000;
            $cart->save();
          }

          return response()->json(['status'=> true]);
        }else{
          throw new \Exception("Producto no encontrado");
        }
      } catch (\Exception $e) {
        return response()->json(['status'=> false, 'message' => $e->getMessage() ], 422); 
      }
    }

    //PROCESA EL CARRITO
    //VALIDA SI POSEE TODOS LOS DATOS
    //RETORNA EL GROUP ID
    public function processCart(Request $request)
    {

      $this->validate($request, [
        'local_cd' => 'required',
      ]);
      
      $url = $this->url.'?c=Cart::send_to_checkout&store_id='.$request->local_cd;

      $this->token = Auth::user()->api_token; 

      $response = Http::withHeaders([
          'x-api-key' => $this->token,
          'Content-Type' => 'application/json'
      ])
      ->post($url);

      $datos = [ 'cart' => $response->json() ];


      $response = Http::withHeaders([
          'x-api-key' => $this->token,
          'Content-Type' => 'application/json'
      ])
      ->post($this->url.'?c=User::is_missing_data');

      $datos['is_missing_data'] = $response->json();

      return response()->json($datos);

    }
}
