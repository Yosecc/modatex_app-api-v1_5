<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Cart;
use App\Models\Store;
use App\Models\Slider;
use App\Models\States;
use App\Models\Products;
use Illuminate\Support\Arr;
use Illuminate\Http\Request;
use App\Models\ProductVisits;
use App\Http\Traits\StoreTraits;
use Illuminate\Http\Client\Pool;
use App\Http\Traits\ClientTraits;
use App\Http\Traits\ProductsTraits;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use App\Helpers\General\CollectionHelper;
use App\Models\TipoModeloUno as Category;
use App\Http\Controllers\ProductsController;

class HomeController extends Controller
{
  use StoreTraits, ProductsTraits, ClientTraits;

  /*
   * @return Stores Favorites
   * @return Categories
   * @return Products
   * @return Stores Recents
   * @return Slider
   */

  public function index(Request $request){

    //dd(Cart::where('CLIENT_NUM',Auth::user()->num)->where('STAT_CD',1000)->orderBy('INSERT_DATE','desc')->get());
    //1026071
    $params = collect($request->all()); 

    $stores_favorites     = $this->stores_favorites($params);


    $category_preferences = Auth::user()->preferences;
    $idsCategoriesPreferences = Arr::pluck($category_preferences,'NUM');
    $idsCategoriesPreferencesString  = implode(',', Arr::pluck($category_preferences,'NUM'));

    $categories = new Category($params);
    $categories = $categories->getCategories();

    $categoriesOrderPreferences = [];


    $stores = new Store($params);
    $stores = $stores->getStores($params);

    foreach ($stores as $key => $store) {
      $products  = new Products(['store'=>$store]);
      $products  = $products->getProductsStore($params);
      $store['products'] = $products;
    }

    foreach ($categories as $key => $category) {
      $products = new Products($params);
      $category['products'] = $products->productsCategories($category['id']);
    }

    $products_visits      = $this->products_visits( $params );
    
    $params['slide_category'] = $params['slide_category'].','.$idsCategoriesPreferencesString;
    $slider = new Slider($params);
    $slider = $slider->getSliders();

    return response()->json([
      'status'           => true, 
      'stores'           => $stores,
      'stores_favorites' => $stores_favorites,
      'categories'       => $categories,
      'products_visits'  => $products_visits,
      'slider'           => $slider,
    ],200);
  }  

  public function  sliders(Request $request){
    $params = collect($request->all());
    $category_preferences = Auth::user()->preferences;
    $idsCategoriesPreferences = Arr::pluck($category_preferences,'NUM');
    $idsCategoriesPreferencesString  = implode(',', Arr::pluck($category_preferences,'NUM'));

    $params['slide_category'] = $params['slide_category'].','.$idsCategoriesPreferencesString;
    $slider = new Slider($params);
    $slider = $slider->getSliders();

    //dd($slider);
    $funcion = function($value){

	 
	    if(stripos($value['url'], '/catalog')!==false){

		   $d = $value['url'];
		   $d = str_replace('/catalog?','',$d);
		   $value['query'] = $this->proper_parse_str($d);
	    }
	    return $value;
    };

    $slider = array_map($funcion, $slider);
    return response()->json($slider,200);
  } 
  public  function proper_parse_str($str) {
    # result array
    $arr = array();

    # split on outer delimiter
    $pairs = explode('&', $str);

    # loop through each pair
    foreach ($pairs as $i) {
      # split into name and value
      list($name,$value) = explode('=', $i, 2);

      # if name already exists
      if( isset($arr[$name]) ) {
        # stick multiple values into an array
        if( is_array($arr[$name]) ) {
          $arr[$name][] = $value;
        }
        else {
          $arr[$name] = array($arr[$name], $value);
        }
      }
      # otherwise, simply stick it in a scalar
      else {
        $arr[$name] = $value;
      }
    }

    # return result array
    return $arr;
  }

  public function get_product_category(Request $request, $category_id){

    $products = new Products($request->all());
    $products = $products->productsCategories($category_id);

    return response()->json(['status' => true, 'products' => $products],200);
  }

  public function productsVisitados(Request $request)
  {
    $products_id = Auth::user()->productsVisits()->limit(10)->orderBy('created_at','desc')->get()->pluck('NUM');
    $p = new ProductsController();
    $productos = $p->whereInProducts($products_id, ['isModels'=> false]);
    return response()->json($productos);
  }

  public function getCategorieSearch($categorie_id , Request $request)
  {
    
    if($request->product_paginate){
      $config['product_paginate'] =  $request->product_paginate;
    }
    if($request->product_for_store){
      $config['product_for_store'] = $request->product_for_store;
    }

    $response = $this->onGetCategorieSearch($categorie_id, $config );
    
    // 
    return response()->json(['stores' => $response['stores'], 'products' => $response['products'] ]);

  }

