<?php

namespace App\Http\Controllers;

// use App\Http\Middleware\Auth;
use App\Http\Controllers\CouponsController;
use App\Http\Controllers\ProductsController;
use App\Models\Cart;
use App\Models\Products;
use App\Models\Store;
use Auth;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Http\Client\Pool;
use App\Http\Controllers\Objects\Producto;


class CartController extends Controller
{

    private $url = 'https://www.modatex.com.ar/';
    private $token;

    /**
     * Agrega y actualiza el carro
     */
    public function addCar(Request $request)
    {

      $url = $this->url.'?c=Cart::product_update';

      $this->token = Auth::user()->api_token; 
      
      $response = Http::withHeaders([
          'x-api-key' => $this->token,
          // 'Content-Type' => 'application/x-www-form-urlencoded'
      ])
      ->asForm()
      ->post($url,[
        'store_id'=> $request->store_id,
        'company_id' => $request->company_id,
        'product_id' => $request->product_id,
        'amounts' => json_encode($request->amounts),
        'filter_inactive' => true,
        'filter_priceless' => true,
        'filter_hidden' => true,
        'filter_no_stock' => true
      ]);

      return response()->json(['status'=> true, 'data' => $response->json() ]);

    }

    //TRAE UN SOLO CARRO SEGUN LA MARCA
    public function getCart($store_id)
    {

      $url = $this->url.'?c=Cart::get&store_id='.$store_id;

      // dd($url);
      $this->token = Auth::user()->api_token; 
     
      $response = Http::withHeaders([
          'x-api-key' => $this->token,
          'Content-Type' => 'application/json'
          // 'Content-Type' => 'application/x-www-form-urlencoded'
      ])
      // ->asForm()
      ->post($url,[
        'store_id' => $store_id,
      ]);

      
      $carrito = $response->json();

      if(!$carrito){
        return response()->json([
          'total' => 0,
          'cantidadModelos' => 0,
          'productos' => []
        ], 422);
      }
      $Urlagregados = collect($carrito['added'])->map(function($agregado){
        return 'https://www.modatex.com.ar/?c=Products::get&product_id='.$agregado['product_id'];
      });

      $consultas = Http::pool(fn (Pool $pool) => 
          $Urlagregados->map(fn ($url) => 
            $pool->acceptJson()->get($url)
          )
      );

      $productos = collect($consultas)->map(function($respuesta) use($carrito){
        return $respuesta->collect()->map(function($product) use($carrito){
          
          // dd($carrito, $product);
          $cartUrl = 'https://www.modatex.com.ar/?c=Cart::product&'.Arr::query([
            'store_id' => $product['store'],
            'company_id' => $product['company'],
            'product_id' => $product['id'],
          ]);
    
          $responseCart = Http::withHeaders([
            'x-api-key' => Auth::user()->api_token,
            'Content-Type' => 'application/json'
          ])->get($cartUrl);

          $producto = new Producto($product);
          $producto->setModelos($responseCart->collect()->all());
          $producto = $producto->getProducto();

          
          $producto['carro'] = collect($carrito['added'])->where('product_id', $product['id'])->values();
          $producto['cantidad_add'] = $producto['carro']->sum('amount');
          $producto['total_add'] = $producto['carro']->sum('total');

          return $producto;
        });
      })->collapse();

      return response()->json([
        'total' => $carrito['total'],
        'cantidadModelos' => $productos->sum('cantidad_add'),
        'productos' => $productos
      ]);

      // return response()->json(['message' => 'No se encontraron carros abiertos para esta marca' ], 422);
      
    }

    //BORRA UN PRODUCTO SEGUN EL ID DEL MODELO
    public function deleteProduct(Request $request)
    {

      $producto = new ProductsController();
      $productoData = $producto->oneProduct($request->product_id);

      $modelos = collect($productoData['models'])->map(function($modelo){
        return [
          'size_id' => $modelo['size_id'],
          'properties' => collect($modelo['properties'])->map(function($propertie){
            return [
              'detail_id' => $propertie['detail_id'],
              'color_id' => $propertie['color_id'],
              'amount' => 0
            ];
          })
        ];
      });

      $url = $this->url.'?c=Cart::product_update';

      $this->token = Auth::user()->api_token; 
      
      $response = Http::withHeaders([
          'x-api-key' => $this->token,
          // 'Content-Type' => 'application/x-www-form-urlencoded'
      ])
      ->asForm()
      ->post($url,[
        'store_id'=> $productoData['local_cd'],
        'company_id' => $productoData['company'],
        'product_id' => $productoData['id'],
        'amounts' => json_encode($modelos->all()),
        'filter_inactive' => true,
        'filter_priceless' => true,
        'filter_hidden' => true,
        'filter_no_stock' => true
      ]);

      return response()->json(['status'=> true, 'data' => $response->json() ]);

      
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
      return response()->json(['status'=> true ,]);
    }


