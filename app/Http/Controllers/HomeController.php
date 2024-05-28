<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Cart;
use App\Models\Store;
use App\Models\Slider;
use App\Models\States;
use App\Models\Favorite;
use App\Models\PagesCms;
use App\Models\Products;
use Illuminate\Support\Arr;
use App\Models\StoresVisits;
use Illuminate\Http\Request;
use App\Models\ProductVisits;
use App\Http\Traits\StoreTraits;
use Illuminate\Http\Client\Pool;
use App\Http\Traits\ClientTraits;
use App\Http\Traits\ProductsTraits;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use App\Http\Controllers\Objects\Marca;
use App\Helpers\General\CollectionHelper;
use App\Models\TipoModeloUno as Category;
use App\Http\Controllers\ProductsController;
use Illuminate\Support\Facades\Log;

class HomeController extends Controller
{
  use StoreTraits, ProductsTraits, ClientTraits;

  /**
   * DEPRECADO
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

  /**
   * GET SLIDERS
   */
  public function  sliders(Request $request){

      $response = Http::acceptJson()->get('https://www.modatex.com.ar/?c=SlidesApp::getAllSlides');

      $sliders = collect($response->json())->collapse()->map(function($item){
       
        if($item['APP_URL_JSON'] == ''){
          return [
            "img" => "https://netivooregon.s3.amazonaws.com/common/img/main_slide/1.1/". $item['IMG_APP_PATH'],
            "title" => "",
            "redirect" => ""
          ] ; 
        }
        $redirect = json_decode($item['APP_URL_JSON'])->redirect;
        $redirect->route = str_replace('/','',$redirect->route);

        switch ($redirect->route) {
          case 'categories':
            $redirect->route = 'categorie';
            break;
          
          default:
            # code...
            break;
        }
        return [
          "img" => "https://netivooregon.s3.amazonaws.com/common/img/main_slide/1.1/". $item['IMG_APP_PATH'],
          "title" => "",
          "redirect" => $redirect
        ] ; 
      });

      // $sliders[] = [
      //   "img" => "https://netivooregon.s3.amazonaws.com/common/img/main_slide/1.1/17137931221713375144cupones-extra-slide-app.webp_app.webp",
      //   "title" => "",
      //   "redirect" => []
      // ];

      return response()->json( $sliders);

    // $params = collect($request->all());
    // $category_preferences = Auth::user()->preferences;
    // $idsCategoriesPreferences = Arr::pluck($category_preferences,'NUM');
    // $idsCategoriesPreferencesString  = implode(',', Arr::pluck($category_preferences,'NUM'));

    // $params['slide_category'] = $params['slide_category'].','.$idsCategoriesPreferencesString;
    // $slider = new Slider($params);
    // $slider = $slider->getSliders();

    // //dd($slider);
    // $funcion = function($value){

	 
	  //   if(stripos($value['url'], '/catalog')!==false){

		//    $d = $value['url'];
		//    $d = str_replace('/catalog?','',$d);
		//    $value['query'] = $this->proper_parse_str($d);
	  //   }
	  //   return $value;
    // };

    // $slider = array_map($funcion, $slider);
    // return response()->json($slider,200);
  } 