  public function onGetCategorieSearch($categorie_id, $config = [ 'product_paginate' => 16, 'product_for_store' => 3 ])
  {
    
    $nameChache = 'categorie_'.$categorie_id.'?product_for_store='.$config['product_for_store'];
    if (Cache::has($nameChache)) {
      $data = Cache::get($nameChache);
      return ['stores' => $data['stores'], 'products' => CollectionHelper::paginate(collect($data['products']), $config['product_paginate']) ];
    }

    $categories = [ 1 => 'woman', 3 => 'man', 6 => 'xl', 4 => 'kids', 2 => 'accessories'];
    $prefix = 'cache';
    $categorieName = $categories[$categorie_id];
    $paquetes = ['premium','black','platinum','gold','blue'];

    $rutas = [];
    foreach ($paquetes as $key => $paquete) {
      $rutas[] = 'https://www.modatex.com.ar/modatexrosa3/json/'.$prefix.'_'.$categorieName.'_'.$paquete.'.json';
    }

    $collection = collect($rutas);
    
    $consultas = Http::pool(fn (Pool $pool) => 
      $collection->map(fn ($url) => 
           $pool->accept('application/json')->get($url)
      )
    );

    $stores = [];
    foreach ($paquetes as $key => $value) {
      if(count($consultas[$key]->json()['stores'])){
        foreach ($consultas[$key]->json()['stores'] as $key => $store) {
          $stores[] = $store;
        }
      }
    }

    // dd($stores);
    $stores = collect($stores);

    $storesIds = $stores->pluck('local_cd');

    $rutas = [];
    
    foreach ($storesIds as $key => $id) {
      $rutas[] = 'https://www.modatex.com.ar/modatexrosa3/?c=Products::get&categorie='.$categorieName.'&start=0&length='.$config['product_for_store'].'&store='.$id.'&years=1&sections=&categories=&search=&order=manually';
    }

    $rutas = collect($rutas);

    $response = Http::pool(fn (Pool $pool) => 
      $rutas->map(fn ($url) => 
        $pool->accept('application/json')->get($url)
      )
    );

    $products = [];
    foreach ($rutas->all() as $key => $value) {
      if(count($response[$key]->json()['data'])){
        foreach ($response[$key]->json()['data'] as $p => $product) {
          $products[] = $product;
        }
      }
    }

    $pro = new ProductsController();
    $products = $pro->arregloProduct($products);

    $stores = $stores->map(function($store){
      return [
        'local_cd' => $store['local_cd'],
        'logo'     => 'https://netivooregon.s3.amazonaws.com/'.$store['profile']['logo'],
        'min'      => $store['profile']['min'],
        'name'     => $store['cover']['title'],
      ];
    });

    Cache::put($nameChache, ['stores' => $stores, 'products' => $products] , $seconds = 10800);

    return ['stores' => $stores, 'products' => CollectionHelper::paginate(collect($products), $config['product_paginate']) ];
  }

  public function getBloques()
  {

    $products = new ProductsController();

    return [
      [
        'name' => 'Mujer',
        'type' => 'categorie',
        
        'value' => 1,
        'config' => [
          'slider' => true,
          'is_title' => false,
          'is_card' => false,
        ],
        'products' => collect($this->onGetCategorieSearch(1, ['product_paginate' => 4, 'product_for_store' => 1])['products'])->all()['data'],
      ],
      [
        'name' => 'Hombre',
        'type' => 'categorie',
        
        'value' => 3,
        'products' => collect($this->onGetCategorieSearch(3, ['product_paginate' => 4, 'product_for_store' => 1])['products'])->all()['data']
      ],
      [
        'name' => 'Talle Especial',
        'type' => 'categorie',
        
        'value' => 6,
        'products' => collect($this->onGetCategorieSearch(6, ['product_paginate' => 4, 'product_for_store' => 1])['products'])->all()['data'],
      ],
      [
        'type' => 'promotion',
        'value' => 'valor de busqueda',
        'images' => [
          'https://netivooregon.s3.amazonaws.com/modatexrosa2/img/logo/minimos-promos3.gif',
          'https://netivooregon.s3.amazonaws.com/modatexrosa2/img/logo/enviogratis-promos3.gif?n'
        ]
      ],
      [
        'name' => 'Niños',
        'type' => 'categorie',
        
        'value' => 4,
        'products' => collect($this->onGetCategorieSearch(4, ['product_paginate' => 4, 'product_for_store' => 1])['products'])->all()['data']
      ],
      [
        'name' => 'Accesorios',
        'type' => 'categorie',
        
        'value' => 2,
        'products' => collect($this->onGetCategorieSearch(2, ['product_paginate' => 4, 'product_for_store' => 1])['products'])->all()['data'],
      ],
      [
        'name' => 'Zapatos',
        'type' => 'filter',
        
        'value' => 'zapatos',
        'products' => $products->onGetSearch([
          'menu' => 'get_catalog_products',
          'date' => Carbon::now()->format('Y-m-d'),
          'type' => 'search-box-input',
          'sections' => [],
          'search'=> 'zapatos',
          'page' => 1,
          'offset' => 4
        ])
      ],
      [
        'name' => 'Remeras',
        'type' => 'filter',
        
        'value' => 'remeras',
        'products' => $products->onGetSearch([
          'menu' => 'get_catalog_products',
          'date' => Carbon::now()->format('Y-m-d'),
          'type' => 'search-box-input',
          'sections' => [],
          'search'=> 'remeras',
          'page' => 1,
          'offset' => 4
        ])
      ],
    ];
  }

  public function statesGet()
  {
    return response()->json(States::select('NUM AS id','STATE_NAME AS name')
    ->where('STAT_CD',1000)->orderBy('name','asc')->get());
  }

}