    public $selectCar = ['CART.NUM','CART.STAT_CD','CART.CLIENT_NUM','CART.GROUP_CD','CART.LOCAL_CD','CART.MODELO_NUM','CART.MODELO_DETALE_NUM','CART.SIZE_NUM','CART.COLOR_NUM','CART.PRICE','CART.CANTIDAD','CART.TOTAL_PRICE','LOCAL.LOCAL_NAME','LOCAL.LIMIT_PRICE'];

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
                  ->where('CART.PRICE', '>', 0)
                  ->get();


        $stores = new StoresController();
        $stores = $stores->consultaStoresRosa([
          'in' =>  array_unique(Arr::pluck($carts->all(), ['LOCAL_CD']))
        ]);

        if($stores){

          $stores = $stores->map(fn ($store)=>
             $this->arregloCart($store,$carts)
          );
          $blocks = [
            //   [
            //   "type" => "text",
            //   "text" => '<p style="font-weight: 600; text-align:center" >Hoy tu compra suma una chance para el sorteo de <br> <br> <span style="background-color: #4CAF50;color: white;padding: 2px 7px;border-radius: 8px;margin: 0 3px;font-size: 16px;">$1.000.000</span> <br> <br> <span style="font-size: 12px">Los pedidos de más de $30.000 tienen doble chance! </span></p>'
            // ]
          ];
          // dd($stores);  
          return response()->json(['stores' => $stores->values()->toArray(), 'blocks' => $blocks ]);
        }

        return response()->json(['message' => 'No se encontraron carros abiertos' ], 422);
    }

    
    
    private function arregloCart($store, $carts)
    {
      
      $products = $carts->where('LOCAL_CD',$store['local_cd']);
      $suma = 0;
      $cart_ids = [];
      $conteo = 0;
          //  dd($products->all() );
      foreach ($products->all() as $key => $value) {
        $suma += (floatval($value['PRICE']) * $value['CANTIDAD']);
        $conteo+=$value['CANTIDAD'];
        $cart_ids[] = $value['NUM'];
      }
      
      return [
        "id"             => $store['local_cd'],
        "company"        => null,
        "name"           => $store['name'],
        "limit_price"    => intval($store['min']),
        "logo"           => $store['logo'],
        "products_count" => $conteo, 
        "total"          => $suma, 
        "is_limit"       => $suma >= $store['min'],
        'cart_ids'       => $cart_ids,
        'rep'            => $store['rep'],
        "vc"             => $store['vc'],
        "categorie"      => $store['categorie'], 
        "category_default" => $store['category_default'],
        "categories_store" => $store['categories_store'],
        "paquete"          => $store['paquete'], 
        "cleaned"          => $store['cleaned'], 
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
                ->where('CART.PRICE', '>', 0)
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
      // dd($productos);
      $arregloProduct = function($product) use ($carts){

        // dd($product);
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

        return [
          "images"        => $product['images'],
          "precio"        => $product['price'],
          "id"            => $product['id'],
          "descripcion"   => $product['name'],
          "store"         => [
            "id"          => $product['store']['id'],
            "company"     => $product['store']['company'],
            "name"        => $product['store']['name'],
            "limit_price" => $product['store']['min'],
            "logo"        => $product['store']['logo'],
          ],
          "sizes"         => $product['sizes'],
          "has_stock"       => $product['has_stock'],
          "colors"        => $product['colors'],
          "combinacion"   => $combinaciones,
          "models"        => $product['models'],
          "code"           => $product['code'],
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


      // dd($this->url.'?c=Coupons::get&store_id='.$request->local_cd.'&welcome=1');
      // dd([
      //   'x-api-key' => $this->token,
      //   'Content-Type' => 'application/json'
      // ]);
      $response = Http::withHeaders([
          'x-api-key' => $this->token,
          'Content-Type' => 'application/json'
      ])
      ->post($this->url.'?c=Coupons::get&store_id='.$request->local_cd.'&welcome=1',[]);

      $datos['cupon'] = CouponsController::getCupon($response->collect());

      $datos['cupon'] = $datos['cupon']->sortByDesc(function ($item) {
        return $item['name'] === 'app_coupon';
      })
      ->values()
      // ->where('name','!=','app_coupon')->values()
      ;

      if(!$datos['cupon']->count()){
        $datos['cupon'] = null;
      }

      return response()->json($datos);

    }
}