  private  function proper_parse_str($str) {
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

  /**
   * 
   */
  public function get_product_category(Request $request, $category_id){

    $products = new Products($request->all());
    $products = $products->productsCategories($category_id);

    return response()->json(['status' => true, 'products' => $products],200);
  }

  /**
   * GET PRODUCTOS VISITADOS
   */
  public function productsVisitados(Request $request)
  {
    $products_id = Auth::user()->productsVisits()->limit(10)->orderBy('created_at','desc')->get()->pluck('NUM');
    $p = new ProductsController();
    $productos = $p->whereInProducts($products_id, ['isModels'=> false]);
    return response()->json($productos);
  }

  /**
   * GET BUSCADOR CATEGORIAS
   */
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

  /**
   * GET BUSCADOR CATEGORIAS
   */
  public function onGetCategorieSearch($categorie_id, $config = [ 'product_paginate' => 16, 'product_for_store' => 3 ])
  {
    // dd('si');
    $nameChache = 'categorie_'.$categorie_id.'?product_for_store='.$config['product_for_store'];
    if (Cache::has($nameChache)) {
      $data = Cache::get($nameChache);
      return ['stores' => $data['stores'], 'products' => CollectionHelper::paginate(collect($data['products']), $config['product_paginate']) ];
    }

    $categories = [ 
      1 => 'woman', 
      2 => 'accessories',
      3 => 'man', 
      4 => 'kids', 
      6 => 'xl', 
      7 => 'sportive',
      8 => 'lingerie',
      9 => 'shoes',
      10 => 'home',
    ];
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
      if(isset($consultas[$key]->json()['stores']) && count($consultas[$key]->json()['stores'])){
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
    //  dd($store);
    //   $storeq = new StoresController();
    //   $predefSection = $storeq->categorieDefaultId($store->toArray());
      return [
        'local_cd' => $store['local_cd'],
        'logo'     => 'https://netivooregon.s3.amazonaws.com/'.$store['profile']['logo'],
        'min'      => $store['profile']['min'],
        'name'     => $store['cover']['title'],
        // "category_defau lt" => $predefSection
      ];
    });

    Cache::put($nameChache, ['stores' => $stores, 'products' => $products] , $seconds = 10800);

    return ['stores' => $stores, 'products' => CollectionHelper::paginate(collect($products), $config['product_paginate']) ];
  }

  /**
   * GET BUSCADOR PRODUCTOS SEGUN CATEGORIAS
   */
  public function productsCategorie($config){
    
    $products = new ProductsController();
    $storep = new StoresController();

    return $storep->consultaStoresRosa($config['store'])
    ->shuffle()
    ->take($config['store']['limit'] ?? 3)
    ->map(function($store) use($products, $config){
      return $products->onGetSearch([
        'start' => 0,
        'length' => $config['products']['length'],
        'store' => $store['local_cd'],
        'years' => 1,
        'order' => 'manually',
        'no_product_id' => null,
        'daysExpir' => 365,
        // 'sections' => 'woman',
      ]); 
    })
    ->collapse()
    ;
  }

  /**
   * GET BLOQUES
   */
  public function getBloques()
  {

    // return $this->generarCacheBloquesHome();
    // Cache::put($nameChache, $data , $seconds = 10800);

    // return response()->json(['bloques' => Cache::get('bloqueshome'), 'imagenes' => Cache::get('bloqueshomeImagenes')]);

    return Cache::get('bloqueshome');

  }

  private function utf8_decode_recursive($mixed) {
    if (is_array($mixed)) {
        foreach ($mixed as $key => $value) {
            $mixed[$key] = $this->utf8_decode_recursive($value);
        }
    } elseif (is_object($mixed)) {
        $vars = get_object_vars($mixed);
        foreach ($vars as $key => $value) {
            $mixed->$key = $this->utf8_decode_recursive($value);
        }
    } elseif (is_string($mixed)) {
        // Verificar si la cadena es binaria
        if (strpos($mixed, 'b"') === 0) {
            // Eliminar el prefijo 'b"' y decodificar la cadena binaria
            $mixed = substr($mixed, 2); // Elimina el prefijo 'b"'
            $mixed = $mixed; // Decodificar la cadena binaria
        } else {
            // La cadena no es binaria, decodificarla como UTF-8
            $mixed = $mixed;
        }
    }
    return $mixed;
}


