<?php

namespace App\Http\Controllers;

use Auth;
use Carbon\Carbon;
use App\Models\Cart;
use App\Models\Code;
use App\Models\Store;
use App\Models\Prices;
use App\Models\Products;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\ProductsDetail;
use App\Models\ProductFavorite;
use Illuminate\Http\Client\Pool;
use App\Http\Traits\ProductsTraits;
use Illuminate\Support\Facades\Http;

class ProductsController extends Controller
{

    private $url = 'https://www.modatex.com.ar/modatexrosa3/?c=Products::get&';
    private $urlSearch = 'https://www.modatex.com.ar/app.proxy.php?';
    private $urlImage = 'https://netivooregon.s3.amazonaws.com/';
    private $page = 0;
    private $categories = ['woman','man','xl','kids','accessories','sportive','lingerie','home','shoes'];
    private $urlBase = 'https://www.modatex.com.ar/modatexrosa3/?c=';
    /*
    * @params product_page is required
    * @return Products Array
    */
    public function getProducts(Request $request){

        if (!isset($request->product_page) && !isset($request->product_limit)) {
            return response()->json(['status'=>false, 'message'=> 'El paramatero :product_page es requerido'],422);
        }

        $products = new Products($request->all());
        $products = $products->allProducts($request->all());

        return response()->json(['status'=>true,'products'=> $products], 200);
    }

    /*
    * @params $product = $slug
    * @return Collection
    */
    public function getProduct($product, Request $request){

        $product = new Products(['product' => $product]);
        $product = $product->getProduct($request->all());

        return response()->json(['status'=>true,'product'=> $product], 200);
    }

    public function product_favorite(Request $request)
    {
        $this->validate($request, [
            'MODELO_NUM' => 'required',
        ]);

        $product = ProductFavorite::where('MODELO_NUM',$request->MODELO_NUM)
                                    ->where('CLIENT_NUM',Auth::user()->num).
                                    ->first();
        if (!$product) {
            $product             = new ProductFavorite();
            $product->MODELO_NUM = $request->MODELO_NUM;
            $product->CLIENT_NUM = Auth::user()->num;
            $product->save();
        }else{
            $product->delete();
        }

        return response()->json(['status'=> true],201);
    }

    public function getProductsRosa(Request $request)
    {     
      return response()->json($this->onGetSearch($request->all()));
    }

    private function generateModels($product)
    {

      if(!isset($product['price'])){
        return [];
      }

      $models = Code::
                select('CODE_NAME as size','NUM as size_id')
                ->where('STAT_CD',1000)
                ->whereIn('CODE_NAME',$product['sizes'])->get();

                // dd($models);

      $detalle = ProductsDetail::
      select('NUM as id','PARENT_NUM as product_id','SIZE_NUM as size_id','COLOR_NUM as color_id','QUANTITY as quantity','MODA_NUM')
                              ->where('PARENT_NUM',$product['id'])
                              ->where('STAT_CD',1000)
                              ->get();
                              

      $detalle = collect($detalle)->all();

      foreach ($models as $key => $code) {
        // dd($code);
        $filtered = Arr::where($detalle, function ($value, $key) use ($code) {
          return $value['size_id'] == $code['size_id'];
        });

        [$keys, $values] = Arr::divide(collect($filtered)->all());

        // dd($values);

        foreach ($values as $key => $val) {
          $price = Prices::select('venta_precio','PARENT_NUM')->where('STAT_CD',1000)->where('PARENT_NUM', $val['MODA_NUM'])->first();
          $val['price'] = $price->venta_precio;
        }

        $code['price'] = isset($product['price']) ? $product['price'] : $product['id'];
        $code['properties'] = $values;
      }

      return $models;
    }

