<?php

namespace App\Http\Controllers;

use App\Http\Traits\ProductsTraits;
use App\Models\Code;
use App\Models\ProductFavorite;
use App\Models\Products;
use App\Models\ProductsDetail;
use App\Models\Store;
use App\Models\Cart;
use Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\Pool;
use Illuminate\Support\Str;
class ProductsController extends Controller
{

    private  $url = 'https://www.modatex.com.ar/modatexrosa3/?c=Products::get&';
    private $urlSearch = 'https://www.modatex.com.ar/app.proxy.php?';
    private $urlImage = 'https://netivooregon.s3.amazonaws.com/';
    private $page = 0;

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
                                    ->where('CLIENT_NUM',Auth::user()->num)
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
      $rr = $request->all();
      if(isset($request->no_product_id)){
        $rr['length'] = $rr['length'] + 1;
      }
      $url = $this->url.Arr::query($rr);
      $response = Http::acceptJson()->get($url);

      $data = $response->collect()->all()['data'];

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

      return response()->json($data);
    }

    public function generateModels($product)
    {

      if(!isset($product['price'])){
        return [];
      }

      $models = Code::select('CODE_NAME as size','NUM as size_id')
                ->where('STAT_CD',1000)
                ->whereIn('CODE_NAME',$product['sizes'])->get();

      $detalle = ProductsDetail::select('NUM as id','PARENT_NUM as product_id','SIZE_NUM as size_id','COLOR_NUM as color_id','QUANTITY as quantity')
                              ->where('PARENT_NUM',$product['id'])
                              ->where('STAT_CD',1000)
                              ->get();

      $detalle = collect($detalle)->all();

      foreach ($models as $key => $code) {

        $filtered = Arr::where($detalle, function ($value, $key) use ($code) {
          return $value['size_id'] == $code['size_id'];
        });
        [$keys, $values] = Arr::divide(collect($filtered)->all());
        $code['price'] = isset($product['price']) ? $product['price'] : $product['id'];
        $code['properties'] = $values;
      }

      return $models;
    }

    public function arregloProduct($data)
    {
      $arregloImages = function($images){
        return $this->urlImage.$images['lg'];
      };

    	$arreglo = function($product) use ($arregloImages)
      {
        $store = Store::LOCAL_CD($product['store'])->first();

        // dd($product);
        return [
          "id"          => $product['id'],
          "moda_id"     => $product['moda_id'],
          "store"       => $product['store'],
          "company"     => $product['company'],
          "code"        => $product['code'],
          "name"        => $product['name'],
          "category"    => isset($product['category']) ? $product['category']:null,
          "category_id" => $product['category_id'],
          "price"       => isset($product['price']) ? $product['price'] : null,
          "prev_price"  => isset($product['prev_price']) ? $product['prev_price']:null,
          "images"      => array_map($arregloImages, $product['images']),
          "sizes"       => $product['sizes'],
          "colors"      => isset($product['colors']) ? $product['colors']: null,
          "models"      => $this->generateModels($product),
          "discount"    => $product['discount'],
          "has_stock"   => $product['has_stock'],
          "isCart"      => $this->isProduct($product['id']),
          "store_data" => [
            'logo' => env('URL_IMAGE').'/modatexrosa2/img/modatexrosa2/'. Str::lower(Str::slug($store['LOCAL_NAME'], '')).'.gif',
            'name' => $store['LOCAL_NAME'],
            'min'  => $store['LIMIT_PRICE'],
            "id"   => $store['LOCAL_CD'],
            "company"     => $store['GROUP_CD'],
          ]
        ];
      };

      return array_map($arreglo, $data);
    }

    public function isProduct($product_id)
    {
      return Cart::where('CLIENT_NUM',Auth::user()->num)->where('MODELO_NUM', $product_id)->where('STAT_CD',1000)->count();
    }

    public function getSearch(Request $request)
    {
      $url = $this->urlSearch.Arr::query($request->all());
      $response = Http::acceptJson()->get($url);

      $data = $response->collect()->all();

      $arregloImages = function($images){
        return Arr::flatten($images)[0];
      };

      $i = 0;
      $arreglo = function($product) use ($arregloImages, $i)
      {
        // dd(Arr::crossJoin($product['colores'],$product['colores_reference']));
        if($i == 1){
          // dd($product);
        }
        
        $colores = [];
        foreach ($product['colores_reference'] as $key => $value) {
          $colores[] = [
            'code'  => $value,
            'name'  => $product['colores'][$key], 
            'order' => '',
            'id'    => $product['colores_id'][$key]
          ];
        }

        $store = Store::LOCAL_CD($product['local_cd'])->first();
        
        $product['id'] = $product['num'];
        $product['sizes'] = $product['talles'];
        $product['price'] = isset($product['price_curr']) ? $product['price_curr']:$product['precio'];

        
        return [
          "id"          => $product['num'],
          // "moda_id"     => $product['local_cd'],
          "store"       => $product['local_cd'],
          "company"     => $store['GROUP_CD'],
          // "code"        => $product['code'],
          "name"        => $product['descripcion'],
          "category"    => isset($product['category_name']) ? $product['category_name']:null,
          "category_id" => $product['category'],
          "price"       => isset($product['price_curr']) ? $product['price_curr']:$product['precio'],
          "prev_price"  => isset($product['price_prev']) ? $product['price_prev']:null,
          "images"      => array_map($arregloImages, $product['images']),
          "sizes"       => $product['talles'],
          "colors"      => $colores,
          "is_desc"     => $product['is_desc'],
          "models"      => $this->generateModels($product),
          "isCart"      => $this->isProduct($product['num']),
          // "disc/ount"    => $product['discount'],
          "has_stock"   => $product['con_stock'] == "" ? true:$product['con_stock'],
          "store_data" => [
            'logo' => env('URL_IMAGE').'/common/img/logo/'.$store['LOGO_FILE_NAME'],
            'name' => $store['LOCAL_NAME'],
            'min'  => $store['LIMIT_PRICE'],
            "id"   => $store['LOCAL_CD'],
            "company"     => $store['GROUP_CD'],
          ]
        ];
      };

      $data = array_map($arreglo, $data['modelos']);

              return response()->json($data);
    }

    public function oneProduct($product_id)
    {
      $request = [];
      $request['product_id'] = $product_id;
      // dd(Arr::query($request));
      $url = $this->url.Arr::query($request);
      $response = Http::acceptJson()->get($url);
      $data = $response->collect()->all();
      // dd($data);
      $data = $this->arregloProduct($data);
      return $data;
    }

    public function whereInProducts($products_ids)
    {
      if(count($products_ids) == 0){

        return [];
      }
      $urls;
      foreach ($products_ids as $key => $id) {
        $request = [];
        $request['product_id'] = $id;
        $urls[] = $this->url.Arr::query($request);
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
          $products[] = $this->arregloProduct($consultas[$i]->collect()->all())[0];
        }
      }
      return collect($products)->all();
    }
}