  private function coding($json_text){


    // Función para aplicar utf8_decode recursivamente
   
    // dd($json_text);
    // Decodificar el JSON
    $data = json_decode($json_text);
    // Aplicar utf8_decode a los valores de cadena
    $data = $this->utf8_decode_recursive($data);
    
    // Codificar el objeto PHP como JSON nuevamente
    $json_output = json_encode($data);
    // dd($json_output, $data);

    // Imprimir el resultado
    return $json_output;
  }
  /**
   * GET PROMOCIONES
   */
  public function getPromociones()
  {
    try {

      $pagePromotion = PagesCms::where('isPromotionApp','!=','0000-00-00 00:00:00')->whereNotNull('isPromotionApp')->orderBy('last_updated', 'desc')->first();
      $cmsCarouselHome = PagesCms::where('isHomeApp','!=','0000-00-00 00:00:00')->whereNotNull('isHomeApp')->orderBy('last_updated','desc')->orderBy('id','desc')->get();

      $modal = PagesCms::where('isModal','!=','0000-00-00 00:00:00')->whereNotNull('isModal')->first();

     try {
      $carousel_home = $cmsCarouselHome->map(function($item){
        
         try {
          // $editor = utf8_encode(utf8_decode($item['data_json']));
          $editor = $this->coding($item['data_json']);
          // $editor = $item['data_json'];
         } catch (\Throwable $th) {
           $editor = '';
         }

        return [
          'id' => $item['id'],
          'url' => $item['url_banner'],
          'name' => $item['title_app'],
          'editor' => $editor,
        ];
      });
     } catch (\Throwable $th) {
      return [];
     }

      return [
        'carousel_home' => $carousel_home,
        'promotion_page' => collect([$pagePromotion])->map(function($item){
          return [
            'name' => $item['title_app'],
            'editor' => $this->coding($item['data_json'])
          ];
        })->first(),
        'modal' => $modal ? [
          'editor' => $this->coding($modal->data_json),
          'status' => true,
          'title' => $modal->title_app ? $modal->title_app: $modal->title
        ] : ['editor' => '', 'status' => false, 'title' => '']
      ];

    } catch (\Throwable $th) {
      return [];
    }
  }

  /**
   * GET CATEGORIAS
   */
  public function getCategories()
  {

    $pageDeportivo = PagesCms::where('isCategoryApp','!=','0000-00-00 00:00:00')->whereNotNull('isCategoryApp')->orderBy('last_updated','desc')->orderBy('id','desc')->get();

    // $pages = $pageDeportivo->map(function($item){

    //   try {
    //     $editor = $this->coding($item['data_json']);
    //   } catch (\Throwable $th) {
    //     $editor = '';
    //   }
    //   return [
    //     'id' => $item['id'],
    //     'name' => $item['title_app'] ?  utf8_decode($item['title_app']) : utf8_decode($item['title']),
    //     'editor' => $editor,
    //     'key' =>  'page', 
    //     'type' =>  'page',
    //     'icon' =>  $item['url_icono'],
    //     'color' =>  "",
    //     'colSpan' =>  3,
    //     'col' =>  0,
    //     'row' =>  0,
    //     'left' =>  100,
    //   ];
    // });
    
    $items = [
      [
        'id' => 1,
        'name' => 'Mujer',
        'key' =>  'woman', 
        'icon' => 'res://woman',
        'color' =>  "",
        'colSpan' =>  3,
        'col' =>  0,
        'row' =>  0,
        'left' =>  100,
      ],
      [
        'id' => 3,
        'name' => 'Hombre',
        'key' =>  'man', 
        'icon' => 'res://men',
        'color' =>  "",
        'colSpan' =>  3,
        'col' =>  3,
        'row' =>  0,
        'left' =>  100,
      ],
      [
        'id' => 6,
        'name' => 'Talle especial',
        'key' =>  'xl', 
        'icon' => 'res://label',
        'color' =>  "",
        'colSpan' =>  2,
        'col' =>  0,
        'row' => 1,
        'left' =>  35,
        'top' =>  20
      ],
      [
        'id' => 4,
        'name' => 'Niños',
        'key' =>  'kids', 
        'icon' => 'res://baby',
        'color' =>  "",
        'colSpan' =>  2,
        'col' =>  2,
        'row' =>  1,
        'left' =>  35,
        'top' =>  20
      ],
      [
        'id' => 2,
        'name' => 'Accesorios',
        'key' =>  'accessories', 
        'icon' => 'res://accessories',
        'color' =>  "",
        'colSpan' =>  2,
        'col' =>  4,
        'row' =>  1,
        'left' =>  35,
        'top' =>  20
      ],
      // [
      //   'id' => 0,
      //   'name' => 'Calzado',
      //   'type' =>  'search',
      //   'search' =>  'zapatos',
      //   'key' =>  'zapatos', 
      //   'icon' => 'res://shoes',
      //   'color' =>  "",
      //   'colSpan' =>  2,
      //   'col' =>  4,
      //   'row' =>  1,
      //   'left' =>  35,
      //   'top' =>  20
      // ],
      [
        'id' => 0,
        'name' => 'Remeras',
        'type' =>  'search',
        'search' =>  'remera',
        'key' =>  'tshitr', 
        'icon' => 'res://tshirt',
        'color' =>  "",
        'colSpan' =>  2,
        'col' =>  4,
        'row' =>  1,
        'left' =>  35,
        'top' =>  20
      ],
      // [
      //   'id' => 7,
      //   'name' => 'Deportiva',
      //   'key' =>  'sportive', 
      //   'icon' => 'res://sportive',
      //   'color' =>  "",
      //   'colSpan' =>  3,
      //   'col' =>  0,
      //   'row' =>  0,
      //   'left' =>  100,
      // ],
    ];

    return $items;
    // return array_merge($items, $pages->toArray());
  }

