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
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Facades\JWTFactory;
use Tymon\JWTAuth\Exceptions\JWTException;
use Illuminate\Support\Facades\Storage;

class HomeController extends Controller
{
  use StoreTraits, ProductsTraits, ClientTraits;


  private $url = 'https://www.modatex.com.ar/';

  public function pruebaWebview(Request $request){
    \Log::info('================LLEGA================');
    \Log::info($request->all());

    // $validated = $request->validate([
    //     'propiedad_campo' => 'file|size:1000000',
    //     'extension' => 'required',
    // ]);

    // if ($validated->fails()) {
    //     return response()->json($validated->errors()  ,422);

    // }
              // try {
        if ($request->hasFile('propiedad_campo')) {
            $file = $request->file('propiedad_campo');
            $fileInfo = pathinfo($file->getClientOriginalName());

            \Log::info($fileInfo);

            $extension = isset($fileInfo['extension']) ? $fileInfo['extension'] : (isset($request->extension) ? $request->extension : '');


            $filename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);


            $newFilename = time() . '_' . $filename . '.' . $extension;
            $file->move('comprobante', $newFilename);

            return response()->json(['file' => $newFilename, 'extension' => $extension], 200);
        }
    // } catch (\Throwable $th) {
    //     \Log::info($th->getMessage());
    //     return response()->json($th->getMessage()  ,422);

    // }



