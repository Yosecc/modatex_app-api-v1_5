<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Cart;
use App\Models\Store;
use App\Models\Slider;
use App\Models\States;
use App\Models\PagesCms;
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
      })
      ;
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

    $nameChache = 'bloques';
    if (Cache::has($nameChache)) {
      return Cache::get($nameChache);
    }

    $products = new ProductsController();
    
    $data = [
      [
        'name' => 'Mujer',
        'type' => 'categorie',
        'value' => 1,
        'config' => [
          'slider' => true,
          'is_title' => false,
          'is_card' => false,
        ],
        'products' => $this->productsCategorie([
          'store' => [
            'plan' => 'black',
            'categorie' => 'woman',
            'limit' => 6
          ],
          'products' => [
            'length' => 1
          ]
        ])
      ],
      [
        'name' => 'Hombre',
        'type' => 'categorie',
        'value' => 3,
        'products' => $this->productsCategorie([
          'store' => [
            'plan' => 'black',
            'categorie' => 'man',
            'limit' => 6
          ],
          'products' => [
            'length' => 1
          ]
        ])
      ],
      [
        'name' => 'Talle Especial',
        'type' => 'categorie',
        'value' => 6,
        'products' => $this->productsCategorie([
          'store' => [
            'plan' => 'black',
            'categorie' => 'xl',
            'limit' => 6
          ],
          'products' => [
            'length' => 1
          ]
        ]),
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
        'type' => 'categorie',
        'value' => 4,
        'products' => $this->productsCategorie([
          'store' => [
            'plan' => 'black',
            'categorie' => 'kids',
            'limit' => 6
          ],
          'products' => [
            'length' => 1
          ]
        ]),
      ],
      [
        'name' => 'Accesorios',
        'type' => 'categorie',
        'value' => 2,
        'products' => $this->productsCategorie([
          'store' => [
            'plan' => 'black',
            'categorie' => 'accessories',
            'limit' => 6
          ],
          'products' => [
            'length' => 1
          ]
        ]),
      ],
      [
        'name' => 'Zapatos',
        'type' => 'filter',
        'value' => 'zapatos',
        'products' => $products->onGetSearch([
          'start' => 0,
          'length' => 6,
          'search' => 'zapatos',
          'years' => 1,
          'order' => 'manually',
          'no_product_id' => null,
          'daysExpir' => 365,
          // 'sections' => 'woman',
        ])
      ],
      [
        'name' => 'Remeras',
        'type' => 'filter',
        'value' => 'remeras',
        'products' => $products->onGetSearch([
          'start' => 0,
          'length' => 6,
          'search' => 'remeras',
          'years' => 1,
          'order' => 'manually',
          'no_product_id' => null,
          'daysExpir' => 365,
          // 'sections' => 'woman',
        ])
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
        'products' => $products->destacados([
          'start' => 0,
          'length' => 6,
          'storeData' => 1,
          'inStock' => 1,
          'daysExpir' => 365,
          'order' => 'date DESC',
        ])
      ],
    ];

    Cache::put($nameChache, $data , $seconds = 10800);

    return $data;

  }

  /**
   * GET PROMOCIONES
   */
  public function getPromociones()
  {
    $pagePromotion = PagesCms::where('isPromotionApp','!=','0000-00-00 00:00:00')->whereNotNull('isPromotionApp')->orderBy('last_updated', 'desc')->first();

    $cmsCarouselHome = PagesCms::where('isHomeApp','!=','0000-00-00 00:00:00')->whereNotNull('isHomeApp')->orderBy('last_updated','desc')->orderBy('id','desc')->get();

    $carousel_home = $cmsCarouselHome->map(function($item){
      return [
        'id' => $item['id'],
        'url' => $item['url_banner'],
        'name' => utf8_decode($item['title']),
        'editor' => utf8_decode($item['data_json']),
      ];
    });
    return [
      'carousel_home' => $carousel_home,
      'promotion_page' => collect([$pagePromotion])->map(function($item){
        return [
          'name' => utf8_decode($item['title']),
          'editor' => utf8_decode($item['data_json']),
        ];
      })->first()
    ];
  }

  /**
   * GET CATEGORIAS
   */
  public function getCategories()
  {

    $pageDeportivo = PagesCms::where('isCategoryApp','!=','0000-00-00 00:00:00')->whereNotNull('isCategoryApp')->orderBy('last_updated','desc')->orderBy('id','desc')->get();

    $pages = $pageDeportivo->map(function($item){
      return [
        'id' => $item['id'],
        'name' => utf8_decode($item['title']),
        'editor' => utf8_decode($item['data_json']),
        'key' =>  'page', 
        'type' =>  'page',
        'icon' =>  $item['url_icono'],
        'color' =>  "",
        'colSpan' =>  3,
        'col' =>  0,
        'row' =>  0,
        'left' =>  100,
      ];
    });
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
      [
        'id' => 0,
        'name' => 'Calzado',
        'type' =>  'search',
        'search' =>  'zapatos',
        'key' =>  'zapatos', 
        'icon' => 'res://shoes',
        'color' =>  "",
        'colSpan' =>  2,
        'col' =>  4,
        'row' =>  1,
        'left' =>  35,
        'top' =>  20
      ],
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
  public function menuList()
  {

    $pagesMenuCMS = PagesCms::where('isMenuApp','!=','0000-00-00 00:00:00')->whereNotNull('isMenuApp')->orderBy('last_updated')->get();

    $pagesMenu = $pagesMenuCMS->map(function($item){
      return [
        "icon" => $item['url_icono'], #'~/assets/icons/icon_menu_3.png',
        "name" => utf8_decode($item['title']),
        "disabled" => $item['status'] == 1 ? false: true ,
        'editor' => utf8_decode($item['data_json'])
      ];
    });

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
        "icon" => '~/assets/icons/icon_menu_0.png',
        "name" => 'Mis pedidos',
        "disabled" => true,
        "redirect"=> [
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
    ];

    return array_merge($itemsMenu, $pagesMenu->toArray());

  }

}