  /**
   * GET STATES
   */
  public function statesGet()
  {
    return response()->json(States::select('NUM AS id','STATE_NAME AS name')
    ->where('STAT_CD',1000)->orderBy('name','asc')->get());
  }

  /**
   * GET MENU
   */
  public function menuList()
  {

    $pagesMenuCMS = PagesCms::where('isMenuApp','!=','0000-00-00 00:00:00')->whereNotNull('isMenuApp')->orderBy('last_updated')->get();

    // $pagesMenu = $pagesMenuCMS->map(function($item){
    //   try {
    //     $editor = $this->coding($item['data_json']);
    //   } catch (\Throwable $th) {
    //     $editor = '';
    //   }

    //   return [
    //     "icon" => $item['url_icono'], #'~/assets/icons/icon_menu_3.png',
    //     "name" => $item['title_app'] ?  utf8_decode($item['title_app']) : utf8_decode($item['title']),
    //     "disabled" => $item['status'] == 1 ? false: true ,
    //     'editor' => $editor
    //   ];
    // });


    $itemsMenu = [
      [
        "icon" => '~/assets/icons/home.png',
        "name" => 'Inicio',
        "disabled" => false,
        "redirect"=> [
          "route"=> "/home",
          "params"=> []
        ]
      ],
      [
        "icon" => '~/assets/icons/icon_menu_0.png',
        "name" => 'Tiendas',
        "disabled" => false,
        "redirect"=> [
          "route"=> "/all_stores",
          "params"=> []
        ]
      ],
      [
        "icon" => '~/assets/icons/icon_menu_099.png',
        "name" => 'Mis pedidos',
        "disabled" => false,
        "redirect"=> [
          // "route"=> "/profileOrdersList",
          "route"=> "/profile",
          "params"=> []
        ]
      ],
      [
        "icon" => '~/assets/icons/icon_menu_5.png',
        "name" => 'Notificaciones',
        "disabled" => false,
        "redirect"=> [
          "route"=> "/notifications",
          "params"=> []
        ]
      ],
      [
        "icon" => '~/assets/icons/new.png',
        "name" => 'Nuevos Ingresos',
        "disabled" => false,
        "childrens" => [
          [
            "icon" => '~/assets/icons/past.png',
            "name" => "Hoy",
            "redirect" => [
              "route" => "/search",
              "params" => [
                "betweenDates" => \Carbon\Carbon::now()->format('Y-m-d').','.\Carbon\Carbon::now()->addDays(1)->format('Y-m-d'),
                "order" => 'register DESC',
                "search" => ''
              ]
            ]
          ],
          [
            "icon" => '~/assets/icons/past.png',
            "name" => "Ayer",
            "redirect" => [
              "route" => "/search",
              "params" => [
                "betweenDates" => \Carbon\Carbon::now()->subDays(1)->format('Y-m-d').','.\Carbon\Carbon::now()->format('Y-m-d'),
                "order" => 'register DESC',
                "search" => ''
              ]
            ]
          ],
          [
            "icon" => '~/assets/icons/past.png',
            "name" => "Antes de Ayer",
            "redirect" => [
              "route" => "/search",
              "params" => [
                "betweenDates" => \Carbon\Carbon::now()->subDays(2)->format('Y-m-d').','.\Carbon\Carbon::now()->subDays(1)->format('Y-m-d'),
                "order" => 'register DESC',
                "search" => ''

              ]
            ]
          ],
          [
            "icon" => '~/assets/icons/past.png',
            "name" => "Hace 3 dias",
            "redirect" => [
              "route" => "/search",
              "params" => [
                "betweenDates" => \Carbon\Carbon::now()->subDays(3)->format('Y-m-d').','.\Carbon\Carbon::now()->subDays(2)->format('Y-m-d'),
                "order" => 'register DESC',
                "search" => ''

              ]
            ]
          ],
          [
            "icon" => '~/assets/icons/past.png',
            "name" => "Hace 4 dias",
            "redirect" => [
              "route" => "/search",
              "params" => [
                "betweenDates" => \Carbon\Carbon::now()->subDays(4)->format('Y-m-d').','.\Carbon\Carbon::now()->subDays(3)->format('Y-m-d'),
                "order" => 'register DESC',
                "search" => ''

              ]
            ]
          ],
        ]
      ],
      [
        "icon" => 'res://heart_gray',
        "name" => 'Mis marcas favoritas',
        "disabled" => false,
        "editor" => $this->getMarcasFavoritas()
      ],
      [
        "icon" => 'res://history',
        "name" => 'Historial',
        "disabled" => false,
        "redirect"=> [
          "route"=> "/search",
          "products" => collect(Cache::get('visitados'.Auth::user()->num))->values()->all(),
          "params"=> [],

        ]
      ],
    ];

    return $itemsMenu;
    // return array_merge($itemsMenu, $pagesMenu->toArray());

  }

