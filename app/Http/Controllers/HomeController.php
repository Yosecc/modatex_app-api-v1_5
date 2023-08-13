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
    // dd('si');
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
        // "category_default" => $predefSection
      ];
    });

    Cache::put($nameChache, ['stores' => $stores, 'products' => $products] , $seconds = 10800);

    return ['stores' => $stores, 'products' => CollectionHelper::paginate(collect($products), $config['product_paginate']) ];
  }

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
      [
        'type' => 'promotions',
        'value' => 'valor de busqueda',
        'promotions' => [
          $this->getPromociones()[array_search(3, array_column($this->getPromociones(), 'id'))]
        ]
      ],
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
    ];

    Cache::put($nameChache, $data , $seconds = 10800);

    return $data;

  }

  public function getPromociones()
  {
    return [ 
      [ 
        'id' => 6,
        'url'=> 'https://netivooregon.s3.amazonaws.com/modatexrosa2/img/logo/lunesmayor-promos.gif?n4',
        'data' => [
          'header' => [
            'title'=> 'Lunes por mayor',
            'subtitle'=> 'Especial moyoristas',
            'image'=> 'https://netivooregon.s3.amazonaws.com/modatexrosa2/img/logo/lunesmayor-promos.gif?n4',
            'config'=>[
                'isSubtitle'=> false,
                'isTitle'=> false,
            ]
          ],
          'body' => [
            [
              'type' => 'section',
              'title' => '',
              'html' => '<h1 style="text-align: center;"><strong><span style="font-size: 16pt; font-family: helvetica;">Mirá este beneficio&nbsp;para comprar ropa&nbsp;por mayor</span></strong></h1>
                <p style="text-align: center;"><span style="font-size: 12pt; font-family: helvetica;">Comprando los Lunes a partir de $40.000,&nbsp;tenés&nbsp;<strong>$2.000 de descuento.&nbsp;</strong>Combinalo con <strong>Envío Gratis</strong> en tiendas seleccionadas a sucursal de Correo Argentino y OCA a todo el país y a domicilio por Moto dentro de Capital Federal y GBA.</span></p>
                <h2 style="text-align: center;"><span style="font-size: 14pt; font-family: helvetica;">Comprá online en los locales de Flores, Avenida Avellaneda</span></h2>
                <p style="text-align: center;"><span style="font-size: 12pt; font-family: helvetica;">Si tenés una tienda de ropa, esta promoción es especial para vos! También&nbsp;podés aprovechar para&nbsp;renovar tu guardarropa, o comprar en conjunto con amigos.</span></p>',
              'config' => []
            ],
            [
              'type'=> 'section',
              'title'=> 'Marcas con Envío Gratis',
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
              'type' => 'section',
              'title' => '',
              'html' => '<p style="width: 800px; margin: auto;"><span style="font-size: 11pt; color: #808080;">1)&nbsp;El cupón se generará una vez superados los $40.000</span></p>
              <p style="width: 800px; margin: auto;"><span style="font-size: 11pt; color: #808080;">2) El cupón debe estar&nbsp;seleccionado previo a la confirmación del pedido.</span></p>
              <p style="width: 800px; margin: auto;"><span style="font-size: 11pt; color: #808080;">3)&nbsp;El cupón sólo puede utilizarse durante&nbsp;el mismo día en el que se realizó el pedido&nbsp;y en la misma marca.</span></p>
              <p style="width: 800px; margin: auto;"><span style="font-size: 11pt; color: #808080;">4) Para poder realizar el pedido el total de la compra (incluído el cupón) deberá ser igual o mayor al monto mínimo de compra de la marca expresado en el banner. El total no incluye el valor del envío.</span></p>
              <p style="width: 800px; margin: auto;"><span style="font-size: 11pt; color: #808080;">5)&nbsp;Promoción no acumulable con otros cupones de Modatex.</span></p>
              <p style="width: 800px; margin: auto;"><span style="font-size: 11pt; color: #808080;">6)&nbsp;Se otorgarán un total de 1 cupón por compra. <strong>1 cupón = 1 compra</strong>.</span></p>
              <p style="width: 800px; margin: auto;"><span style="font-size: 11pt; color: #808080;">7) Promoción válida hasta diciembre&nbsp;2023 inclusive.</span></p>',
              'config' => []
            ],
  
          ]
        ]
      ],
      [ 
        'id' => 7,
        'url'=> 'https://netivooregon.s3.amazonaws.com/modatexrosa2/img/logo/cupones750-promos.jpg',
        'action' => [
          'redirect' => [
            'route' => '/discount_especial',
            'params' => []
          ]
        ],
        'data' => []
      ],
      [ 
        'id' => 5,
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
      [ 
        'id' => 4,
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
        'id' => 3,
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
        'id' => 2,
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
        'id' => 1,
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
                    'action' => '',
                    'redirect' =>[
                      'route'=> '/search',
                      'params' => [
                        'params' => [
                          'search' => 'value',
                          'sections' => [3],
                          'section' => 'ofertas'
                        ]
                      ]
                    ]
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
        'id' => 8,
        'url'=> 'https://netivooregon.s3.amazonaws.com/modatexrosa2/img/logo/outlet-cms.jpg',
        'data' => [
          'header' => [
            'title'=> 'Outlet',
            'subtitle'=> 'de temporadas anterioes',
            'image'=> 'https://netivooregon.s3.amazonaws.com/modatexrosa2/img/logo/outlet-cms.jpg',
            'config'=>[
                'isSubtitle'=> false,
                'isTitle'=> false,
            ]
          ],
          'body' => [
            [
              'type' => 'section',
              'title' => 'Outlet de Temporadas Pasadas',
              'subtitle' => 'Encontrá ofertas de Otoño Invierno 2022 y Primavera Verano 2023.',
              'group_buttons' => [
                [
                  'type' => 'tag',
                  'title' => 'TOPS',
                  'redirect' => [
                    'route' => '/search',
                    'params' => [
                      'search' => 'top',
                      'section' => 'ofertas'
                    ]
                  ]
                ],
                [
                  'type' => 'tag',
                  'title' => 'REMERAS',
                  'redirect' => [
                    'route' => '/search',
                    'params' => [
                      'search' => 'remera',
                      'section' => 'ofertas'
                    ]
                  ]
                ],
                [
                  'type' => 'tag',
                  'title' => 'CAMISAS',
                  'redirect' => [
                    'route' => '/search',
                    'params' => [
                      'search' => 'camisa',
                      'section' => 'ofertas'
                    ]
                  ]
                ],
                [
                  'type' => 'tag',
                  'title' => 'BLUSAS',
                  'redirect' => [
                    'route' => '/search',
                    'params' => [
                      'search' => 'blusa',
                      'section' => 'ofertas'
                    ]
                  ]
                ],
                [
                  'type' => 'tag',
                  'title' => 'MUSCULOSAS',
                  'redirect' => [
                    'route' => '/search',
                    'params' => [
                      'search' => 'musculosa',
                      'section' => 'ofertas'
                    ]
                  ]
                ],
                [
                  'type' => 'tag',
                  'title' => 'BODIES',
                  'redirect' => [
                    'route' => '/search',
                    'params' => [
                      'search' => 'body',
                      'section' => 'ofertas'
                    ]
                  ]
                ],
                [
                  'type' => 'tag',
                  'title' => 'VESTIDOS',
                  'redirect' => [
                    'route' => '/search',
                    'params' => [
                      'search' => 'vestido',
                      'section' => 'ofertas'
                    ]
                  ]
                ],
                [
                  'type' => 'tag',
                  'title' => 'MONOS',
                  'redirect' => [
                    'route' => '/search',
                    'params' => [
                      'search' => 'mono',
                      'section' => 'ofertas'
                    ]
                  ]
                ],
                [
                  'type' => 'tag',
                  'title' => 'ENTERITOS',
                  'redirect' => [
                    'route' => '/search',
                    'params' => [
                      'search' => 'enterito mono',
                      'section' => 'ofertas'
                    ]
                  ]
                ],
                [
                  'type' => 'tag',
                  'title' => 'BLAZERS',
                  'redirect' => [
                    'route' => '/search',
                    'params' => [
                      'search' => 'blazer',
                      'section' => 'ofertas'
                    ]
                  ]
                ],
                [
                  'type' => 'tag',
                  'title' => 'PANTALONES',
                  'redirect' => [
                    'route' => '/search',
                    'params' => [
                      'search' => 'pantalon',
                      'section' => 'ofertas'
                    ]
                  ]
                ],
                [
                  'type' => 'tag',
                  'title' => 'SHORTS',
                  'redirect' => [
                    'route' => '/search',
                    'params' => [
                      'search' => 'sshort',
                      'section' => 'ofertas'
                    ]
                  ]
                ],
                [
                  'type' => 'tag',
                  'title' => 'POLLERAS',
                  'redirect' => [
                    'route' => '/search',
                    'params' => [
                      'search' => 'pollera',
                      'section' => 'ofertas'
                    ]
                  ]
                ],
                [
                  'type' => 'tag',
                  'title' => 'JEANS',
                  'redirect' => [
                    'route' => '/search',
                    'params' => [
                      'search' => 'jean',
                      'section' => 'ofertas'
                    ]
                  ]
                ],
                [
                  'type' => 'tag',
                  'title' => 'CALZADO',
                  'redirect' => [
                    'route' => '/search',
                    'params' => [
                      'search' => 'calzados zap',
                      'section' => 'ofertas'
                    ]
                  ]
                ],
              ]
            ]
          ]
        ]
      ],
      [ 
        'id' => 9,
        'url'=> 'https://netivooregon.s3.amazonaws.com/modatexrosa2/img/logo/liquidacion-verano-promos.jpg',
        'data' => [
          'header' => [
            'title'=> 'Rebajas en primavera verano 2023',
            'subtitle'=> 'hassta 50% Off',
            'image'=> 'https://netivooregon.s3.amazonaws.com/modatexrosa2/img/logo/liquidacion-verano-promos.jpg',
            'config'=>[
                'isSubtitle'=> false,
                'isTitle'=> false,
            ]
          ],

          'body' => [
            [
              'type' => 'images',
              'title' => ' Rebajas en primavera verano 2023',
              'images' => [
                [
                  'url' => 'https://netivooregon.s3.amazonaws.com/modatexrosa2/img/logo/liquidacion-ss23-blusas.jpg',
                  'col' => 3,
                  'height' => 500,
                  'label_html' => '<span style="display: flex; clear: both; widht: 20%; text-align: center; justify-content: center;"><span style="text-decoration: underline;">blusas desde <span style="text-decoration: line-through;">$2700</span> $1500 · ver<br></span></span>',
                  'redirect' => [
                    'route' => '/search',
                    'params' => [
                      'search' => 'blusa',
                      'section' => 'ofertas'
                    ]
                  ]
                ]
              ]
            ],
            [
              'type' => 'images',
              'images' => [
                [
                  'url' => 'https://netivooregon.s3.amazonaws.com/modatexrosa2/img/logo/liquidacion-ss23-vestidos.jpg',
                  'col' => 1.5,
                  'label_html' => '<span style="display: flex; clear: both; widht: 20%; text-align: center; justify-content: center;"><span style="text-decoration: underline;">vestidos desde <span style="text-decoration: line-through;">$2400</span> $1500 · ver<br></span></span>',
                  'redirect' => [
                    'route' => '/search',
                    'params' => [
                      'search' => 'vestido',
                      'section' => 'ofertas'
                    ]
                  ]
                ],
                [
                  'url' => 'https://netivooregon.s3.amazonaws.com/modatexrosa2/img/logo/liquidacion-ss23-lino.jpg',
                  'col' => 1.5,
                  'label_html' => '<span style="display: flex; clear: both; widht: 20%; text-align: center; justify-content: center;"><span style="text-decoration: underline;">prendas de lino&nbsp;· ver</span></span>',
                  'redirect' => [
                    'route' => '/search',
                    'params' => [
                      'search' => 'lino',
                      'section' => '1'
                    ]
                  ]
                ],
              ]
            ],
            [
              'type' => 'images',
              'images' => [
                [
                  'url' => 'https://netivooregon.s3.amazonaws.com/modatexrosa2/img/logo/liquidacion-ss23-top.jpg',
                  'col' => 1,
                  'label_html' => '<span style="display: flex; clear: both; widht: 20%; text-align: center; justify-content: center;"><span style="text-decoration: underline;">tops desde <span style="text-decoration: line-through;">$1200</span> $750 · ver</span></span>',
                  'redirect' => [
                    'route' => '/search',
                    'params' => [
                      'search' => 'top',
                      'section' => 'ofertas'
                    ]
                  ]
                ],
                [
                  'url' => 'https://netivooregon.s3.amazonaws.com/modatexrosa2/img/logo/liquidacion-ss23-remera.jpg',
                  'col' => 1,
                  'label_html' => '<span style="display: flex; clear: both; widht: 20%; text-align: center; justify-content: center;"><span style="text-decoration: underline;">remeras desde <span style="text-decoration: line-through;">$1200</span> $600 · ver</span></span>',
                  'redirect' => [
                    'route' => '/search',
                    'params' => [
                      'search' => 'remera manga corta remeron',
                      'section' => 'ofertas'
                    ]
                  ]
                ],
                [
                  'url' => 'https://netivooregon.s3.amazonaws.com/modatexrosa2/img/logo/liquidacion-ss23-remera.jpg',
                  'col' => 1,
                  'label_html' => '<span style="display: flex; clear: both; widht: 20%; text-align: center; justify-content: center;"><span style="text-decoration: underline;">remeras desde <span style="text-decoration: line-through;">$1200</span> $600 · ver</span></span>',
                  'redirect' => [
                    'route' => '/search',
                    'params' => [
                      'search' => 'remera manga corta remeron',
                      'section' => 'ofertas'
                    ]
                  ]
                ],
              ]
            ],
            [
              'type'=>'section',
              'title'=> '',
              'html'=> '<div id="tiendas" style="width-max: 70%; margin: auto; clear: both;">
              <p style="margin: auto; text-align: center;"><span style="color: #000000;"><strong><span style="font-family: calibri; font-size: 15pt; letter-spacing: 3px; padding: 5px;">TIENDAS CON DESCUENTOS</span></strong></span></p>
              <p style="margin: auto; text-align: center;">&nbsp;</p>
              <p style="margin: auto; text-align: center;"><span style="color: #606060; line-height: 30px; letter-spacing: 3px;"><span style="font-family: verdana;">hasta 60% off</span></span></p>
              </div>',
              'config'=> [
                'card' => false,
              ]
            ],
            [
              'type' => 'section',
              'title' => 'hasta 60% off',
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
              'title' => 'hasta 50% off',
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
              'title' => 'hasta 40% off',
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
    ];
  }

  public function getCategories()
  {
    return [
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
        'id' => 5,
        'name' => 'Zapatos',
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
        'id' => 7,
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
    ];
  }

  public function statesGet()
  {
    return response()->json(States::select('NUM AS id','STATE_NAME AS name')
    ->where('STAT_CD',1000)->orderBy('name','asc')->get());
  }

  public function menuList()
  {

    return [
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
        "icon" => '~/assets/icons/icon_menu_6.png',
        "name" => 'Descuentos Especiales',
        "disabled" => false,
        "redirect"=> [
          "route"=> "/discount_especial",
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
        "icon" => '~/assets/icons/icon_menu_1.png',
        "name" => '¿Cómo comprar?',
        "disabled" => false,
        "data" => [
          "header" => [
            'title'=> '¿Cómo comprar en Modatex?',
            'subtitle'=> '',
            'image'=> '',
            'config'=>[
                'isSubtitle'=> false,
                'isTitle'=> true,
            ]
          ],
          "body" => [
            [
              'type' => 'section',
              'title' => '1. Creá tu carrito da compras',
              'html' => '<span style="font-family: verdana, geneva; font-size: 16pt;"><span style="font-size: 13pt;">La compra<strong> es individual&nbsp;por tienda</strong>, buscá tu favorita!&nbsp;</span></span><span style="font-family: verdana, geneva; font-size: 16pt;"><span style="font-size: 13pt;">El <strong>sistema de reputación</strong>&nbsp;Modapoint te ayuda a elegir mejor.</span></span><span style="font-family: verdana, geneva; font-size: 16pt;">&nbsp;</span><a style="padding: 2px 5px; border: 2px solid #e40175; background-color: #efefef; color: #e40175; border-radius: 3px; font-weight: bold;" href="../../?page=81">Ver Modapoints</a><span style="font-size: 13pt;">Cada marca tiene su mínimo de compra.&nbsp;Llená tu carrito con prendas <strong>hasta superar el monto mínimo</strong>.</span><span style="font-size: 13pt;">Pst! De viernes a domingo, las marcas bajan el monto mínimo ¡Aprovechá!&nbsp;</span>&nbsp;<a style="padding: 2px 5px; border: 2px solid #e40175; background-color: #efefef; color: #e40175; border-radius: 3px; font-weight: bold;" href="../../montosminimos">Ver Montos Rebajados</a>',
              'config' => [
                
              ]
            ],
            [
              'type' => 'section',
              'title' => '2. Elegí el método de envío',
              'html' => '<span style="font-family: verdana, geneva; font-size: 13pt;">Hacemos envíos a todo el país por <strong>Correo Argentino y OCA</strong>&nbsp;(sucursal y domicilio) y por&nbsp;<strong>Moto</strong> domicilio a&nbsp;Capital Federal&nbsp;o GBA.&nbsp;</span>&nbsp;<a style="padding: 2px 5px; border: 2px solid #e40175; background-color: #efefef; color: #e40175; border-radius: 3px; font-weight: bold;" href="../../envios">+Info de Envíos</a>&nbsp;<span style="font-family: verdana, geneva; font-size: 13pt;">Estas dos modalidades tienen<strong> seguro garantizado</strong>, además de poseer un número de guía único para que hagas el seguimiento</span><span style="font-size: 13pt;"><span style="font-family: verdana, geneva;">.</span></span><span style="font-size: 13pt;"><span style="font-family: verdana, geneva;">Se hacen envíos con otros transportes, pero sin garantía de Modatex.</span></span>',
              'config' => []
            ],
            [
              'type' => 'section',
              'title' => '3. Elegí el medio de pago',
              'html' => '<span style="font-family: verdana, geneva; font-size: 13pt;">Podés&nbsp;elegir<strong>&nbsp;todos los medios de pago</strong>: depósito bancario, transferencia, o Modapago (tarjetas de crédito o débito, Pago Fácil, Rapipago, etc.)<strong>&nbsp;</strong></span><span style="font-family: verdana, geneva; font-size: 13pt;"><strong>Modapago</strong>&nbsp;es el sistema de pagos de Modatex, el más seguro y simple.&nbsp;</span>&nbsp;<a style="padding: 2px 5px; border: 2px solid #e40175; background-color: #efefef; color: #e40175; border-radius: 3px; font-weight: bold;" href="../../modapago">+Info Modapago</a>&nbsp;',
              'config' => []
            ],
            [
              'type' => 'section',
              'title' => '4. Recibí la confirmación, aboná y listo!',
              'html' => '<span style="font-family: verdana, geneva; font-size: 13pt;">Una vez que le mandaste el pedido a la marca, esta tiene que chequear que tenga todo lo que pediste.</span><span style="font-family: verdana, geneva; font-size: 13pt;">Luego de que la tienda <strong>confirma el stock</strong>, <strong>te envía el cupón de pago por mail.</strong></span><span style="font-family: verdana, geneva; font-size: 13pt;">Una vez abonado, la marca procede a llevar tu paquete a la oficina de envíos y <strong>te va a aparecer el número de guía en tu perfil de modatex.</strong></span><span style="font-family: verdana, geneva; font-size: 13pt;"><strong>Y listo!</strong> Sólo&nbsp;hay que esperar que llegue.</span>',
              'config' => []
            ],
          ]
        ]
        // "redirect"=> [
        //   "route"=> "/how_to_buy",
        //   "params"=> []
        // ]
      ],
      [
        "icon" => '~/assets/icons/icon_menu_2.png',
        "name" => 'Envíos a todo el país',
        "disabled" => false,
        "redirect"=> [
          "route"=> "/shipping",
          "params"=> []
        ]
      ],
      [
        "icon" => '~/assets/icons/icon_menu_3.png',
        "name" => 'Formas de pago',
        "disabled" => false,
        "redirect"=> [
          "route"=> "/payment_methods",
          "params"=> []
        ]
      ],
      
    ];

  }

}
