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
        'products' => collect($this->onGetCategorieSearch(1, ['product_paginate' => 8, 'product_for_store' => 1])['products'])->all()['data'],
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

  public function getPromociones()
  {
    return [ 
      [ 
        'url' => 'https://netivooregon.s3.amazonaws.com/modatexrosa2/img/logo/enviosrebajados-promos3.gif?2',
        'data' => [
          'header' => [
              'title' => 'Envíos Rebajados',
              'subtitle' =>'Descuentos especiales todos los dias',
              'image' => 'https://netivooregon.s3.amazonaws.com/modatexrosa2/img/logo/enviosrebajados-promos3b.gif',
              'config'=> [
                  'isSubtitle' => false,
                  'isTitle' => false,
              ]
          ],
          'body' => [
              [
                'type'=>'section',
                'title' =>'TARIFAS DESDE ENERO 2023',
                'images' =>['https://netivooregon.s3.amazonaws.com/modatexrosa2/img/logo/precios-envios-enero.gif?n1'],
                'config' =>[
                  'images' =>'scroll',
                  'card' =>false,
                  'padding' =>0
                ]
              ],
              [
                'type'=> 'section',
                'title' => '',
                'images' => [],
                'buttons' => [
                  [
                    'title' => 'Mirá por dónde esta tu paquete',
                    'action' => ''
                  ],
                  [
                    'title' => 'Ver servicio de moto CABA 48HS',
                    'action' => ''
                  ],
                ],
                'config' =>[
                    'card' => false
                ]
              ],
              [
                'type' => 'section',
                'title' => 'Plazos',
                'html' => '<p style="font-family: verdana, geneva; font-size: 13pt; text-align: left;"><span style="font-family: verdana, geneva;">Los plazos de entrega&nbsp;<strong>comienzan&nbsp;a dia siguiente que la marca entrega el paquete</strong> en nuestro depósito (no desde el día de la compra). </span></p><p style="font-family: verdana, geneva; font-size: 13pt; text-align: left;"><span style="font-family: verdana, geneva;"><span style="font-size: 11pt;">Si&nbsp;tu localidad es muy alejada puede ser que tarde un poco más de lo establecido.</span></span></p>',
              ],
              [
                  'type' => 'section',
                  'title' => 'Seguro',
                  'html' => '<p style="text-align: left;"><span style="font-size: 13pt; font-family: verdana, geneva;"><strong>Modatex Garantiza el&nbsp;envío por OCA, CORREO ARGENTINO,&nbsp;INTEGRAL PACK, MOTO y Transporte Tradicional&nbsp;</strong></span><span style="font-size: 13pt; font-family: verdana, geneva;">ya que en caso de extravío o pérdida, posee un seguro.&nbsp;</span><strong><span style="font-size: 13pt;">Si hay algún problema con la entrega de tu mercaderia, Modatex te la reenvía TOTALMENTE GRATIS.</span></strong></p><p style="width: 800px; margin: auto; font-size: 20px;"><span style="font-size: 13pt; font-family: verdana, geneva;">Si en el segundo envío no llega a ser satisfactoria la entrega si se pagará el nuevo (3er) reenvío.</span></p><p style="text-decoration: underline; font-family: verdana, geneva; font-size: 11pt;"><span style="font-size: 11pt; color: #000000; font-family: verdana, geneva;"><a style="color: #000000; text-decoration: underline;" title="quedate" href="../../?page=268">Condiciones del seguro.</a></span></p>',
              ],
              [
                  'type' =>'section',
                  'title' => 'Más Promos de Envíos',
                  'images' => ['https://netivooregon.s3.amazonaws.com/modatexrosa2/img/logo/pinkdays-promos3.gif?n6','https://netivooregon.s3.amazonaws.com/modatexrosa2/img/logo/enviogratis-promos3.gif?n'],
                  'config' => []
              ],

          ]
        ]
      ],
      [ 
        'url' =>  'https://netivooregon.s3.amazonaws.com/modatexrosa2/img/logo/enviogratis-promos3.gif?n',
        'data' =>  [
          'header' => [
              'title' => 'Envíos Gratis',
              'subtitle' => 'Minímo $3000',
              'image' =>  'https://netivooregon.s3.amazonaws.com/modatexrosa2/img/logo/enviogratis-promos3b.gif?n',
              'config' => [
                  'isSubtitle' =>  false,
                  'isTitle' =>  false,
              ]
          ],
          'body' => [
              [
                  'type' =>'section',
                  'title' => 'Envío Gratis en toda la web',
                  'html' => '<h2 style="text-align: center;"><span style="font-size: 12pt;">Ahorrá hasta $950</span></h2><p style="width: 800px; margin: auto; font-size: 20px; text-align: center;"><span style="color: #333333; font-size: 12pt;">A&nbsp;partir&nbsp;del monto mínimo especifícado,&nbsp;el envío es <strong>GRATIS</strong>! </span></p><p style="width: 800px; margin: auto; font-size: 20px; text-align: center;"><span style="color: #333333; font-size: 12pt;">A través de: <strong>sucursal de Oca o Correo Argentino</strong> a todo el país; sucursales seleccionadas de <strong>Integral Pack</strong>; y <strong>Moto</strong> dentro de Capital Federal y Gran Buenos Aires.</span></p>',
                  'config' => []
              ],
              [
                'type' => 'section',
                'title' => 'A PARTIR DE $3000!',
                'marcas' => [
                  [
                    "logo"=> "https://netivooregon.s3.amazonaws.com/common/img/logo/valery_1615838645.webp",
                    "name"=> "valery",
                    "local_cd"=> "2107",
                    "min"=> 5000
                  ],
                ]
              ],
              [
                'type'=> 'section',
                'title'=> 'A PARTIR DE $4000!',
                'marcas'=> [
                  
                  [
                      "logo"=> "https://netivooregon.s3.amazonaws.com/common/img/logo/blackolive_1547561960.webp",
                      "name"=> "blackolive",
                      "local_cd"=> "1753",
                      "min"=> 10000
                  ],
                  [
                      "logo"=> "https://netivooregon.s3.amazonaws.com/common/img/logo/amaika_1616526803.webp",
                      "name"=> "amaika",
                      "local_cd"=> "2120",
                      "min"=> 7000
                  ],
                 
                ]
              ],
              [
                'type'=> 'section',
                'title'=> 'A PARTIR DE $5000!',
                'marcas'=> [
                  
                [
                    "logo"=> "https://netivooregon.s3.amazonaws.com/common/img/logo/dasom_1598037855.webp",
                    "name"=> "dasom",
                    "local_cd"=> "2011",
                    "min"=> 6000
                ],
                [
                    "logo"=> "https://netivooregon.s3.amazonaws.com/common/img/logo/explosive_1620757743.webp",
                    "name"=> "explosive",
                    "local_cd"=> "2271",
                    "min"=> 10000
                ],
                [
                    "logo"=> "https://netivooregon.s3.amazonaws.com/common/img/logo/monami_1601662041.webp",
                    "name"=> "monami",
                    "local_cd"=> "2153",
                    "min"=> 6500
                ],
                [
                    "logo"=> "https://netivooregon.s3.amazonaws.com/common/img/logo/chesmin_1623351440.webp",
                    "name"=> "chesmin",
                    "local_cd"=> "2250",
                    "min"=> 4000
                ],
                [
                    "logo"=> "https://netivooregon.s3.amazonaws.com/common/img/logo/nozomi_1664473458.webp",
                    "name"=> "nozomi",
                    "local_cd"=> "1897",
                    "min"=> 10000
                ],
                [
                    "logo"=> "https://netivooregon.s3.amazonaws.com/common/img/logo/anais_1619799015.webp",
                    "name"=> "anais",
                    "local_cd"=> "2298",
                    "min"=> 20000
                ],
                [
                    "logo"=> "https://netivooregon.s3.amazonaws.com/common/img/logo/lulu_1664473404.webp",
                    "name"=> "lulu",
                    "local_cd"=> "2390",
                    "min"=> 10000
                ],
                [
                    "logo"=> "https://netivooregon.s3.amazonaws.com/common/img/logo/numeroindumentaria_1663266928.webp",
                    "name"=> "numeroindumentaria",
                    "local_cd"=> "2529",
                    "min"=> 10000
                ],
                 
                ]
              ],
              [
                'type' =>  'section',
                'title' =>  '',
                'html' =>  '<p style="width: 800px; margin: auto; font-size: 14px; text-align: center; color: white;"><span style="color: #000000;">Promoción válida cumpliendo el mínimo de compra de la marca correspondiente.</span></p><p style="width: 800px; margin: auto; font-size: 14px; text-align: center; color: white;"><span style="color: #000000;">Sólo para la República Argentina, en envíos por:</span></p><p style="width: 800px; margin: auto; font-size: 14px; text-align: center; color: white;"><span style="color: #000000;">-&nbsp;Sucursal de Correo Argentino a todo el país.</span></p><p style="width: 800px; margin: auto; font-size: 14px; text-align: center; color: white;"><span style="color: #000000;">-&nbsp;Sucursal de OCA a todo el país.</span></p><p style="width: 800px; margin: auto; font-size: 14px; text-align: center; color: white;"><span style="color: #000000;">- Moto a domicilio dentro de Capital Federal y Gran Buenos Aires.</span></p><p style="width: 800px; margin: auto; font-size: 14px; text-align: center; color: white;"><strong><span style="color: #000000;">NO ACUMULABLE CON OTRAS PROMOCIONES DE ENVÍOS</span></strong></p><p style="width: 800px; margin: auto; font-size: 14px; text-align: center; color: white;"><span style="color: #000000;">Aplican Bases y Condiciones</span></p>',
              ]

          ]
        ]
      ],
      [ 
        'url'=> 'https://netivooregon.s3.amazonaws.com/modatexrosa2/img/logo/minimos-promos3.gif',
        'data'=> [
          'header'=>[
              'title'=> 'Mínimos Rebajados',
              'subtitle'=> 'Hasta $1! vie - sab - dom',
              'image'=> 'https://netivooregon.s3.amazonaws.com/modatexrosa2/img/logo/minimos-promos3b.gif',
              'config'=>[
                  'isSubtitle'=> false,
                  'isTitle'=> false,
              ]
          ],
          'body'=>[
              [
                  'type'=>'section',
                  'title'=> '¡Comprá desde $1! ',
                  'html'=> '<p style="text-align: center;"><span style="color: #333333; font-size: 13pt;">&nbsp;<span style="font-size: 12pt;">Todos los <strong>Viernes, Sábados y Domingos</strong>,&nbsp;las&nbsp;marcas bajan sus mínimos de compra.</span></span></p><p style="text-align: center;"><span style="color: #333333; font-size: 11pt;"><strong>Combinalo con nuestras <span style="text-decoration: underline;"><a style="color: #333333; text-decoration: underline;" href="http://www.modatex.com.ar/?page=220">promos en envíos</a></span>!&nbsp;</strong></span><span style="color: #333333;">&nbsp;</span>&nbsp;</p>',
                  'config'=> []
              ],
              [
                'type' => 'section',
                'title' => 'Mínimos Rebajados Todos los Días. De Lunes a Lunes.',
                'marcas' => [
                  [
                      "logo"=> "https://netivooregon.s3.amazonaws.com/common/img/logo/blackolive_1547561960.webp",
                      "name"=> "blackolive",
                      "local_cd"=> "1753",
                      "min"=> 10000
                  ],
                  [
                      "logo"=> "https://netivooregon.s3.amazonaws.com/common/img/logo/amaika_1616526803.webp",
                      "name"=> "amaika",
                      "local_cd"=> "2120",
                      "min"=> 7000
                  ],
                ]
              ],
              [
                'type' => 'section',
                'title' => 'Mínimos menores a $1000 de viernes a domingo',
                'marcas' => [
                  [
                      "logo"=> "https://netivooregon.s3.amazonaws.com/common/img/logo/blackolive_1547561960.webp",
                      "name"=> "blackolive",
                      "local_cd"=> "1753",
                      "min"=> 10000
                  ],
                  [
                      "logo"=> "https://netivooregon.s3.amazonaws.com/common/img/logo/amaika_1616526803.webp",
                      "name"=> "amaika",
                      "local_cd"=> "2120",
                      "min"=> 7000
                  ],
                ]
              ],
              [
                'type' => 'section',
                'title' => 'Mínimos mayores a $1000 de viernes a domingo',
                'marcas' => [
                  [
                      "logo"=> "https://netivooregon.s3.amazonaws.com/common/img/logo/blackolive_1547561960.webp",
                      "name"=> "blackolive",
                      "local_cd"=> "1753",
                      "min"=> 10000
                  ],
                  [
                      "logo"=> "https://netivooregon.s3.amazonaws.com/common/img/logo/amaika_1616526803.webp",
                      "name"=> "amaika",
                      "local_cd"=> "2120",
                      "min"=> 7000
                  ],
                ]
              ],
          ]
        ]
      ],
      [ 
        'url'=> 'https://netivooregon.s3.amazonaws.com/modatexrosa2/img/logo/juevesmodapago-promos.gif',
        'data' => [
          'header' => [
            'title' => 'Reintegro del 5% de tu compra.',
            'subtitle' => 'Modapago. Jueves',
            'image' => 'https://netivooregon.s3.amazonaws.com/modatexrosa2/img/logo/juevesmodapago-promos.gif',
            'config' => [
              'isSubtitle'=> false,
              'isTitle'=> false,
            ]
          ],
          'body' => [
            [
              'type'=>'section',
              'title'=> '',
              'html'=> '<div id="contents_area" class="contents_area_homelist_last_products">
              <div style="width: auto; margin-left: auto; margin-right: auto; text-align: center;">
              <span style="font-size: 16pt;">Transformá tu compra en un descuento!</span>&nbsp;
              </div>
              <p style="width: 800px; margin: auto; font-size: 17px; text-align: center; color: black;">Comprando todos los <strong>jueves</strong> y abonando con<strong>&nbsp;<strong>Modapago</strong></strong>, Modatex <strong>te devuelve 5%</strong> en forma de un cupón para tu próxima compra!</p>
              <p style="width: 800px; margin: auto; font-size: 17px; text-align: center; color: black;">&nbsp;&nbsp;</p>
              <p style="width: 800px; margin: auto; font-size: 17px; text-align: center; color: black;"><span style="color: #5757ad;"><strong>Cómo funciona?</strong></span></p>
              <p style="width: 800px; margin: auto; font-size: 17px; text-align: center; color: black;"><strong>Pagando por Modapago</strong> con tarjeta de crédito o débito, Rapipago o Pagofácil;</p>
              <p style="width: 800px; margin: auto; font-size: 17px; text-align: center; color: black;">se te va a generar automáticamente un cupón en tu perfil. <span style="font-size: 9pt;">(2)(3)</span></p>
              <p style="width: 800px; margin: auto; font-size: 17px; text-align: center; color: black;">&nbsp;&nbsp;</p>
              <p style="width: 800px; margin: auto; font-size: 17px; text-align: center; color: black;"><strong><span style="color: #5364ad;">De cuánto es el cupón?</span></strong></p>
              <p style="width: 800px; margin: auto; font-size: 17px; text-align: center; color: black; padding: 10px;">Tu cupón va a ser igual al 5% de tu compra, con un <strong>límite de devolución de $750</strong>.</p>
              <p style="width: 800px; margin: auto; font-size: 17px; text-align: center; color: black;">
              <img src="https://netivooregon.s3.amazonaws.com/modatexrosa2/img/logo/juevesmodapago-news2.gif" alt="modapago" width="300"/></p>
              <p style="width: 800px; margin: auto; font-size: 17px; text-align: center; color: black;">&nbsp;</p>
              <p style="width: 800px; margin: auto; font-size: 17px; text-align: center; color: #5364ad;">&nbsp;<strong>Hasta cuándo puedo usar el cupón?</strong></p>
              <p style="width: 800px; margin: auto; font-size: 17px; text-align: center; color: black;">Tenés&nbsp;una semana para usarlo&nbsp;desde el día del pedido. Aprovechalo! <span style="font-size: 9pt;">(4)</span></p>
              <p style="width: 800px; margin: auto; font-size: 17px; text-align: center; color: black;">&nbsp;</p>
              <p style="width: 800px; margin: auto; font-size: 17px; text-align: center; color: black;"><span style="color: #5364ad;"><strong>En qué marcas puedo usar mi cupón?</strong></span></p>
              <p style="width: 800px; margin: auto; font-size: 17px; text-align: center; color: black;">En todas las marcas.</p>
              <p style="width: 800px; margin: auto; font-size: 17px; text-align: center; color: black;">&nbsp;</p>
              <p style="width: 800px; margin: auto; font-size: 17px; text-align: center; color: #5364ad;"><br>&nbsp;</p>
              <p style="width: 800px; margin: auto; font-size: 17px; text-align: center; color: black;"><span style="color: #5364ad; font-family: verdana, geneva;"><a style="color: #5364ad;" href="../../perfil?cupones">¿Dónde veo mis cupones?</a></span></p>
              <p style="width: 800px; margin: auto; font-size: 17px; text-align: center; color: black;"><span style="font-family: verdana, geneva;">No olvides que para participar debés&nbsp;<span style="color: #5364ad;"><a style="color: #5364ad;" href="../../">ingresar&nbsp;con tu usuario de Modatex</a>.</span></span></p>
              <p style="width: 800px; margin: auto; font-size: 17px; text-align: center; color: black;">&nbsp;</p>
              <p style="width: 800px; margin: auto; font-size: 17px; text-align: center; color: black;">&nbsp;&nbsp;</p>
              <p style="width: 800px; margin: auto; font-size: 17px; text-align: center; color: black;">&nbsp;</p>
              <div>
              <p style="width: 800px; margin: auto; text-align: center; font-size: 25px;">&nbsp;&nbsp;</p>
              <p style="width: 800px; margin: auto; font-size: 14px; text-align: center; color: white;"><span style="color: #000000;">1-Promoción&nbsp;válida sólo para la República Argentina.</span></p>
              <p style="width: 800px; margin: auto; font-size: 14px; text-align: center; color: white;"><span style="color: #000000;">2-Los&nbsp;cupones&nbsp;se&nbsp;generan una vez realizado el pago. El pago puede ser hecho en cualquier momento&nbsp;antes del jueves siguiente a hacer el pedido.</span></p>
              <p style="width: 800px; margin: auto; font-size: 14px; text-align: center; color: white;"><span style="color: #000000;">3-Son válidos solamente los pagos relacionados a compras dentro de Modatex. No son válidos los pagos independientes. El número de cupón debe coincidir con el número de compra.</span></p>
              <p style="width: 800px; margin: auto; font-size: 14px; text-align: center; color: white;"><span style="color: #000000;">4- Los cupones son válidos&nbsp;hasta el miercoles siguiente de hacer el pedido, a las 23:59 hs.</span></p>
              <p style="width: 800px; margin: auto; font-size: 14px; text-align: center; color: white;"><span style="color: #000000;">5- El total a redimir no incluye el valor del envío.</span></p>
              <p style="width: 800px; margin: auto; font-size: 14px; text-align: center; color: white;"><span style="color: #000000;">6- Aplican Bases y Condiciones</span></p>
              <p style="width: 800px; margin: auto; font-size: 14px; text-align: center; color: white;">&nbsp;</p>
              <p style="width: 800px; margin: auto; font-size: 14px; text-align: center; color: white;">&nbsp;</p>
              <p><iframe style="display: block; margin-left: auto; margin-right: auto; max-width: 100%;" src="https://f0d938a3.sibforms.com/serve/MUIEAHz1LTZNZ9kzGoydvyRkZtZ3d86rFEqruk8SmPn8PYkLDKzvhs4vhwn7saG6VyEni59IeV9H34IluTe7ttoank-0aC0dN5w4pgYQDjl90pcBhJzc1znCoVt3MFvjq0EiibuVKQJcIP24g-1aoB82d7fpABvVdKJsD2pqXUD327FqDPT0SVfZ9iqixejZQgPi3SC3BETjQAt7" width="50%" height="100%" frameborder="0" scrolling="auto" allowfullscreen="allowfullscreen"></iframe></p>
              <p style="width: 800px; margin: auto; font-size: 14px; text-align: center; color: white;">&nbsp;</p>
              <p style="width: 800px; margin: auto;"><span style="color: #000000;">&nbsp;</span>&nbsp;&nbsp;</p>
              <p style="width: 800px; margin: auto;">&nbsp;</p>
              </div>
              
              </div>',
              'config'=> []
            ],
          ]
        ]
      ],
      [ 
        'url'=> 'https://netivooregon.s3.amazonaws.com/modatexrosa2/img/logo/pinkdays-promos3.gif?n6',
        'data' => [
          'header' => [
            'title' => 'Pink Day',
            'subtitle' => 'Envíos desde $199. Miércoles',
            'image' => 'https://netivooregon.s3.amazonaws.com/modatexrosa2/img/logo/pinkdays-promos3.gif?n6',
            'config'=>[
              'isSubtitle'=> false,
              'isTitle'=> false,
            ]
          ],
          'body' => [
            [
              'type' => 'section',
              'title' => '',
              'html' => '<p style="width: 800px; margin: auto; font-size: 17px; color: black; text-align: center;"><span style="font-size: 9pt; color: #333333;">&nbsp;</span></p>
              <div style="width: auto; margin-left: auto; margin-right: auto; text-align: center;"><span style="font-size: 13pt; color: #333333;">Sólo los miércoles!</span></div>
              <div style="width: auto; margin-left: auto; margin-right: auto; text-align: center;"><span style="font-size: 13pt; color: #333333;">El envío te sale hasta <strong>75% más barato</strong>!</span></div>
              <div style="width: auto; margin-left: auto; margin-right: auto; text-align: center;">&nbsp;</div>
              <div style="width: auto; margin-left: auto; margin-right: auto; text-align: center;"><span style="color: #333333;">&nbsp;</span></div>
              <div style="width: auto; margin-left: auto; margin-right: auto; text-align: center;"><span style="text-decoration: underline; color: #333333;"><span style="font-size: 13pt;"><strong>Todos los miércoles:</strong></span></span></div>
              <div style="width: auto; margin-left: auto; margin-right: auto; text-align: center;"><span style="font-size: 13pt; color: #333333;"><strong>Envío a $199 </strong>a&nbsp;domicilio por<strong> <strong>Moto</strong> </strong>dentro de CABA,</span></div>
              <div style="width: auto; margin-left: auto; margin-right: auto; text-align: center;"><span style="font-size: 13pt; color: #333333;"><strong>Envío a $249&nbsp;</strong>a&nbsp;domicilio por<strong>&nbsp;<strong>Moto</strong>&nbsp;</strong>dentro de GBA,</span></div>
              <div style="width: auto; margin-left: auto; margin-right: auto; text-align: center;"><span style="font-size: 13pt; color: #333333;"><strong>Envío a $399 </strong>por<strong>&nbsp;</strong>sucursal de&nbsp;<strong><strong>Correo Argentino</strong></strong> a todo el país,</span></div>
              <div style="width: auto; margin-left: auto; margin-right: auto; text-align: center;"><span style="font-size: 13pt; color: #333333;"><strong>Envío a $399&nbsp;</strong>por<strong>&nbsp;</strong>sucursal de <strong><strong>OCA</strong></strong>, y<strong><strong>&nbsp;</strong></strong>por terminal de&nbsp;<strong>Integral Pack</strong> a todo el país</span></div>',
              'config'=> []
            ],
            [
              'type' =>'section',
              'title' => 'Más Promos de Envíos',
              'images' => ['https://netivooregon.s3.amazonaws.com/modatexrosa2/img/logo/enviogratis-promos3.gif','https://netivooregon.s3.amazonaws.com/modatexrosa2/img/logo/enviosrebajados-promos3.gif?2'],
              'config' => []
            ],
            [
              'type' => 'section',
              'title' => '',
              'html' => '<div style="width: auto; margin-left: auto; margin-right: auto; text-align: center;">

                <p style="width: 800px; margin: auto; font-size: 14px; text-align: center; color: white;"><span style="color: #000000;">1- Promoción válida sólo para la República Argentina.</span></p>
                <p style="width: 800px; margin: auto; font-size: 14px; text-align: center; color: white;"><span style="color: #000000;">2- Los precios expresados son válidos durante los días miércoles.</span></p>
                <p style="width: 800px; margin: auto; font-size: 14px; text-align: center; color: white;"><span style="color: #000000;">3- El pedido debe ser hecho el día miércoles.</span></p>
                <p style="width: 800px; margin: auto; font-size: 14px; text-align: center; color: white;"><strong><span style="color: #000000;">4- NO ACUMULABLE CON OTRAS PROMOCIONES DE ENVÍOS</span></strong></p>
                <p style="width: 800px; margin: auto; font-size: 14px; text-align: center; color: white;"><span style="color: #000000;">5- Aplican Bases y Condiciones</span></p>
              </div>',
              'config'=> []
            ],
          ]
        ]
      ],

      [ 'url'=> 'https://netivooregon.s3.amazonaws.com/modatexrosa2/img/logo/otono-invierno-adelanto-promos.jpg',],
      [ 'url'=> 'https://netivooregon.s3.amazonaws.com/modatexrosa2/img/logo/liquidacion-verano-promos.jpg' ],
      [ 'url'=> 'https://netivooregon.s3.amazonaws.com/modatexrosa2/img/logo/outlet-cms.jpg'],
      [ 'url'=> 'https://netivooregon.s3.amazonaws.com/modatexrosa2/img/logo/cupones750-promos.jpg' ],
      [ 'url'=> 'https://netivooregon.s3.amazonaws.com/modatexrosa2/img/logo/lunesmayor-promos.gif?n4' ],
    ];
  }

  public function statesGet()
  {
    return response()->json(States::select('NUM AS id','STATE_NAME AS name')
    ->where('STAT_CD',1000)->orderBy('name','asc')->get());
  }

}