  private function getMarcasFavoritas()
  {

        $response = Http::withHeaders([
            'x-api-key' => Auth::user()->api_token,
        ])
        ->asForm()
        ->acceptJson()
        ->get('https://www.modatex.com.ar?c=Favorites::added');


        if(!isset($response->collect()['data']))
        {
          return [];
        }

        $tiendas = collect($response->collect()['data']);

        $blocks = [];

        $marcasBlock = $tiendas->map(function($tienda){
          // $tienda['sections'] = json_decode($tienda['sections']);
          $tienda['logo'] =  env('URL_IMAGE').'/common/img/logo/'. $tienda['logo'];
          $tienda['col'] = 2;
          $tienda['offset'] = 0;
          return $tienda;
        });

        $blocks[] = $this->constructorBloque([
          'type' => 'Marcas',
          'data' => ['marcas' => $marcasBlock ]
        ]);

    return json_encode($this->constructorObjectPage($blocks)) ;

  }

  private function constructorBloque($params)
  {
    return [
      "id" => 'GuUPhCjs1F',
      "type" => $params['type'],
      "data" => $params['data'],
      "tunes" => [
        "categoriaTune"=>[
            "ocultarApp" => false,
            "ocultarWeb" => false
        ],
        "configTune"=>[
            "expandir" => false,
            "margin"=>[
                "top" => [ "value" => 0,"placeholder" => "Arriba","clave" => "top"],
                "right" => [ "value" => 0,"placeholder" => "Derecha","clave" => "right"],
                "bottom" => [ "value" => 0,"placeholder" => "Abajo","clave" => "bottom"],
                "left" => [ "value" => 0,"placeholder" => "Izquierda","clave" => "left"]
            ]
        ]
      ]
    ];
  }

  private function constructorObjectPage($blocks)
  {
    return [
      "time" => \Carbon\Carbon::now()->timestamp,
      "blocks" => $blocks,
      "version" => "1.0.0"
    ];
  }