    public function arregloProduct($data, $config = ['isModels' => false])
    {
      $productos = collect($data);

      // dd($productos);
      // dd(Prices::limit(5)->get());
      $idsProductos = $productos->pluck('id');
      $localCds     = $productos->pluck('store');
      
      $stores = Store::whereIn('LOCAL_CD', $localCds->all())->select(
        'GROUP_CD',
        'LOGO_FILE_NAME',
        'LOCAL_NAME',
        'LIMIT_PRICE',
        'LOCAL_CD',
        'GROUP_CD',
        'MODAPOINT',
        'PREDEF_SECTION',
        'USE_DEPORTIVA',
        'USE_LENCERIA',
        'USE_MAN',
        'USE_WOMAN',
        'USE_CHILD',
        'USE_ACCESORY',
        'USE_SPECIAL',
        'USE_DEPORTIVA',
        'USE_LENCERIA',
        'USE_SHOES',
        'USE_HOME',
        )
      ->get();

      $products_carro = $this->isProductCarro($idsProductos->all(), ['whereIn'=>true]);

      $products_carro = $products_carro->pluck('MODELO_NUM')->countBy();

      $productos = $productos->map( function($product) use ($stores, $products_carro, $config) {

        // dd($product);
        $arregloImages = function($images){
          return $this->urlImage.$images['lg'];
        };

        $store = $stores->where('LOCAL_CD',$product['store'])->first(); 
        $models = null;

        
        if(isset($config['isModels']) && $config['isModels']){
          $models = $this->generateModels($product);
        }

        // dd($store->toArray());
        $storeq = new StoresController();
        $predefSection = $storeq->categorieDefaultId($store->toArray());


        return [
          "id"          => $product['id'],
          "code" => isset($product['code']) ? $product['code'] : '',
          "local_cd"    => $product['store'],
          "company"     => $store['GROUP_CD'],
          "name"        => $product['name'],
          "category"    => isset($product['category']) ? $product['category']:null,
          "category_id" => $product['category_id'],
          "price"       => isset($product['price']) ? $product['price']:null,
          "prev_price"  => isset($product['prev_price']) ? $product['prev_price']:null,
          "images"      => array_map($arregloImages, $product['images']),
          "sizes"       => $product['sizes'],
          "colors"      => isset($product['colors']) ? $product['colors']: null,
          "is_desc"     => $product['discount'],
          "isCart"      => Arr::exists($products_carro->all(), $product['id']),
          "has_stock"   => $product['has_stock'],
          "models"      => $models,
          "store" => [
            'logo' => env('URL_IMAGE').'/common/img/logo/'.$store['LOGO_FILE_NAME'],
            'name' => $store['LOCAL_NAME'],
            'min'  => $store['LIMIT_PRICE'],
            "id"   => $store['LOCAL_CD'],
            "company"     => $store['GROUP_CD'],
            'rep' => $store['MODAPOINT'],
            "category_default" => $predefSection

          ]
        ];
      });

      return $productos->all();
    }

    public function isProductCarro($product_id, $config = [])
    {

      $cart = Cart::where('CLIENT_NUM',Auth::user()->num);

      if(isset($config['whereIn']) && $config['whereIn']){
        return Cart::select('MODELO_NUM')->where('CLIENT_NUM',Auth::user()->num)->whereIn('MODELO_NUM', $product_id)->where('STAT_CD',1000)->get();
      }

      return Cart::where('CLIENT_NUM',Auth::user()->num)->where('MODELO_NUM', $product_id)->where('STAT_CD',1000)->count();
    }

    public function getSearch(Request $request)
    {
      $response = $this->onGetSearch($request->all());

      return response()->json($response);
    }

    public function onGetSearch($request)
    {
     
      $rr = $request;
     
      if(isset($request->no_product_id)){
        $rr['length'] = $rr['length'] + 1;
      }
      
      $url = $this->url.Arr::query($rr);
      $response = Http::acceptJson()->get($url);

      if(!$response->json()){
        return [];
      }

      $data = $response->json()['data'];

      $data = $this->arregloProduct($data);

      $d = [];
      if(isset($request->no_product_id)){
        foreach ($data as $key => $value) {
          if($value['id'] != $request->no_product_id){
           $d[] = $value;
          }
        }
        $data = $d;
      }

      return $data;

    }

    public function oneProduct($product_id)
    {
      $request = [];
      $request['product_id'] = $product_id;
      $url = $this->url.Arr::query($request);
      $response = Http::acceptJson()->get($url);
      $data = $response->collect()->all();
      
      $data = $this->arregloProduct($data,['isModels' => true]);
      return $data;
    }

    public function inProducts(Request $request)
    {

      return response()->json($this->whereInProducts($request->data));

    }

    public function whereInProducts($products_ids, $config = ['isModels'=> true])
    {
      if(count($products_ids) == 0){
        return [];
      }

      $urls;
      foreach ($products_ids as $key => $id) {
        if($id != '0'){
          $request = [];
          $request['product_id'] = $id;
          $urls[] = $this->url.Arr::query($request);
        }
      }

      
      $collection = collect($urls);

      $consultas = Http::pool(fn (Pool $pool) => 
        $collection->map(fn ($url) => 
          $pool->acceptJson()->get($url)
        )
      );

      $products = [];
      for ($i=0; $i < count($urls) ; $i++) {
        $data = $consultas[$i]->collect()->all();
        if(count($consultas[$i]->collect()->all())){
          // dd($consultas[$i]->collect()->all());
          try {
            $products[] = $this->arregloProduct($consultas[$i]->collect()->all(),$config)[0];
            //code...
          } catch (\Throwable $th) {
            //throw $th;
          }
        }
      }

      // dd($products);

      return collect($products)->all();
    }

    public function destacados($request)
    {

      $url = $this->urlBase.'Highlights::get&'.Arr::query($request);
      $response = Http::acceptJson()->get($url);

      $data = $response->json()['data'];

      $data = $this->arregloProduct($data);
      
      return $data;
      
    }
}