   // dd($request->all());
  }

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

  public function test()
  {
    $editor = PagesCms::where('id', 516)->first();

    $json = json_decode($editor->data_json);
    $blocks = collect($json->blocks);

    dd($blocks->where('type','Marcas'));
    // $editor->data_json
    return response()->json($editor->data_json);
  }

  /**
   * GET SLIDERS
   */
  public function  sliders(Request $request){

      $response = Http::acceptJson()->get('https://www.modatex.com.ar/?c=SlidesApp::getAllSlides');

      // dd($response->json());
      // dd($response->body());
      $datos = $response->json();
      $mujer = (isset($datos) && isset($datos['Mujer'])) ? $datos['Mujer'] : [];
      $sliders = collect($mujer)->map(function($item){

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
          case 'page':
            if($redirect->params->id){
              $editor = PagesCms::where('id', $redirect->params->id)->first();

              if($editor){
                $redirect->params->name = $editor->title_app ? $editor->title_app : $editor->title;
                $redirect->params->editor = $editor->data_json;
              }
            }
            break;
          default:
            # code...
            break;
        }

        // dd($redirect);
        return [
          "img" => "https://netivooregon.s3.amazonaws.com/common/img/main_slide/1.1/". $item['IMG_APP_PATH'],
          "title" => "",
          "redirect" => $redirect
        ] ;
      });


      // $sliders[] = [
      //   "img" => "https://netivooregon.s3.amazonaws.com/common/img/main_slide/1.1/17137931221713375144cupones-extra-slide-app.webp_app.webp",
      //   "title" => "",
      //   // "redirect" => [],
      //   'editor' => PagesCms::where('id',487)->first()->data_json,
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

  public function getCategorieFilter()
  {
      $response = Http::acceptJson()->get($this->url.'?c=Products::categories');

      $data = $response->collect();

      $data = $data->map( function($seccion){
        $seccion['sections'] = explode(',',$seccion['section_id']);

        $seccion['categories'] = collect($seccion['categories'])->map(function($subcategoria){
          $subcategoria['amount'] = intval($subcategoria['amount']);
          return $subcategoria;
        });
        return $seccion;
      });

      return response()->json($data);
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
    ->collapse();
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
          $editor = $item['data_json'];
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

    $pages = $pageDeportivo->map(function($item){

      try {
        $editor = $item['data_json'];
      } catch (\Throwable $th) {
        $editor = '';
      }
      return [
        'id' => $item['id'],
        'name' => $item['title_app'] ? $item['title_app'] : $item['title'],
        // 'editor' => $editor,
        'key' =>  $item['key_category_app'],
        'type' =>  'page',
        'icon' =>  $item['url_icono'],
        'icon_image' => $item['url_icono'],
        'icon_image_asset' => 'assets/icons/woman.png',
        'color' =>  "",
        'colSpan' =>  3,
        'col' =>  0,
        'row' =>  0,
        'left' =>  100,
        'redirect' => [
          'route' => '/page',
          'params' => [ 'id' => $item['id'], 'editor' => $editor, 'name' => $item['title_app'] ? $item['title_app'] : $item['title'],]
        ]
      ];
    });

    $items = [
      [
        'id' => 1,
        'name' => 'Mujer',
        'type' =>  'products',
        'key' =>  'woman',
        'icon' => 'res://woman',
        'icon_image' => 'woman.png',
        'icon_image_asset' => '/assets/icons/woman.png',
        'color' =>  "",
        'colSpan' =>  3,
        'col' =>  0,
        'row' =>  0,
        'left' =>  100,
        'redirect' => [
          'route' => '/categories',
          'params' => [ 'woman' ]
        ]
      ],
      [
        'id' => 3,
        'name' => 'Hombre',
        'type' =>  'products',
        'key' =>  'man',
        'icon' => 'res://men',
        'icon_image' => 'men.png',
        'icon_image_asset' => 'assets/icons/men.png',
        'color' =>  "",
        'colSpan' =>  3,
        'col' =>  3,
        'row' =>  0,
        'left' =>  100,
        'redirect' => [
          'route' => '/categories',
          'params' => [ 'man' ]
        ]
      ],
      [
        'id' => 6,
        'name' => 'Talle especial',
        'type' =>  'products',
        'key' =>  'xl',
        'icon' => 'res://label',
        'icon_image' => 'label.png',
        'icon_image_asset' => 'assets/icons/label.png',
        'color' =>  "",
        'colSpan' =>  2,
        'col' =>  0,
        'row' => 1,
        'left' =>  35,
        'top' =>  20,
        'redirect' => [
          'route' => '/categories',
          'params' => [ 'xl' ]
        ]
      ],
      [
        'id' => 4,
        'name' => 'Niños',
        'key' =>  'kids',
        'type' =>  'products',
        'icon' => 'res://baby',
        'icon_image' => 'baby.png',
        'icon_image_asset' => 'assets/icons/baby.png',
        'color' =>  "",
        'colSpan' =>  2,
        'col' =>  2,
        'row' =>  1,
        'left' =>  35,
        'top' =>  20,
        'redirect' => [
          'route' => '/categories',
          'params' => [ 'kids' ]
        ]
      ],
      [
        'id' => 2,
        'name' => 'Accesorios',
        'key' =>  'accessories',
        'type' =>  'products',
        'icon' => 'res://accessories',
        'icon_image' => 'accessories.png',
        'icon_image_asset' => 'assets/icons/accessories.png',
        'color' =>  "",
        'colSpan' =>  2,
        'col' =>  4,
        'row' =>  1,
        'left' =>  35,
        'top' =>  20,
        'redirect' => [
          'route' => '/categories',
          'params' => [ 'accessories' ]
        ]
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
        'type' =>  'products',
        'search' =>  'remera',
        'key' =>  'tshitr',
        'icon' => 'res://tshirt',
        'icon_image' => 'tshirt.png',
        'icon_image_asset' => 'assets/icons/tshirt.png',
        'color' =>  "",
        'colSpan' =>  2,
        'col' =>  4,
        'row' =>  1,
        'left' =>  35,
        'top' =>  20,
        'redirect' => [
          'route' => '/search',
          'params' => [
            'search' => 'remera',
            'section' => ['woman']
          ]
        ]
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

    // return $items;
    return array_merge($items, $pages->toArray());
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
  public function menuList(Request $request)
  {

    $isMenuTemporal = isset($request->menu) && $request->menu == 'temporal' ? true : false;

    $pagesMenuCMS = PagesCms::where('isMenuApp','!=','0000-00-00 00:00:00')->whereNotNull('isMenuApp')->orderBy('last_updated')->get();

    $pagesMenu = $pagesMenuCMS->map(function($item) use($isMenuTemporal){
      try {
        $editor = $this->coding($item['data_json']);
      } catch (\Throwable $th) {
        $editor = '';
      }

      return [
        "icon" => $item['url_icono'], #'~/assets/icons/icon_menu_3.png',
        "name" => $item['title_app'] ?  $item['title_app'] : $item['title'],
        "disabled" => $item['status'] == 1 ? false: true ,
        'editor' => $editor,
        "redirect"=> $isMenuTemporal ? [
          "route"=> "/page",
          "params"=> [
            'name' =>  $item['title_app'] ?  $item['title_app'] : $item['title'],
            'editor' =>  $editor,
          ]
        ] : null
      ];
    });

    $itemsMenu = [
      [
        "icon" => $isMenuTemporal ? 'home' : '~/assets/icons/home.png',
        "name" => 'Inicio',
        "disabled" => false,
        "redirect"=> [
          "route"=> $isMenuTemporal ? '/' : "/home",
          "params"=> []
        ]
      ],
      [
        "icon" => $isMenuTemporal ? 'store' : '~/assets/icons/icon_menu_0.png',
        "name" => 'Tiendas',
        "disabled" => false,
        "redirect"=> [
          "route"=> $isMenuTemporal ? '/stores' : "/all_stores",
          "params"=> []
        ]
      ],
      [
        "icon" =>  $isMenuTemporal ? 'shopping_bag' :'~/assets/icons/icon_menu_099.png',
        "name" => 'Mis pedidos',
        "disabled" => false,
        "redirect"=> [
          "route"=> $isMenuTemporal ? '/pedidos' :  "/profileOrdersList",
          "params"=> []
        ]
      ],
      [
        "icon" => $isMenuTemporal ? 'notifications' :'~/assets/icons/icon_menu_5.png',
        "name" => 'Notificaciones',
        "disabled" => false,
        "redirect"=> [
          "route"=> "/notifications",
          "params"=> []
        ]
      ],
      [
        "icon" => $isMenuTemporal ? 'new_releases' : '~/assets/icons/new.png',
        "name" => 'Nuevos Ingresos',
        "disabled" => false,
        "childrens" => [
          [
            "icon" => $isMenuTemporal ? 'access_time' : '~/assets/icons/past.png',
            "name" => "Hoy",
            "redirect" => [
              "route" => "/search",
              "params" => [
                "betweenDates" => "hoy",
                "order" => 'register DESC',
                "search" => ''
              ]
            ]
          ],
          [
            "icon" => $isMenuTemporal ? 'access_time' :  '~/assets/icons/past.png',
            "name" => "Ayer",
            "redirect" => [
              "route" => "/search",
              "params" => [
                "betweenDates" => "ayer",
                "order" => 'register DESC',
                "search" => ''
              ]
            ]
          ],
          [
            "icon" => $isMenuTemporal ? 'access_time' :  '~/assets/icons/past.png',
            "name" => "Antes de Ayer",
            "redirect" => [
              "route" => "/search",
              "params" => [
                "betweenDates" => "antesdeayer",
                "order" => 'register DESC',
                "search" => ''

              ]
            ]
          ],
          [
            "icon" =>  $isMenuTemporal ? 'access_time' : '~/assets/icons/past.png',
            "name" => "Hace 3 dias",
            "redirect" => [
              "route" => "/search",
              "params" => [
                "betweenDates" =>"3dias",
                "order" => 'register DESC',
                "search" => ''

              ]
            ]
          ],
          [
            "icon" => $isMenuTemporal ? 'access_time' : '~/assets/icons/past.png',
            "name" => "Hace 4 dias",
            "redirect" => [
              "route" => "/search",
              "params" => [
                "betweenDates" => "4dias",
                "order" => 'register DESC',
                "search" => ''

              ]
            ]
          ],
        ]
      ],
      [
        "icon" => $isMenuTemporal ? 'favorite' : 'res://heart_gray',
        "name" => 'Mis marcas favoritas',
        "disabled" => false,
        "editor" => $this->getMarcasFavoritas(), 
        "redirect"=> $isMenuTemporal ? [
          "route"=> "/page",
          "params"=> [
            'editor' => $this->getMarcasFavoritas(),
            'name' => 'Marcas favoritas',
          ]
        ] : null
      ],
      [
        "icon" => $isMenuTemporal ? 'history' : 'res://history',
        "name" => 'Historial',
        "disabled" => false,
        "redirect"=> [
          "route"=> "/search",
          "products" => collect(Cache::get('visitados'.Auth::user()->num))->values()->all(),
          "params"=> [],

        ]
      ],
    ];

      $itemProfileTemporal = [
        "icon" => 'user',
        "name" => 'Perfil',
        "disabled" => false,
        "redirect"=> [
          "route"=> "/profile",
          "params"=> []
        ]
      ];

    // return $itemsMenu;
    $result = array_merge($itemsMenu, $pagesMenu->toArray());
    array_splice($result, 1, 0, [$itemProfileTemporal]);
    return $result;

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
          'data' => [ 'marcas' => $marcasBlock , 'marcasBuscadorData' => []]
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
    $response = Http::accept('application/json')->get($this->url.'?c=Store::all');

    // dd($response->collect()['data'][0]);

    if(isset($response->json()['data'])){
      Cache::put('stores',$response->json()['data']);
    }

    $storesCache = Cache::get('stores');

    if($storesCache){

      $enlaces = collect($storesCache)->map(function($store){
          $store['enlace'] = $this->url."?c=Store::_get&store_ref={$store['id']}";
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
        if($store['id'] == 2000){
          // dd($store);
        }
        $store = new Marca($store);
        return $store->getMarca();
      });

      $planes = Marca::$allowedPlanes;

      $stores = $stores->sortBy(function ($item) use ($planes) {
          $position = array_search($item['paquete'], $planes);
          return $position === false ? PHP_INT_MAX : $position;
      })->values();

      Cache::put('stores',$stores);

      return $stores;
    }
  }

  public function validatoken(Request $request)
  {
    return true;
  }

  public function generarCacheBloquesHome()
  {

   $f = $this->generarCacheMarcas();

    $products = new ProductsController();

    $pagesMenuCMS = PagesCms::whereIn('id',[479,458,459, 470, 481])->get();

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

    $data = [
                [
                  "name" => "Líderes Modatex",
                  "type" => "banners_animated_change",
                  "value" => "",
                  "items" => [
                      [
                          "textos" => [
                              "head" => [
                                  "text" => "Nueva Temporada",
                                  "size" => 9,
                                  "color" => "#FFFFFF"
                              ],
                              "body" => [
                                  "text" => "Sweaters $13.500",
                                  "size" => 12,
                                  "color" => "#FFFFFF"
                              ],
                              "footer" => [
                                  "text" => "Ver tienda",
                                  "size" => 9.0,
                                  "color" => "#FFFFFF"
                              ]
                          ],
                          "color" => "#000000",
                          "portada" => "https://netivooregon.s3.us-west-2.amazonaws.com/modatexrosa2/img/logo/hesed-app-premium.png",
                          "store" => [
                              "logo" => "https://netivooregon.s3.amazonaws.com/common/img/logo/hesed_1597363016.webp",
                              "name" => "HESED",
                              "id" => 1601,
                              "local_cd" => "1601",
                              "company_id" => "1514",
                              "company" => "1514",
                              "min" => 20000,
                              "rep" => 88,
                              "vc" => 82,
                              "categorie" => "woman",
                              "category_default" => 1,
                              "categories_store" => [
                                  "woman"
                              ],
                              "paquete" => "premium",
                              "cleaned" => "hesed",
                              "favorite" => false,
                              "status" => "1000",
                              "max_discount" => 29,
                              "portada" => ""
                          ]
                      ],
                      [
                          "textos" => [
                              "head" => [
                                  "text" => "Sin mínimo",
                                  "size" => 9,
                                  "color" => "#000000"
                              ],
                              "body" => [
                                  "text" => "2x1 y hasta 50% off",
                                  "size" => 11,
                                  "color" => "#000000"
                              ],
                              "footer" => [
                                  "text" => "Ver ofertas",
                                  "size" => 9,
                                  "color" => "#000000"
                              ]
                          ],
                          "color" => "#FFFFFF",
                          "portada" => "https://netivooregon.s3.us-west-2.amazonaws.com/modatexrosa2/img/logo/octane-app-premium.png",
                          "store" => [
                              "logo" => "https://netivooregon.s3.amazonaws.com/common/img/logo/octane_1611259242.webp",
                              "name" => "OCTANE",
                              "id" => 1868,
                              "local_cd" => "1868",
                              "company_id" => "1776",
                              "company" => "1776",
                              "min" => 1,
                              "rep" => 86,
                              "vc" => 61,
                              "categorie" => "woman",
                              "category_default" => 1,
                              "categories_store" => [
                                  "woman",
                                  "man"
                              ],
                              "paquete" => "blue",
                              "cleaned" => "octane",
                              "favorite" => false,
                              "status" => "1000",
                              "max_discount" => 51,
                              "portada" => ""
                          ]
                      ],
                      [
                          "textos" => [
                              "head" => [
                                  "text" => "Nueva Temporada",
                                  "size" => 9,
                                  "color" => "#FFFFFF"
                              ],
                              "body" => [
                                  "text" => "Comprá desde $3000",
                                  "size" => 11,
                                  "color" => "#FFFFFF"
                              ],
                              "footer" => [
                                  "text" => "Ver catálogo",
                                  "size" => 9,
                                  "color" => "#FFFFFF"
                              ]
                          ],
                          "portada" => "https://netivooregon.s3.us-west-2.amazonaws.com/modatexrosa2/img/logo/blackolive-app-premium.png",
                          "color" => "#000000",
                          "store" => [
                              "logo" => "https://netivooregon.s3.amazonaws.com/common/img/logo/blackolive_1547561960.webp",
                              "name" => "BLACK OLIVE",
                              "id" => 1753,
                              "local_cd" => "1753",
                              "company_id" => "1664",
                              "company" => "1664",
                              "min" => 6000,
                              "rep" => 78,
                              "vc" => 75,
                              "categorie" => "woman",
                              "category_default" => 1,
                              "categories_store" => [
                                  "woman"
                              ],
                              "paquete" => "premium",
                              "cleaned" => "blackolive",
                              "favorite" => false,
                              "status" => "1000",
                              "max_discount" => 34
                          ]
                      ]
                  ]
                ],
                [
                  'name' => 'Mujer',
                  'type' => 'filter',
                  'value' => 'Mujer',
                  'redirect' => [
                    'route' => '/categories',
                    'params' => [ 'woman' ]
                  ],
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
                  'products' => $productoHombre,
                  'redirect' => [
                    'route' => '/categories',
                    'params' => [ 'man' ]
                  ],
                ],
                [
                  'name' => 'Talle Especial',
                  'type' => 'filter',
                  'value' => 'Talle Especial',
                  'products' => $productoXL,
                  'redirect' => [
                    'route' => '/categories',
                    'params' => [ 'xl' ]
                  ],
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
                  'redirect' => [
                    'route' => '/categories',
                    'params' => [ 'kids' ]
                  ],
                ],
                [
                  'name' => 'Accesorios',
                  'type' => 'categorie',
                  'value' => 'Accesorios',
                  'products' => $productoaccessories,
                  'redirect' => [
                    'route' => '/categories',
                    'params' => [ 'accessories' ]
                  ],

                ],
                [
                  'name' => 'Zapatos',
                  'type' => 'filter',
                  'value' => 'zapatos',
                  'products' => $productoZapatos,
                  'redirect' => [
                    'route' => '/search',
                    'params' => [ 'search' => 'zapatos' ]
                  ],
                ],
                [
                  'name' => 'Remeras',
                  'type' => 'filter',
                  'value' => 'remeras',
                  'products' => $productoRemeras,
                  'redirect' => [
                    'route' => '/search',
                    'params' => [ 'search' => 'remera' ]
                  ],
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
                  'products' => $productoDetacados,
                  'redirect' => [
                    'route' => '/search',
                    'params' => [ 'search' => '', 'section' => ['woman']  ]
                  ],
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
                [
                  'name' => '¿Necesitas ayuda?',
                  'type' => 'card_list_redirect',
                  'items' => [
                    [
                      'name' => '¿Cómo comprar?',
                      'editor' => $this->coding($pagesMenuCMS->where('id',458)->first()->data_json),
                      'redirect' => [
                        'route' => '/page',
                        'params' => [ 'id' => 458 , 'editor' => $this->coding($pagesMenuCMS->where('id',458)->first()->data_json), 'name' => $pagesMenuCMS->where('id',458)->first()['title_app'] ? $pagesMenuCMS->where('id',458)->first()['title_app'] : $pagesMenuCMS->where('id',458)->first()['title']]
                      ]
                    ],
                    [
                      'name' => 'Formas de pago',
                      'editor' => $this->coding($pagesMenuCMS->where('id',479)->first()->data_json),
                      'redirect' => [
                        'route' => '/page',
                        'params' => [ 'id' => 479 , 'editor' => $this->coding($pagesMenuCMS->where('id',479)->first()->data_json), 'name' => $pagesMenuCMS->where('id',479)->first()['title_app'] ? $pagesMenuCMS->where('id',479)->first()['title_app'] : $pagesMenuCMS->where('id',479)->first()['title']]
                      ]
                    ],
                    [
                      'name' => 'Envíos a todo el país',
                      'editor' => $this->coding($pagesMenuCMS->where('id',481)->first()->data_json),
                      'redirect' => [
                        'route' => '/page',
                        'params' => [ 'id' => 481 , 'editor' => $this->coding($pagesMenuCMS->where('id',481)->first()->data_json), 'name' => $pagesMenuCMS->where('id',481)->first()['title_app'] ? $pagesMenuCMS->where('id',481)->first()['title_app'] : $pagesMenuCMS->where('id',481)->first()['title']]
                      ]
                    ],
                    [
                      'name' => 'Políticas de Privacidad',
                      'editor' => $this->coding($pagesMenuCMS->where('id',470)->first()->data_json),
                      'redirect' => [
                        'route' => '/page',
                        'params' => [ 'id' => 470 , 'editor' => $this->coding($pagesMenuCMS->where('id',470)->first()->data_json), 'name' => $pagesMenuCMS->where('id',470)->first()['title_app'] ? $pagesMenuCMS->where('id',470)->first()['title_app'] : $pagesMenuCMS->where('id',470)->first()['title']]
                      ]
                    ],
                  ]
                ]
            ];



    Cache::put('bloqueshome',collect($data));

    return Cache::get('bloqueshome');
  }

  public function saveToken(Request $request)
  {
    return response()->json($request->all());
  }

  public function generateTokenApi(Request $request)
  {
    $apiKey = $request->input('clave_token');
    if ($apiKey === env('CLAVE_API')) {
        try {
            // Crear los datos del payload del JWT
            $customClaims = [
                'iss' => "jwt-auth", // Emisor del token
                'sub' => $apiKey,    // Asunto del token (podría ser un ID de usuario o clave API)
                'iat' => Carbon::now()->timestamp,  // Tiempo en que se emitió el token
                'exp' =>Carbon:: now()->addMinutes(60)->timestamp // Tiempo en que expira el token
            ];

            // Crear una instancia del payload usando el PayloadFactory
            $payload = JWTFactory::customClaims($customClaims)->make();

            // Generar el token JWT
            $token = JWTAuth::encode($payload)->get();

            // dd($token);
            // Crear una cookie con el token que expira en 60 minutos
            // $cookie = cookie('api_token', $token, 60);

            // Almacenar el token temporal en la caché por 60 minutos
            Cache::put($token, true, Carbon::now()->addMinutes(60));

            return ['status' => true, 'result' => [ 'expires_at' => Carbon::now()->addMinutes(60), 'token' => $token ]];

        } catch (JWTException $e) {
            return [ 'status' => false, 'result' => [ 'message' => 'Could not create token' ] ];
        }
    }
    return ['status' => false, 'result' => ['message' => 'Unauthorized']];
  }

  public function colorsFilter(Request $request)
  {
    $response = Http::withHeaders([
      'x-api-key' => Auth::user()->api_token,
    ])
    ->acceptJson()
    ->get($this->url.'?c=Products::colors');

    return response()->json($response->collect());
  }

  public function versionCheck(Request $request)
    {
        // Información de la versión actual desde la configuración
        $latestVersion =  '1.0.63';
        $latestBuildNumber = '63';
        
        // Obtener información de la versión actual del cliente (opcional)
        $currentVersion = $request->get('current_version', '');
        $currentBuildNumber = $request->get('current_build_number', '');
        
        // Determinar si hay actualización disponible
        $isUpdateAvailable = (int)$latestBuildNumber > (int)$currentBuildNumber;
        
        // Determinar si la actualización es obligatoria
        $isRequired = $this->isUpdateRequired($currentBuildNumber, $latestBuildNumber);
        
        // Mensaje personalizado
        $message = $isUpdateAvailable 
            ? ($isRequired 
                ? 'Esta actualización es obligatoria para continuar usando la aplicación.' 
                : 'Nueva versión disponible con mejoras de rendimiento y corrección de errores.')
            : 'Tu aplicación está actualizada.';
        
        return response()->json([
            'status' => true,
            'latest_version' => $latestVersion,
            'latest_build_number' => $latestBuildNumber,
            'current_version' => $currentVersion,
            'current_build_number' => $currentBuildNumber,
            'is_required' => $isRequired,
            'message' => $message,
            'download_url' => $isUpdateAvailable ? 'https://play.google.com/store/apps/details?id=com.modatex.app' : null,
            'release_notes' => $isUpdateAvailable ? $this->getReleaseNotes($latestVersion) : []
        ]);
    }
    
    private function isUpdateRequired($currentBuild, $latestBuild)
    {
        // Definir versiones críticas que requieren actualización obligatoria
        $criticalBuilds = [55, 60, 65]; // Números de build críticos
        
        return in_array((int)$latestBuild, $criticalBuilds) && (int)$currentBuild < (int)$latestBuild;
    }
    
    private function getReleaseNotes($version)
    {
        // Notas de la versión
        $releaseNotes = [
            '1.0.3' => [
                'Mejoras en el rendimiento de carga',
                'Corrección de errores en el checkout',
                'Nuevas funcionalidades de notificaciones'
            ],
            '2.0.0' => [
                'Actualización de seguridad crítica',
                'Cambios importantes en la estructura de datos',
                'Mejoras obligatorias del sistema'
            ]
        ];
        
        return $releaseNotes[$version] ?? [];
    }

}