  public function generarCacheMarcas()
  {

   
    $response = Http::accept('application/json')->get('https://www.modatex.com.ar/?c=Store::all');

    if(isset($response->json()['data'])){
      Cache::put('stores',$response->json()['data']);
    }

    $storesCache = Cache::get('stores');

    // dd($storesCache);

    if($storesCache){
      $enlaces = collect($storesCache)->filter(function ($value, $key) {
        return ($value['id']!=1006 && $value['id'] != 1365);
    })->map(function($store){
          $store['enlace'] = "https://www.modatex.com.ar/?c=Store::_get&store_ref={$store['id']}";
          return $store;
      })->pluck('enlace');

      $consultas = Http::pool(fn (Pool $pool) => 
        $enlaces->map(fn ($url) => 
            $pool->accept('application/json')->get($url)
        )
      );

      $respuestas = collect($consultas)->map(function($consulta){
        return $consulta->collect()['data'];
      });

      $stores = collect($storesCache)->map(function($store) use ($respuestas){
        $store['more'] = $respuestas->where('LOCAL_CD',$store['id'])->first();
        $store = new Marca($store);
        return $store->getMarca();
      });

      Cache::put('stores',$stores);

      return $stores;
    }
    
  }

  public function generarCacheBloquesHome()
  {
   
    $this->generarCacheMarcas();

    $products = new ProductsController();
    
    $pagesMenuCMS = PagesCms::whereIn('id',[460,458,459, 470])->get();

    $productoMujer = $products->onGetSearch([
                      'start' => 0,
                      'length' => 10,
                      'storeData' => 1,
                      'inStock' => 1,
                      'daysExpir' => 365,
                      'order' => 'date DESC',
                      'sections' => 'woman',
                      'cacheTime' => 1200
    ]);

    $productoHombre = $products->onGetSearch([
      'start' => 0,
      'length' => 10,
      'storeData' => 1,
      'inStock' => 1,
      'daysExpir' => 365,
      'order' => 'date DESC',
      'sections' => 'man',
      'cacheTime' => 1200
    ]);

    $productoXL = $products->onGetSearch([
      'start' => 0,
      'length' => 10,
      'storeData' => 1,
      'inStock' => 1,
      'daysExpir' => 365,
      'order' => 'date DESC',
      'sections' => 'xl',
      'cacheTime' => 1200
    ]);

    $productoNino = $products->onGetSearch([
      'start' => 0,
      'length' => 10,
      'storeData' => 1,
      'inStock' => 1,
      'daysExpir' => 365,
      'order' => 'date DESC',
      'sections' => 'kids',
      'cacheTime' => 1200
    ]);

    $productoaccessories = $products->onGetSearch([
      'start' => 0,
      'length' => 10,
      'storeData' => 1,
      'inStock' => 1,
      'daysExpir' => 365,
      'order' => 'date DESC',
      'sections' => 'accessories',
      'cacheTime' => 1200
    ]);

    $productoZapatos = $products->onGetSearch([
      'start' => 0,
      'length' => 10,
      'search' => 'zapatos',
      'years' => 1,
      'order' => 'manually',
      'no_product_id' => null,
      'daysExpir' => 365,
      // 'sections' => 'woman',
    ]);

    $productoRemeras = $products->onGetSearch([
      'start' => 0,
      'length' => 10,
      'search' => 'remeras',
      'years' => 1,
      'order' => 'manually',
      'no_product_id' => null,
      'daysExpir' => 365,
      // 'sections' => 'woman',
    ]);

    $productoDetacados = $products->destacados([
      'start' => 0,
      'length' => 10,
      'storeData' => 1,
      'inStock' => 1,
      'daysExpir' => 365,
      'order' => 'date DESC',
      'version' => \Carbon\Carbon::now()->timestamp
    ]);

    $productoHoy = $products->onGetSearch([
      'start' => 0,
      'length' => 28,
      'search' => '',
      'years' => 1,
      'order' => 'register DESC',
      'no_product_id' => null,
      'daysExpir' => 365,
      'storeData' => 1,
      'inStock'=> 1,
      'cacheTime' => 1200,
      'sections' => 'woman,man,xl,kids,accessories',
      "betweenDates" => \Carbon\Carbon::now()->format('Y-m-d').','.\Carbon\Carbon::now()->addDays(1)->format('Y-m-d'),
    ]);

    try {
      $imagenes = $productoMujer->pluck('images')->map(function($images){ return collect($images)->first();});
      $imagenes = $imagenes->merge($productoHombre->pluck('images')->map(function($images){ return collect($images)->first();}));
      $imagenes = $imagenes->merge($productoXL->pluck('images')->map(function($images){ return collect($images)->first();}));
      $imagenes = $imagenes->merge($productoNino->pluck('images')->map(function($images){ return collect($images)->first();}));
      $imagenes = $imagenes->merge($productoaccessories->pluck('images')->map(function($images){ return collect($images)->first();}));
      $imagenes = $imagenes->merge($productoZapatos->pluck('images')->map(function($images){ return collect($images)->first();}));
      $imagenes = $imagenes->merge($productoRemeras->pluck('images')->map(function($images){ return collect($images)->first();}));
      $imagenes = $imagenes->merge($productoDetacados->pluck('images')->map(function($images){ return collect($images)->first();}));
      $imagenes = $imagenes->merge($productoHoy->pluck('images')->map(function($images){ return collect($images)->first();}));

      Cache::put('bloqueshomeImagenes',$imagenes);
    } catch (\Throwable $th) {
      //throw $th;
    }
    $data = [
                [
                  'name' => 'Mujer',
                  'type' => 'filter',
                  'value' => 'Mujer',
                  // 'config' => [
                  //   'slider' => true,
                  //   'is_title' => false,
                  //   'is_card' => false,
                  // ],
                  'products' => $productoMujer
                ],
                [
                  'name' => 'Hombre',
                  'type' => 'filter',
                  'value' => 'Hombre',
                  'products' => $productoHombre
                ],
                [
                  'name' => 'Talle Especial',
                  'type' => 'filter',
                  'value' => 'Talle Especial',
                  'products' => $productoXL
                ],
                // [
                //   'type' => 'promotions',
                //   'value' => 'valor de busqueda',
                //   'promotions' => [
                //     $this->getPromociones()[array_search(3, array_column($this->getPromociones(), 'id'))]
                //   ]
                // ],
                [
                  'name' => 'Niños',
                  'type' => 'filter',
                  'value' => 'Niños',
                  'products' => $productoNino,
                ],
                [
                  'name' => 'Accesorios',
                  'type' => 'categorie',
                  'value' => 'Accesorios',
                  'products' => $productoaccessories
                  ,
                ],
                [
                  'name' => 'Zapatos',
                  'type' => 'filter',
                  'value' => 'zapatos',
                  'products' => $productoZapatos
                ],
                [
                  'name' => 'Remeras',
                  'type' => 'filter',
                  'value' => 'remeras',
                  'products' => $productoRemeras
                ],
                [
                  'name' => 'Productos destacados',
                  'type' => 'categorie',
                  'value' => 0,
                  'config' => [
                    'slider' => true,
                    'is_title' => false,
                    'is_card' => false,
                  ],
                  'products' => $productoDetacados
                ],
                [
                  'name' => 'Ingresos de Hoy',
                  'type' => 'search',
                  'value' => '',
                  "redirect" => [
                    "route" => "/search",
                    "params" => [
                      "betweenDates" => \Carbon\Carbon::now()->format('Y-m-d').','.\Carbon\Carbon::now()->addDays(1)->format('Y-m-d'),
                      "order" => 'register DESC',
                      "search" => ''
                    ]
                  ],
                  'config' => [
                    'slider' => false,
                    'is_title' => false,
                    'is_card' => false,
                  ],
                  'products' => $productoHoy
                ],
                // [
                //   'name' => 'modal',
                //   'type' => 'modal',
                //   'editores' => [utf8_decode('{"time":1699560659858,"blocks":[{"id":"UdS0qmWFpd","type":"Portadas","data":{"marcas":[{"id":1880,"name":"BLANCO YABELL","name_id":"BLANCOYABELL","logo":"https://netivooregon.s3.amazonaws.com/common/img/logo/blancoyabell_1554825423.webp","cleaned":"blancoyabell","col":4,"offset":0,"portada_url":"https://netivooregon.s3.amazonaws.com/modatexrosa2/img/logo/yabell-covernuevo-m1.jpg?N6","isViewLogo":false},{"id":2573,"name":"BLANCO PALACE","name_id":"BLANCOPALACE","logo":"https://netivooregon.s3.amazonaws.com/common/img/logo/blancopalace_1682446532.webp","cleaned":"blancopalace","col":4,"offset":0,"portada_url":"https://netivooregon.s3.amazonaws.com/modatexrosa2/img/logo/blancopalace-covernuevo.jpg?n2","isViewLogo":false},{"id":2330,"name":"VIA BLANCO","name_id":"VIABLANCO","logo":"https://netivooregon.s3.amazonaws.com/common/img/logo/viablanco_1622660862.webp","cleaned":"viablanco","col":4,"offset":0,"portada_url":"https://netivooregon.s3.amazonaws.com/modatexrosa2/img/logo/viablanco-covernuevo.jpg?5","isViewLogo":false},{"id":2565,"name":"Somio Sweet Home","name_id":"SOMIOSWEETHOME","logo":"https://netivooregon.s3.amazonaws.com/common/img/logo/somiosweethome_1680008658.webp","cleaned":"somiosweethome","col":4,"offset":0,"portada_url":"https://netivooregon.s3.amazonaws.com/modatexrosa2/img/logo/somio-covernuevo.jpg","isViewLogo":false},{"id":1626,"name":"NEW PORT","name_id":"NEWPORT","logo":"https://netivooregon.s3.amazonaws.com/common/img/logo/newport_1618851261.webp","cleaned":"newport","col":4,"offset":0,"portada_url":"https://netivooregon.s3.amazonaws.com/modatexrosa2/img/logo/newport-covernuevo.jpg?n5","isViewLogo":false},{"id":2031,"name":"ALIPAPA","name_id":"ALIPAPA","logo":"https://netivooregon.s3.amazonaws.com/common/img/logo/alipapa_1633544236.webp","cleaned":"alipapa","col":4,"offset":0,"portada_url":"https://netivooregon.s3.amazonaws.com/modatexrosa2/img/logo/altocabildo-covernuevo.jpg","isViewLogo":false}]},"tunes":{"categoriaTune":{"ocultarApp":false,"ocultarWeb":false},"configTune":{"expandir":false,"margin":{"top":{"value":0,"placeholder":"Arriba","clave":"top"},"right":{"value":0,"placeholder":"Derecha","clave":"right"},"bottom":{"value":0,"placeholder":"Abajo","clave":"bottom"},"left":{"value":0,"placeholder":"Izquierda","clave":"left"}}}}}],"version":"2.28.2"}')]
                // ],
                [
                  'name' => 'Categorías',
                  'type' => 'box_categories',
                  'categories' => $this->getCategories()
                ],
                // [
                //   'name' => '¿Necesitas ayuda?',
                //   'type' => 'card_list_redirect',
                //   'items' => [
                //     [
                //       'name' => '¿Cómo comprar?',
                //       'editor' => $this->coding($pagesMenuCMS->where('id',458)->first()->data_json),
                //       // 'redirect' => []
                //     ],
                //     [
                //       'name' => 'Formas de pago',
                //       'editor' => $this->coding($pagesMenuCMS->where('id',460)->first()->data_json),
                //       // 'redirect' => []
                //     ],
                //     [
                //       'name' => 'Envíos a todo el país',
                //       'editor' => $this->coding($pagesMenuCMS->where('id',459)->first()->data_json),
                //       // 'redirect' => []
                //     ],
                //     [
                //       'name' => 'Políticas de Privacidad',
                //       'editor' => $this->coding($pagesMenuCMS->where('id',470)->first()->data_json),
                //       // 'redirect' => []
                //     ],
                //   ]
                // ]
            ];
    
    
           
    Cache::put('bloqueshome',collect($data));
    
    return Cache::get('bloqueshome');
  }

  public function saveToken(Request $request)
  {
    return response()->json($request->all());
  }

}
