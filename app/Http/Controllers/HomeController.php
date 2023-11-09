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
    return [
      [
        'id' => 5,
        'url' => 'https://netivooregon.s3.amazonaws.com/modatexrosa2/img/logo/primavera-verano-cms.jpg?n4',
        'name' => 'Nueva temporada',
        'editor' => '{"time":1698271878536,"blocks":[{"id":"Qh-Ce1laIS","type":"ImageCustom","data":{"url":"https://netivooregon.s3.amazonaws.com/modatexrosa2/img/logo/primavera-verano-cms.jpg?n4","caption":"nueva temporada "},"tunes":{"anchorTune":{"anchor":"ancla"},"categoriaTune":{"ocultarApp":false,"ocultarWeb":false},"configTune":{"expandir":true,"margin":{"top":{"node":{},"value":0,"placeholder":"Arriba","clave":"top"},"right":{"node":{},"value":0,"placeholder":"Derecha","clave":"right"},"bottom":{"node":{},"value":"42.6668","placeholder":"Abajo","clave":"bottom"},"left":{"node":{},"value":0,"placeholder":"Izquierda","clave":"left"}}}}},{"id":"w4OuZzuEmH","type":"paragraph","data":{"text":"<editorjs-style class=\"\" style=\"font-weight: bold\"><font size=\"5\">Temporada primavera verano 2024</font></editorjs-style>","alignment":"center"},"tunes":{"categoriaTune":{"ocultarApp":false,"ocultarWeb":false},"configTune":{"expandir":false}}},{"id":"sqR2wpOnIX","type":"Botones","data":{"botones":[{"id":"6d94dc70-621d-11ee-9bdc-af5c1be6f454","texto":{},"textoHtml":"Mujer","textoT":"Mujer","color":"#000000","redirect":{"route":"categorie","params":{"id":2,"name":"Mujer","key":"woman","clave":"mujer"}},"class":["m-2"],"offset":0,"tamano":"","type":"enlace"},{"id":"1ff25460-621e-11ee-b6d8-a365d4824fb1","texto":{},"textoHtml":"Hombre","textoT":"Hombre","color":"#000000","redirect":{"route":"categorie","params":{"id":3,"name":"Hombre","key":"man","clave":"hombre"}},"class":["m-2"],"offset":0,"tamano":"","type":"enlace"},{"id":"5e6aed80-7383-11ee-a9fe-e5d275054588","texto":{},"textoHtml":"Calzado y accesorios","textoT":"Calzado y accesorios","color":"#000000","redirect":{"route":"ancla","params":"#anclita"},"class":["m-2"],"offset":0,"tamano":"","type":"enlace"},{"id":"01bf5a50-621f-11ee-873e-1bbd1f7afadd","texto":{},"textoHtml":"Infantil","textoT":"Infantil","color":"#000000","redirect":{"params":""},"class":["m-2"],"offset":0,"tamano":"","type":"enlace"}],"isList":false},"tunes":{"alignmentTune":{"alignment":"center"},"categoriaTune":{"ocultarApp":false,"ocultarWeb":false},"configTune":{"expandir":false,"margin":{"top":{"node":{},"value":0,"placeholder":"Arriba","clave":"top"},"right":{"node":{},"value":0,"placeholder":"Derecha","clave":"right"},"bottom":{"node":{},"value":"42.6668","placeholder":"Abajo","clave":"bottom"},"left":{"node":{},"value":0,"placeholder":"Izquierda","clave":"left"}}}}},{"id":"Vc8J0fsi21","type":"Imagenes","data":{"id":"d78bf340-7382-11ee-a9fe-e5d275054588","productos":[{"image":"https://netivooregon.s3.amazonaws.com/modatexrosa2/img/logo/primavera-verano-cebra.jpg?n1","caption":{},"captionHtmlw":"ver cebra","ocultarTitulo":false,"redirect":{"route":"search","params":{"value":"cebra","categoria":{"id":2,"name":"Mujer","key":"woman","clave":"mujer"}}},"clases":{"mobile":["6"],"desktop":["6"]},"offset":0,"captionHtml":"ver cebra"},{"image":"https://netivooregon.s3.amazonaws.com/modatexrosa2/img/logo/primavera-verano-floreado.jpg","caption":{},"captionHtmlw":"ver floreado","ocultarTitulo":false,"redirect":{"route":"search","params":{"value":"floreado","categoria":{"id":1,"name":"Todo","key":"all","clave":"all"}}},"clases":{"mobile":["6"],"desktop":["6"]},"offset":0,"captionHtml":"ver floreado"},{"image":"https://netivooregon.s3.amazonaws.com/modatexrosa2/img/logo/primavera-verano-crepe.jpg?n1","caption":{},"captionHtmlw":"ver crepe","ocultarTitulo":false,"redirect":{"route":"store","params":{"logo":"https://netivooregon.s3.amazonaws.com/common/img/logo/e-motivo_1664223159.webp","name":"e-motivo","id":1008,"cleaned":"emotivo"}},"clases":{"mobile":["6"],"desktop":["6"]},"offset":0,"captionHtml":"ver crepe"},{"image":"https://netivooregon.s3.amazonaws.com/modatexrosa2/img/logo/primavera-verano-jean.jpg","caption":{},"captionHtmlw":"ver jean","ocultarTitulo":false,"redirect":{"route":"categorie","params":{"id":2,"name":"Mujer","key":"woman","clave":"mujer"}},"clases":{"mobile":["6"],"desktop":["6"]},"offset":0,"captionHtml":"ver jean"},{"image":"https://netivooregon.s3.amazonaws.com/modatexrosa2/img/logo/primavera-verano-pantalon.jpg","caption":{},"captionHtmlw":"ver pantalones","ocultarTitulo":false,"redirect":{"route":"page","params":{"id":"461","title":"Forma de pago","slug":"forma-de-pago"}},"clases":{"mobile":["6"],"desktop":["6"]},"offset":0,"captionHtml":"ver pantalones"},{"image":"https://netivooregon.s3.amazonaws.com/modatexrosa2/img/logo/primavera-verano-blusa.jpg","caption":{},"captionHtmlw":"ver blusas","ocultarTitulo":false,"redirect":{"route":"link","params":"https://translate.google.com.ar/?hl=es&amp;sl=en&amp;tl=es&amp;text=tailwind&amp;op=translate"},"clases":{"mobile":["6"],"desktop":["6"]},"offset":0,"captionHtml":"ver blusas"},{"image":"https://netivooregon.s3.amazonaws.com/modatexrosa2/img/logo/primavera-verano-shortpollera.jpg?n1","caption":{},"captionHtmlw":"ver short pollera","ocultarTitulo":false,"redirect":{"route":"search","params":"short pollera"},"clases":{"mobile":["6"],"desktop":["6"]},"offset":0,"captionHtml":"ver short pollera"},{"image":"https://netivooregon.s3.amazonaws.com/modatexrosa2/img/logo/primavera-verano-vestido.jpg","caption":{},"captionHtmlw":"ver vetidos","ocultarTitulo":false,"redirect":{"route":"search","params":"vestidos"},"clases":{"mobile":["6"],"desktop":["6"]},"offset":0,"captionHtml":"ver vetidos"},{"image":"https://netivooregon.s3.amazonaws.com/modatexrosa2/img/logo/primavera-verano-pollera.jpg?n1","caption":{},"captionHtmlw":"ver polleras","ocultarTitulo":false,"redirect":{"route":"search","params":"polleras"},"clases":{"mobile":["6"],"desktop":["6"]},"offset":0,"captionHtml":"ver polleras"},{"image":"https://netivooregon.s3.amazonaws.com/modatexrosa2/img/logo/primavera-verano-short.jpg","caption":{},"captionHtmlw":"ver shorts","ocultarTitulo":false,"redirect":{"route":"search","params":"shorts"},"clases":{"mobile":["6"],"desktop":["6"]},"offset":0,"captionHtml":"ver shorts"},{"image":"https://netivooregon.s3.amazonaws.com/modatexrosa2/img/logo/primavera-verano-saco.jpg","caption":{},"captionHtmlw":"ver saquitos","ocultarTitulo":false,"redirect":{"route":"search","params":"saquitoss"},"clases":{"mobile":["4"],"desktop":["4"]},"offset":0,"captionHtml":"ver saquitos"},{"image":"https://netivooregon.s3.amazonaws.com/modatexrosa2/img/logo/primavera-verano-mono.jpg","caption":{},"captionHtmlw":"ver enteritos","ocultarTitulo":false,"redirect":{"route":"search","params":"enteritos"},"clases":{"mobile":["4"],"desktop":["4"]},"offset":0,"captionHtml":"ver enteritos"},{"image":"https://netivooregon.s3.amazonaws.com/modatexrosa2/img/logo/primavera-verano-biker.jpg","caption":{},"captionHtmlw":"ver bikers","ocultarTitulo":false,"redirect":{"route":"search","params":"biker"},"clases":{"mobile":["4"],"desktop":["4"]},"offset":0,"captionHtml":"ver bikers"},{"image":"https://netivooregon.s3.amazonaws.com/modatexrosa2/img/logo/primavera-verano-remera.jpg?n1","caption":{},"captionHtmlw":"ver remeras","ocultarTitulo":false,"redirect":{"route":"search","params":"remeras"},"clases":{"mobile":["6"],"desktop":["6"]},"offset":0,"captionHtml":"ver remeras"}],"config":{"expandir":false,"isSlider":false}},"tunes":{"categoriaTune":{"ocultarApp":false,"ocultarWeb":false},"configTune":{"expandir":false}}},{"id":"J9BBz9yjvM","type":"Imagenes","data":{"id":"d78cdda0-7382-11ee-a9fe-e5d275054588","productos":[{"image":"https://netivooregon.s3.amazonaws.com/modatexrosa2/img/logo/primavera-verano-cat-calzado.jpg?n1","caption":{},"captionHtmlw":"Calzado y Accesorios","ocultarTitulo":false,"redirect":{"params":""},"clases":{"mobile":["6"],"desktop":["6"]},"offset":3,"captionHtml":"Calzado y Accesorios"}],"config":{"expandir":false,"isSlider":false}},"tunes":{"anchorTune":{"anchor":"anclita"},"categoriaTune":{"ocultarApp":false,"ocultarWeb":false},"configTune":{"expandir":false}}},{"id":"x2iHDOGYAZ","type":"Botones","data":{"botones":[{"id":"28229b80-630f-11ee-9eec-8d24474b6628","texto":{},"textoHtml":"Sandalias","textoT":"Sandalias","color":"#000000","redirect":{"route":"search","params":{"value":"sandalias","categoria":{"id":1,"name":"Todo","key":"all","clave":"all"}}},"class":["m-2"],"offset":0,"tamano":"","type":"enlace"},{"id":"474d4690-630f-11ee-9eec-8d24474b6628","texto":{},"textoHtml":"Zapatillas urbanas","textoT":"Zapatillas urbanas","color":"#000000","redirect":{"route":"search","params":{"value":"zapatillas","categoria":{"id":1,"name":"Todo","key":"all","clave":"all"}}},"class":["m-2"],"offset":0,"tamano":"","type":"enlace"},{"id":"58b80be0-630f-11ee-9eec-8d24474b6628","texto":{},"textoHtml":"Zapatos","textoT":"Zapatos","color":"#000000","redirect":{"route":"search","params":{"value":"zapato","categoria":{"id":1,"name":"Todo","key":"all","clave":"all"}}},"class":["m-2"],"offset":0,"tamano":"","type":"enlace"}],"isList":false},"tunes":{"alignmentTune":{"alignment":"center"},"categoriaTune":{"ocultarApp":false,"ocultarWeb":false},"configTune":{"expandir":false}}}],"version":"2.28.2"}',
      ],
      [ 
        'id' => 6,
        'url'=> 'https://netivooregon.s3.amazonaws.com/modatexrosa2/img/logo/lunesmayor-cms.jpg?n1',
        'name'=> 'Lunes por mayor',
        'editor' => '{"time":1698300653929,"blocks":[{"id":"8m3a8s4OAB","type":"Imagenes","data":{"id":"4eb86410-73c6-11ee-bb6a-173730c5f79d","productos":[{"image":"https://netivooregon.s3.amazonaws.com/modatexrosa2/img/logo/lunesmayor-cms.jpg?n1","caption":{},"captionHtmlw":"TÃ­tulo","ocultarTitulo":true,"redirect":{"params":""},"clases":{"mobile":["8"],"desktop":["4"]},"offset":0,"captionHtml":"TÃ­tulo"}],"centrar":true,"isSlider":false,"config":{"expandir":false,"isSlider":false}},"tunes":{"categoriaTune":{"ocultarApp":false,"ocultarWeb":false},"configTune":{"backgroundColor":"#e7e6e1","expandir":true,"margin":{"top":{"node":{},"value":0,"placeholder":"Arriba","clave":"top"},"right":{"node":{},"value":0,"placeholder":"Derecha","clave":"right"},"bottom":{"node":{},"value":"64.0002","placeholder":"Abajo","clave":"bottom"},"left":{"node":{},"value":0,"placeholder":"Izquierda","clave":"left"}}}}},{"id":"lJr8zkhke3","type":"header","data":{"text":"MirÃ¡ este beneficio para comprar ropa por mayor.","level":3},"tunes":{"alignmentTune":{"alignment":"center"},"configTune":{"expandir":false,"margin":{"top":{"value":0,"placeholder":"Arriba","clave":"top"},"right":{"value":0,"placeholder":"Derecha","clave":"right"},"bottom":{"value":0,"placeholder":"Abajo","clave":"bottom"},"left":{"value":0,"placeholder":"Izquierda","clave":"left"}}}}},{"id":"YICL3-q1YE","type":"paragraph","data":{"text":"Comprando los Lunes a partir de $60.000, tenÃ©s $3.000 de descuento. Combinalo con EnvÃ­o Gratis en tiendas seleccionadas a sucursal de Correo Argentino y OCA a todo el paÃ­s y a domicilio por Moto dentro de Capital Federal y GBA.","alignment":"center"},"tunes":{"categoriaTune":{"ocultarApp":false,"ocultarWeb":false},"configTune":{"expandir":false,"margin":{"top":{"node":{},"value":0,"placeholder":"Arriba","clave":"top"},"right":{"node":{},"value":0,"placeholder":"Derecha","clave":"right"},"bottom":{"node":{},"value":"42.6668","placeholder":"Abajo","clave":"bottom"},"left":{"node":{},"value":0,"placeholder":"Izquierda","clave":"left"}}}}},{"id":"1lvLOjUssJ","type":"paragraph","data":{"text":"Ahora tenÃ©s hasta 12 cuotas pagando por","alignment":"center"},"tunes":{"categoriaTune":{"ocultarApp":false,"ocultarWeb":false},"configTune":{"expandir":false,"margin":{"top":{"value":0,"placeholder":"Arriba","clave":"top"},"right":{"value":0,"placeholder":"Derecha","clave":"right"},"bottom":{"value":0,"placeholder":"Abajo","clave":"bottom"},"left":{"value":0,"placeholder":"Izquierda","clave":"left"}}}}},{"id":"jfUNMcAQcK","type":"Imagenes","data":{"id":"4eb90050-73c6-11ee-bb6a-173730c5f79d","productos":[{"image":"https://netivooregon.s3.amazonaws.com/modatexrosa2/img/logo/modapago-logo-cms.png?n1","caption":{},"captionHtmlw":"TÃ­tulo","ocultarTitulo":true,"redirect":{"params":""},"clases":{"mobile":["4"],"desktop":["2"]},"offset":0,"captionHtml":"TÃ­tulo"}],"centrar":true,"isSlider":false,"config":{"expandir":false,"isSlider":false}},"tunes":{"categoriaTune":{"ocultarApp":false,"ocultarWeb":false},"configTune":{"expandir":false,"margin":{"top":{"node":{},"value":0,"placeholder":"Arriba","clave":"top"},"right":{"node":{},"value":0,"placeholder":"Derecha","clave":"right"},"bottom":{"node":{},"value":"42.6668","placeholder":"Abajo","clave":"bottom"},"left":{"node":{},"value":0,"placeholder":"Izquierda","clave":"left"}}}}},{"id":"Kj8kJgiQTp","type":"Botones","data":{"botones":[{"id":"65aaa310-73c4-11ee-b0a8-c1938c6ff7c8","texto":{},"textoHtml":"Ver tarjetas","textoT":"Ver tarjetas","color":"#000000","redirect":{"route":"page","params":{"id":"458","title":"Como comprar","slug":"como-comprar"}},"class":["m-2"],"offset":0,"tamano":"","type":"btn"}],"isList":false},"tunes":{"alignmentTune":{"alignment":"center"},"categoriaTune":{"ocultarApp":false,"ocultarWeb":false},"configTune":{"expandir":false,"margin":{"top":{"node":{},"value":0,"placeholder":"Arriba","clave":"top"},"right":{"node":{},"value":0,"placeholder":"Derecha","clave":"right"},"bottom":{"node":{},"value":"42.6668","placeholder":"Abajo","clave":"bottom"},"left":{"node":{},"value":0,"placeholder":"Izquierda","clave":"left"}}}}},{"id":"US2e0sg7ss","type":"paragraph","data":{"text":"<span style=\"font-size: small;\">ComprÃ¡ online en los locales de Flores, Avenida Avellaneda</span>","alignment":"center"},"tunes":{"categoriaTune":{"ocultarApp":false,"ocultarWeb":false},"configTune":{"expandir":false,"margin":{"top":{"value":0,"placeholder":"Arriba","clave":"top"},"right":{"value":0,"placeholder":"Derecha","clave":"right"},"bottom":{"value":0,"placeholder":"Abajo","clave":"bottom"},"left":{"value":0,"placeholder":"Izquierda","clave":"left"}}}}},{"id":"2ol9SnSDEX","type":"paragraph","data":{"text":"<span style=\"font-size: small;\">Si tenÃ©s una tienda de ropa, esta promociÃ³n es especial para vos! TambiÃ©n podÃ©s aprovechar para renovar tu guardarropa, o comprar en conjunto con amigos.</span>","alignment":"center"},"tunes":{"categoriaTune":{"ocultarApp":false,"ocultarWeb":false},"configTune":{"expandir":false,"margin":{"top":{"node":{},"value":0,"placeholder":"Arriba","clave":"top"},"right":{"node":{},"value":0,"placeholder":"Derecha","clave":"right"},"bottom":{"node":{},"value":"64.0002","placeholder":"Abajo","clave":"bottom"},"left":{"node":{},"value":0,"placeholder":"Izquierda","clave":"left"}}}}},{"id":"RbCTw84GhE","type":"header","data":{"text":"Marcas con EnvÃ­o Gratis","level":3},"tunes":{"alignmentTune":{"alignment":"center"},"configTune":{"backgroundColor":null,"expandir":true,"margin":{"top":{"value":0,"placeholder":"Arriba","clave":"top"},"right":{"value":0,"placeholder":"Derecha","clave":"right"},"bottom":{"value":0,"placeholder":"Abajo","clave":"bottom"},"left":{"value":0,"placeholder":"Izquierda","clave":"left"}}}}},{"id":"FLPA55g0Rw","type":"Marcas","data":{"marcas":[{"id":1914,"name":"200NUDOS","name_id":"200NUDOS","logo":"https://netivooregon.s3.amazonaws.com/common/img/logo/200nudos_1659552628.webp","sections":{"woman":"blue","xl":"blue"},"cleaned":"200nudos","col":"2","offset":0},{"id":2467,"name":"26YARDAS","name_id":"26YARDAS","logo":"https://netivooregon.s3.amazonaws.com/common/img/logo/26yardas_1638475424.webp","sections":{"woman":"blue","xl":"blue"},"cleaned":"26yardas","col":"2","offset":0},{"id":2478,"name":"2DPN","name_id":"2DPN","logo":"https://netivooregon.s3.amazonaws.com/common/img/logo/2dpn_1644947223.webp","sections":{"woman":"blue"},"cleaned":"2dpn","col":"2","offset":0},{"id":1204,"name":"Accueil","name_id":"Accueil","logo":"https://netivooregon.s3.amazonaws.com/common/img/logo/accueil_1614021171.webp","sections":{"woman":"blue"},"cleaned":"accueil","col":"2","offset":0},{"id":2143,"name":"ADUQUESA","name_id":"ADUQUESA","logo":"https://netivooregon.s3.amazonaws.com/common/img/logo/aduquesa_1600886197.webp","sections":{"accessories":"black"},"cleaned":"aduquesa","col":"2","offset":0},{"id":2040,"name":"AIME","name_id":"AIME","logo":"https://netivooregon.s3.amazonaws.com/common/img/logo/aime_1669917809.webp","sections":{"woman":"gold"},"cleaned":"aime","col":"2","offset":0},{"id":2062,"name":"AIX","name_id":"AIX","logo":"https://netivooregon.s3.amazonaws.com/common/img/logo/aix_1593458025.webp","sections":{"woman":"blue"},"cleaned":"aix","col":"2","offset":0},{"id":2031,"name":"ALIPAPA","name_id":"ALIPAPA","logo":"https://netivooregon.s3.amazonaws.com/common/img/logo/alipapa_1633544236.webp","sections":{"woman":"blue","xl":"black","sportive":"blue"},"cleaned":"alipapa","col":"2","offset":0},{"id":2120,"name":"AMAIKA","name_id":"AMAIKA","logo":"https://netivooregon.s3.amazonaws.com/common/img/logo/amaika_1616526803.webp","sections":{"woman":"black"},"cleaned":"amaika","col":"2","offset":0},{"id":2297,"name":"ANELEN","name_id":"ANELEN","logo":"https://netivooregon.s3.amazonaws.com/common/img/logo/anelen_1619811588.webp","sections":{"woman":"platinum"},"cleaned":"anelen","col":"2","offset":0},{"id":2406,"name":"ANELLA LINGERIE","name_id":"ANELLALINGERIE","logo":"https://netivooregon.s3.amazonaws.com/common/img/logo/anellalingerie_1629835492.webp","sections":{"kids":"blue","woman":"blue","man":"blue","lingerie":"blue"},"cleaned":"anellalingerie","col":"2","offset":0},{"id":1669,"name":"ARACELI","name_id":"ARACELI","logo":"https://netivooregon.s3.amazonaws.com/common/img/logo/araceli_1597363121.webp","sections":{"woman":"blue"},"cleaned":"araceli","col":"2","offset":0},{"id":1597,"name":"ARCHIN","name_id":"ARCHIN","logo":"https://netivooregon.s3.amazonaws.com/common/img/logo/archin_1691072179.webp","sections":{"woman":"platinum"},"cleaned":"archin","col":"2","offset":0}]},"tunes":{"categoriaTune":{"ocultarApp":false,"ocultarWeb":false},"configTune":{"expandir":false,"margin":{"top":{"value":"42.6668","placeholder":"Arriba","clave":"top","node":{}},"right":{"value":0,"placeholder":"Derecha","clave":"right","node":{}},"bottom":{"value":"42.6668","placeholder":"Abajo","clave":"bottom","node":{}},"left":{"value":0,"placeholder":"Izquierda","clave":"left","node":{}}}}}},{"id":"buRi9aPTV8","type":"list","data":{"style":"unordered","items":["El cupÃ³n se generarÃ¡ una vez superados los $60.000","El cupÃ³n debe estar seleccionado previo a la confirmaciÃ³n del pedido.","El cupÃ³n solo puede utilizarse durante el mismo dÃ­a en el que se realizÃ³ el pedido y en la misma marca.<br>","Para poder realizar el pedido, el total de la compra (incluido el cupÃ³n) deberÃ¡ ser igual o mayor al monto mÃ­nimo de compra de la marca expresado en el banner. El total no incluye el valor del envÃ­o.<br>","PromociÃ³n no acumulable con otros cupones de Modatex.<br>","Se otorgarÃ¡n un total de 1 cupÃ³n por compra. 1 cupÃ³n = 1 compra.<br>","PromociÃ³n vÃ¡lida hasta diciembre 2023 inclusive.<br>"]},"tunes":{"categoriaTune":{"ocultarApp":false,"ocultarWeb":false},"configTune":{"expandir":false,"margin":{"top":{"node":{},"value":"64.0002","placeholder":"Arriba","clave":"top"},"right":{"node":{},"value":0,"placeholder":"Derecha","clave":"right"},"bottom":{"node":{},"value":"42.6668","placeholder":"Abajo","clave":"bottom"},"left":{"node":{},"value":0,"placeholder":"Izquierda","clave":"left"}}}}}],"version":"2.28.2"}',
        
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

  /**
   * GET CATEGORIAS
   */
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
        'id' => 0,
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
      [
        'id' => 8,
        'name' => 'Deportivo',
        'type' =>  'page',
        'search' =>  '',
        'key' =>  'page', 
        'icon' => 'res://tshirt',
        'color' =>  "",
        'colSpan' =>  2,
        'col' =>  4,
        'row' =>  1,
        'left' =>  35,
        'top' =>  20,
        'editor' => '{"time":1699498476031,"blocks":[{"id":"yMpr7eM-Sr","type":"Cupones","data":{"cupones":[{"code":"ARACELINOV500","id":"5769","price":"500.00","expire_date":"2023-11-30","LOGO_FILE_NAME":"https://netivooregon.s3.amazonaws.com/common/img/logo/araceli_1597363121.webp","LOCAL_NAME":"ARACELI","LOCAL_CD":"1669","col":2,"offset":0}],"isSlider":false},"tunes":{"categoriaTune":{"ocultarApp":false,"ocultarWeb":false},"configTune":{"expandir":false,"margin":{"top":{"value":"42.6668","placeholder":"Arriba","clave":"top","node":{}},"right":{"value":0,"placeholder":"Derecha","clave":"right","node":{}},"bottom":{"value":"213.334","placeholder":"Abajo","clave":"bottom","node":{}},"left":{"value":0,"placeholder":"Izquierda","clave":"left","node":{}}}}}},{"id":"EMrVg8rj2P","type":"CanjeCupon","data":{"form":{"placeholder":"CÃ³digo de cupÃ³n","button":"Enviar"}},"tunes":{"categoriaTune":{"ocultarApp":false,"ocultarWeb":false},"configTune":{"expandir":false,"margin":{"top":{"value":0,"placeholder":"Arriba","clave":"top"},"right":{"value":0,"placeholder":"Derecha","clave":"right"},"bottom":{"value":0,"placeholder":"Abajo","clave":"bottom"},"left":{"value":0,"placeholder":"Izquierda","clave":"left"}}}}}],"version":"2.28.2"}'
      ],
    ];
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
        "name" => 'Descuentos',
        "disabled" => false,
        // "redirect"=> [
        //   "route"=> "/discount_especial",
        //   "params"=> []
        // ]
        "editor" => '{"time":1698293936194,"blocks":[{"id":"Fon9ti6tPR","type":"ImageCustom","data":{"url":"https://netivooregon.s3.amazonaws.com/modatexrosa2/img/logo/martescupones-cms.jpg?n3","caption":"cupones"},"tunes":{"categoriaTune":{"ocultarApp":false,"ocultarWeb":false},"configTune":{"expandir":true,"margin":{"top":{"value":"0","placeholder":"Arriba","clave":"top","node":{}},"right":{"value":0,"placeholder":"Derecha","clave":"right","node":{}},"bottom":{"value":"21.3334","placeholder":"Abajo","clave":"bottom","node":{}},"left":{"value":0,"placeholder":"Izquierda","clave":"left","node":{}}}}}},{"id":"TowIMVe4oP","type":"Imagenes","data":{"id":"b67e0ba0-73b6-11ee-8cc7-d950fdbf114b","productos":[{"image":"https://netivooregon.s3.amazonaws.com/modatexrosa2/img/logo/cupones-extra-promos.jpg?n4","caption":{},"captionHtmlw":"cupones","ocultarTitulo":true,"redirect":{"params":""},"clases":{"mobile":["8"],"desktop":["8"]},"offset":0,"captionHtml":"cupones"}],"centrar":true,"isSlider":false,"config":{"expandir":false,"isSlider":false}},"tunes":{"categoriaTune":{"ocultarApp":false,"ocultarWeb":false},"configTune":{"expandir":false,"margin":{"top":{"value":"21.3334","placeholder":"Arriba","clave":"top","node":{}},"right":{"value":0,"placeholder":"Derecha","clave":"right","node":{}},"bottom":{"value":"0","placeholder":"Abajo","clave":"bottom","node":{}},"left":{"value":0,"placeholder":"Izquierda","clave":"left","node":{}}},"backgroundColor":"#ffeeff"}}},{"id":"AgUz_g3zEZ","type":"Cupones","data":{"cupones":[{"LOGO_FILE_NAME":"https://netivooregon.s3.amazonaws.com/common/img/logo/boom_1630381812.webp","LOCAL_NAME":"BOOM","code":"BOOM200","id":"482","price":"200.00","expire_date":"2019-10-20","LOCAL_CD":"1949","col":2,"offset":0},{"LOGO_FILE_NAME":"https://netivooregon.s3.amazonaws.com/common/img/logo/dasom_1598037855.webp","LOCAL_NAME":"DASOM","code":"DASOM300","id":"1314","price":"300.00","expire_date":"2020-05-31","LOCAL_CD":"2011","col":2,"offset":0},{"LOGO_FILE_NAME":"https://netivooregon.s3.amazonaws.com/common/img/logo/amaika_1616526803.webp","LOCAL_NAME":"AMAIKA","code":"AMAIKA13","id":"4621","price":"750.00","expire_date":"2022-12-13","LOCAL_CD":"2120","col":2,"offset":0},{"LOGO_FILE_NAME":"https://netivooregon.s3.amazonaws.com/common/img/logo/prany_1671716156.webp","LOCAL_NAME":"PRANY","code":"PRANY300","id":"2487","price":"300.00","expire_date":"2021-03-31","LOCAL_CD":"2228","col":2,"offset":0},{"LOGO_FILE_NAME":"https://netivooregon.s3.amazonaws.com/common/img/logo/archin_1691072179.webp","LOCAL_NAME":"ARCHIN","code":"ARCHIN200","id":"832","price":"200.00","expire_date":"2020-01-31","LOCAL_CD":"1597","col":2,"offset":0},{"LOGO_FILE_NAME":"https://netivooregon.s3.amazonaws.com/common/img/logo/araceli_1597363121.webp","LOCAL_NAME":"ARACELI","code":"ARACELI7","id":"3730","price":"400.00","expire_date":"2022-03-13","LOCAL_CD":"1669","col":2,"offset":0},{"LOGO_FILE_NAME":"https://netivooregon.s3.amazonaws.com/common/img/logo/badalona_1603917027.webp","LOCAL_NAME":"BADALONA","code":"BADALONA300","id":"1978","price":"300.00","expire_date":"2020-11-01","LOCAL_CD":"2168","col":2,"offset":0},{"LOGO_FILE_NAME":"https://netivooregon.s3.amazonaws.com/common/img/logo/blackolive_1547561960.webp","LOCAL_NAME":"BLACK OLIVE","code":"BLACKOLIVE100","id":"233","price":"100.00","expire_date":"2019-08-11","LOCAL_CD":"1753","col":2,"offset":0},{"LOGO_FILE_NAME":"https://netivooregon.s3.amazonaws.com/common/img/logo/chufinno_1582744610.webp","LOCAL_NAME":"CHUFINNO","code":"CHUFINNO100","id":"574","price":"100.00","expire_date":"2019-11-30","LOCAL_CD":"1926","col":2,"offset":0},{"LOGO_FILE_NAME":"https://netivooregon.s3.amazonaws.com/common/img/logo/derfuria_1571247520.webp","LOCAL_NAME":"DERFURIA","code":"DERFURIA300","id":"912","price":"300.00","expire_date":"2020-02-02","LOCAL_CD":"1957","col":2,"offset":0},{"LOGO_FILE_NAME":"https://netivooregon.s3.amazonaws.com/common/img/logo/explosive_1620757743.webp","LOCAL_NAME":"EXPLOSIVE","code":"EXPLOSIVE300","id":"2646","price":"300.00","expire_date":"2021-04-26","LOCAL_CD":"2271","col":2,"offset":0},{"LOGO_FILE_NAME":"https://netivooregon.s3.amazonaws.com/common/img/logo/leonaxstyle_1619205578.webp","LOCAL_NAME":"LEONA X STYLE","code":"LEONAXSTYLE300","id":"2667","price":"300.00","expire_date":"2021-05-31","LOCAL_CD":"2285","col":2,"offset":0},{"LOGO_FILE_NAME":"https://netivooregon.s3.amazonaws.com/common/img/logo/mamba_1655402051.webp","LOCAL_NAME":"MAMBA","code":"MAMBA28","id":"4780","price":"750.00","expire_date":"2023-02-28","LOCAL_CD":"2520","col":2,"offset":0},{"LOGO_FILE_NAME":"https://netivooregon.s3.amazonaws.com/common/img/logo/sama_1618508683.webp","LOCAL_NAME":"SAMA","code":"SAMA400","id":"3405","price":"400.00","expire_date":"2021-10-31","LOCAL_CD":"2270","col":2,"offset":0},{"LOGO_FILE_NAME":"https://netivooregon.s3.amazonaws.com/common/img/logo/sincostura_1683891218.webp","LOCAL_NAME":"SIN COSTURA","code":"SINCOSTURA27","id":"5196","price":"1000.00","expire_date":"2023-06-27","LOCAL_CD":"2580","col":2,"offset":0},{"LOGO_FILE_NAME":"https://netivooregon.s3.amazonaws.com/common/img/logo/tukson_1661268272.webp","LOCAL_NAME":"TUKSON","code":"TUKSON6","id":"5103","price":"1000.00","expire_date":"2023-06-06","LOCAL_CD":"2147","col":2,"offset":0},{"LOGO_FILE_NAME":"https://netivooregon.s3.amazonaws.com/common/img/logo/milow_1620069811.webp","LOCAL_NAME":"MILOW","code":"MILOW16","id":"2742","price":"300.00","expire_date":"2021-05-16","LOCAL_CD":"2301","col":2,"offset":0},{"LOGO_FILE_NAME":"https://netivooregon.s3.amazonaws.com/common/img/logo/skandall_1686675490.webp","LOCAL_NAME":"SKANDALL","code":"SKANDALL20","id":"5166","price":"1000.00","expire_date":"2023-06-20","LOCAL_CD":"2588","col":2,"offset":0},{"LOGO_FILE_NAME":"https://netivooregon.s3.amazonaws.com/common/img/logo/arizona_1542739316.webp","LOCAL_NAME":"ARIZONA","code":"ARIZONA100","id":"43","price":"100.00","expire_date":null,"LOCAL_CD":"1506","col":2,"offset":0},{"LOGO_FILE_NAME":"https://netivooregon.s3.amazonaws.com/common/img/logo/valco_1545077526.webp","LOCAL_NAME":"VALCO","code":"VALCO300","id":"8","price":"300.00","expire_date":null,"LOCAL_CD":"1846","col":2,"offset":0}],"isSlider":false},"tunes":{"categoriaTune":{"ocultarApp":false,"ocultarWeb":false},"configTune":{"expandir":false,"margin":{"top":{"value":0,"placeholder":"Arriba","clave":"top","node":{}},"right":{"value":"0","placeholder":"Derecha","clave":"right","node":{}},"bottom":{"value":0,"placeholder":"Abajo","clave":"bottom","node":{}},"left":{"value":"0","placeholder":"Izquierda","clave":"left","node":{}}},"backgroundColor":"#ffeeff"}}}],"version":"2.28.2"}',
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
        "editor" => '{"time":1698270929055,"blocks":[{"id":"WkDV8lYTW9","type":"header","data":{"text":"Preguntas Frecuentes","level":2},"tunes":{"alignmentTune":{"alignment":"center"},"configTune":{"expandir":false,"margin":{"top":{"node":{},"value":"42.6668","placeholder":"Arriba","clave":"top"},"right":{"node":{},"value":0,"placeholder":"Derecha","clave":"right"},"bottom":{"node":{},"value":0,"placeholder":"Abajo","clave":"bottom"},"left":{"node":{},"value":0,"placeholder":"Izquierda","clave":"left"}}}}},{"id":"Q-Sxx0Wj0e","type":"paragraph","data":{"text":"Â¡Te contamos en este video los<span style=\"color: rgb(236, 29, 132);\"> beneficios de comprar en Modatex!</span>","alignment":"center"},"tunes":{"categoriaTune":{"ocultarApp":false,"ocultarWeb":false},"configTune":{"expandir":false}}},{"id":"0osG10Xseq","type":"Video","data":{"video":{"url":"https://netivooregon.s3.amazonaws.com/modatexrosa2/img/logo/modatexvideo1.mp4 "}},"tunes":{"alignmentTune":{"alignment":"left"},"categoriaTune":{"ocultarApp":false,"ocultarWeb":false},"configTune":{"expandir":false,"margin":{"top":{"node":{},"value":"21.3334","placeholder":"Arriba","clave":"top"},"right":{"node":{},"value":0,"placeholder":"Derecha","clave":"right"},"bottom":{"node":{},"value":"21.3334","placeholder":"Abajo","clave":"bottom"},"left":{"node":{},"value":0,"placeholder":"Izquierda","clave":"left"}}}}},{"id":"Ing8IExKUU","type":"header","data":{"text":"&nbsp; Compras","level":3},"tunes":{"alignmentTune":{"alignment":"center"},"configTune":{"expandir":false}}},{"id":"XJj5cfa4u1","type":"Acordeon","data":{"acordeon":{"items":[{"id":"de38ec80-645e-11ee-a6d9-7d2d15a73a81","title":"Â¿CÃ³mo comprar en Modatex?","titleHtml":"Â¿CÃ³mo comprar en Modatex?","bodyEditorJSON":"{\"time\":1698270929051,\"blocks\":[{\"id\":\"g7kV0V0ZBE\",\"type\":\"header\",\"data\":{\"text\":\"1. IngresÃ¡ con tu cuenta\",\"level\":4},\"tunes\":{\"alignmentTune\":{\"alignment\":\"left\"},\"configTune\":{\"expandir\":false}}},{\"id\":\"GCuaUH04rc\",\"type\":\"paragraph\",\"data\":{\"text\":\"Si no tenÃ©s una cuenta en Modatex:\",\"alignment\":\"left\"},\"tunes\":{\"categoriaTune\":{\"ocultarApp\":false,\"ocultarWeb\":false},\"configTune\":{\"expandir\":false}}},{\"id\":\"asjVeD5-x8\",\"type\":\"Botones\",\"data\":{\"botones\":[{\"id\":\"ccf8cd90-66b7-11ee-8b9f-690081382141\",\"texto\":{},\"textoHtml\":\"RegÃ­strate acÃ¡\",\"textoT\":\"RegÃ­strate acÃ¡\",\"color\":\"#000000\",\"redirect\":{\"route\":\"ancla\",\"params\":\"?open_register=1\"},\"class\":[\"m-2\"],\"offset\":0,\"tamano\":\"\",\"type\":\"enlace\"}],\"isList\":false},\"tunes\":{\"alignmentTune\":{\"alignment\":\"left\"},\"categoriaTune\":{\"ocultarApp\":false,\"ocultarWeb\":false},\"configTune\":{\"expandir\":false}}},{\"id\":\"oLNgCljjeo\",\"type\":\"paragraph\",\"data\":{\"text\":\"Una vez que ingresas tus datos, va a llegarte un mail para verificar la cuenta. HacÃ© click en el botÃ³n verde de Verificar Cuenta. Y listo! Ya podÃ©s comprar en Modatex.\",\"alignment\":\"left\"},\"tunes\":{\"categoriaTune\":{\"ocultarApp\":false,\"ocultarWeb\":false},\"configTune\":{\"expandir\":false}}},{\"id\":\"ZsXF0bd4j-\",\"type\":\"Botones\",\"data\":{\"botones\":[{\"id\":\"80a35590-6460-11ee-8d11-57f7e35b7f30\",\"texto\":{},\"textoHtml\":\"Logueate acÃ¡\",\"textoT\":\"Logueate acÃ¡\",\"color\":\"#000000\",\"redirect\":{\"params\":\"\"},\"class\":[\"m-2\"],\"offset\":0,\"tamano\":\"\",\"type\":\"enlace\"}],\"isList\":false},\"tunes\":{\"alignmentTune\":{\"alignment\":\"left\"},\"categoriaTune\":{\"ocultarApp\":false,\"ocultarWeb\":false},\"configTune\":{\"expandir\":false}}},{\"id\":\"MyhLJHonEY\",\"type\":\"header\",\"data\":{\"text\":\"2. ArmÃ¡ tu carrito de compras\",\"level\":4},\"tunes\":{\"alignmentTune\":{\"alignment\":\"left\"},\"configTune\":{\"expandir\":false}}},{\"id\":\"tSsygfmzVs\",\"type\":\"paragraph\",\"data\":{\"text\":\"La compra es individual por tienda.\",\"alignment\":\"left\"},\"tunes\":{\"categoriaTune\":{\"ocultarApp\":false,\"ocultarWeb\":false},\"configTune\":{\"expandir\":false}}},{\"id\":\"tUleod8JMR\",\"type\":\"paragraph\",\"data\":{\"text\":\"PodÃ©s utilizar el buscador de la parte superior para encontrar un producto o buscar tu tienda favorita. Tenemos tiendas de las categorÃ­as:\",\"alignment\":\"left\"},\"tunes\":{\"categoriaTune\":{\"ocultarApp\":false,\"ocultarWeb\":false},\"configTune\":{\"expandir\":false}}},{\"id\":\"nfTmCtq0XW\",\"type\":\"Botones\",\"data\":{\"botones\":[{\"id\":\"cfbac5f0-6776-11ee-82f1-0b17fda9b23d\",\"texto\":{},\"textoHtml\":\"Mujer general\",\"textoT\":\"Mujer general\",\"color\":\"#000000\",\"redirect\":{\"route\":\"ancla\",\"params\":\"/\"},\"class\":[\"m-2\"],\"offset\":0,\"tamano\":\"\",\"type\":\"enlace\"},{\"id\":\"e013cd20-6776-11ee-82f1-0b17fda9b23d\",\"texto\":{},\"textoHtml\":\"Mujer XL\",\"textoT\":\"Mujer XL\",\"color\":\"#000000\",\"redirect\":{\"route\":\"categorie\",\"params\":{\"id\":4,\"name\":\"Talle especial\",\"key\":\"xl\",\"clave\":\"xl\"}},\"class\":[\"m-2\"],\"offset\":0,\"tamano\":\"\",\"type\":\"enlace\"},{\"id\":\"fc4ef960-6776-11ee-82f1-0b17fda9b23d\",\"texto\":{},\"textoHtml\":\"Hombre\",\"textoT\":\"Hombre\",\"color\":\"#000000\",\"redirect\":{\"route\":\"categorie\",\"params\":{\"id\":3,\"name\":\"Hombre\",\"key\":\"man\",\"clave\":\"hombre\"}},\"class\":[\"m-2\"],\"offset\":0,\"tamano\":\"\",\"type\":\"enlace\"},{\"id\":\"04f10ea0-6777-11ee-82f1-0b17fda9b23d\",\"texto\":{},\"textoHtml\":\"Infantil\",\"textoT\":\"Infantil\",\"color\":\"#000000\",\"redirect\":{\"route\":\"categorie\",\"params\":{\"id\":5,\"name\":\"NiÃ±os\",\"key\":\"kids\",\"clave\":\"ninos\"}},\"class\":[\"m-2\"],\"offset\":0,\"tamano\":\"\",\"type\":\"enlace\"},{\"id\":\"31089800-6777-11ee-82f1-0b17fda9b23d\",\"texto\":{},\"textoHtml\":\"Accesorios\",\"textoT\":\"Accesorios\",\"color\":\"#000000\",\"redirect\":{\"route\":\"categorie\",\"params\":{\"id\":6,\"name\":\"Accesorios\",\"key\":\"accessories\",\"clave\":\"accesorios\"}},\"class\":[\"m-2\"],\"offset\":0,\"tamano\":\"\",\"type\":\"enlace\"},{\"id\":\"3bbb16b0-6777-11ee-82f1-0b17fda9b23d\",\"texto\":{},\"textoHtml\":\"Deportivo\",\"textoT\":\"Deportivo\",\"color\":\"#000000\",\"redirect\":{\"route\":\"categorie\",\"params\":{\"id\":7,\"name\":\"Deportivo\",\"key\":\"deportivo\",\"clave\":\"deportivo\"}},\"class\":[\"m-2\"],\"offset\":0,\"tamano\":\"\",\"type\":\"enlace\"},{\"id\":\"43f409e0-6777-11ee-82f1-0b17fda9b23d\",\"texto\":{},\"textoHtml\":\"Lenceria\",\"textoT\":\"Lenceria\",\"color\":\"#000000\",\"redirect\":{\"route\":\"categorie\",\"params\":{\"id\":8,\"name\":\"Lenceria\",\"key\":\"lenceria\",\"clave\":\"lenceria\"}},\"class\":[\"m-2\"],\"offset\":0,\"tamano\":\"\",\"type\":\"enlace\"},{\"id\":\"5443c240-6777-11ee-82f1-0b17fda9b23d\",\"texto\":{},\"textoHtml\":\"Calzado\",\"textoT\":\"Calzado\",\"color\":\"#000000\",\"redirect\":{\"route\":\"categorie\",\"params\":{\"id\":10,\"name\":\"Calzado\",\"key\":\"zapatos\",\"clave\":\"zapatos\"}},\"class\":[\"m-2\"],\"offset\":0,\"tamano\":\"\",\"type\":\"enlace\"},{\"id\":\"5c908b90-6777-11ee-82f1-0b17fda9b23d\",\"texto\":{},\"textoHtml\":\"Hogar\",\"textoT\":\"Hogar\",\"color\":\"#000000\",\"redirect\":{\"route\":\"categorie\",\"params\":{\"id\":9,\"name\":\"Hogar\",\"key\":\"hogar\",\"clave\":\"index.php?page=287\"}},\"class\":[\"m-2\"],\"offset\":0,\"tamano\":\"\",\"type\":\"enlace\"}],\"isList\":false},\"tunes\":{\"alignmentTune\":{\"alignment\":\"left\"},\"categoriaTune\":{\"ocultarApp\":false,\"ocultarWeb\":false},\"configTune\":{\"expandir\":false}}},{\"id\":\"SdFJTu4A0N\",\"type\":\"paragraph\",\"data\":{\"text\":\"La reputaciÃ³n (REP) y Ventas Concretadas (VC) te ayudan a elegir mejor. Te recomendamos tiendas con calificaciÃ³n de mÃ¡s de 70 en ambas. Si tenÃ©s consultas sobre los artÃ­culos, hacÃ© clic en el botÃ³n Preguntas dentro de la tienda para hablar con la marca.\",\"alignment\":\"left\"},\"tunes\":{\"categoriaTune\":{\"ocultarApp\":false,\"ocultarWeb\":false},\"configTune\":{\"expandir\":false}}},{\"id\":\"uJ-hcIz71p\",\"type\":\"header\",\"data\":{\"text\":\"3. AlcanzÃ¡ el monto mÃ­nimo\",\"level\":4},\"tunes\":{\"alignmentTune\":{\"alignment\":\"left\"},\"configTune\":{\"expandir\":false}}},{\"id\":\"j7Bsleqj9L\",\"type\":\"paragraph\",\"data\":{\"text\":\"Cada marca es mayorista y tiene su monto mÃ­nimo de compra (podÃ©s verlo debajo del logo de la marca). AgregÃ¡ productos a tu carrito hasta llegar al mÃ­nimo de esa tienda. En general, no hace falta llevar muchas prendas. Incluso, de viernes a domingo, la mayorÃ­a rebaja sus montos para que puedas llevar de a una prenda.&nbsp;\",\"alignment\":\"left\"},\"tunes\":{\"categoriaTune\":{\"ocultarApp\":false,\"ocultarWeb\":false},\"configTune\":{\"expandir\":false}}},{\"id\":\"csQlaXJgxo\",\"type\":\"header\",\"data\":{\"text\":\"4. HacÃ© click en comprar y aplÃ­ca tus descuentos\",\"level\":4},\"tunes\":{\"alignmentTune\":{\"alignment\":\"left\"},\"configTune\":{\"expandir\":false}}},{\"id\":\"XHoCcZR_sG\",\"type\":\"paragraph\",\"data\":{\"text\":\"Si tenÃ©s cupones de descuento en la tienda que elegiste. AplÃ­calos en este paso.&nbsp;\",\"alignment\":\"left\"},\"tunes\":{\"categoriaTune\":{\"ocultarApp\":false,\"ocultarWeb\":false},\"configTune\":{\"expandir\":false}}},{\"id\":\"EoA--mYv-T\",\"type\":\"Botones\",\"data\":{\"botones\":[{\"id\":\"d708dc90-6779-11ee-8f92-0f8d5f52c561\",\"texto\":{},\"textoHtml\":\"Ver mis cupones\",\"textoT\":\"Ver mis cupones\",\"color\":\"#000000\",\"redirect\":{\"route\":\"ancla\",\"params\":\"/perfil?cupones\"},\"class\":[\"m-2\"],\"offset\":0,\"tamano\":\"\",\"type\":\"enlace\"}],\"isList\":false},\"tunes\":{\"alignmentTune\":{\"alignment\":\"left\"},\"categoriaTune\":{\"ocultarApp\":false,\"ocultarWeb\":false},\"configTune\":{\"expandir\":false}}},{\"id\":\"3dB2xGSEv6\",\"type\":\"paragraph\",\"data\":{\"text\":\"Todos los dÃ­as hay promociones y descuentos.\",\"alignment\":\"left\"},\"tunes\":{\"categoriaTune\":{\"ocultarApp\":false,\"ocultarWeb\":false},\"configTune\":{\"expandir\":false}}},{\"id\":\"rGV6q8mhqE\",\"type\":\"Botones\",\"data\":{\"botones\":[{\"id\":\"021b03e0-677a-11ee-8f92-0f8d5f52c561\",\"texto\":{},\"textoHtml\":\"Ver promos\",\"textoT\":\"Ver promos\",\"color\":\"#000000\",\"redirect\":{\"route\":\"page\",\"params\":{\"id\":\"126\",\"title\":\"Promociones | Modatex\",\"slug\":\"promociones\"}},\"class\":[\"m-2\"],\"offset\":0,\"tamano\":\"\",\"type\":\"enlace\"}],\"isList\":false},\"tunes\":{\"alignmentTune\":{\"alignment\":\"left\"},\"categoriaTune\":{\"ocultarApp\":false,\"ocultarWeb\":false},\"configTune\":{\"expandir\":false}}},{\"id\":\"owcg9fDaLc\",\"type\":\"header\",\"data\":{\"text\":\"5. ElegÃ­ el mÃ©todo de envÃ­o\",\"level\":4},\"tunes\":{\"alignmentTune\":{\"alignment\":\"left\"},\"configTune\":{\"expandir\":false}}},{\"id\":\"zbb7YFilQG\",\"type\":\"paragraph\",\"data\":{\"text\":\"Hay envÃ­os a todo el paÃ­s por OCA, Correo Argentino, Integral Pack y transportes tradicionales. TambiÃ©n, hay envÃ­os por moto dentro del AMBA. Estas modalidades estÃ¡n a 100% aseguradas por Modatex y garantizadas por siniestro o extravÃ­o. AdemÃ¡s, podÃ©s hacer el seguimiento del paquete.\",\"alignment\":\"left\"},\"tunes\":{\"categoriaTune\":{\"ocultarApp\":false,\"ocultarWeb\":false},\"configTune\":{\"expandir\":false}}},{\"id\":\"-f_4qBdDQU\",\"type\":\"Botones\",\"data\":{\"botones\":[{\"id\":\"c176fb70-677c-11ee-ab4f-8b7ecee75b42\",\"texto\":{},\"textoHtml\":\"Ver precios, Plazos y Seguimiento\",\"textoT\":\"Ver precios, Plazos y Seguimiento\",\"color\":\"#000000\",\"redirect\":{\"route\":\"page\",\"params\":{\"id\":\"460\",\"title\":\"Envios new\",\"slug\":\"envios-new\"}},\"class\":[\"m-2\"],\"offset\":0,\"tamano\":\"\",\"type\":\"enlace\"}],\"isList\":false},\"tunes\":{\"alignmentTune\":{\"alignment\":\"left\"},\"categoriaTune\":{\"ocultarApp\":false,\"ocultarWeb\":false},\"configTune\":{\"expandir\":false}}},{\"id\":\"8-JjFa3hyp\",\"type\":\"paragraph\",\"data\":{\"text\":\"Promos de envÃ­os\",\"alignment\":\"left\"},\"tunes\":{\"categoriaTune\":{\"ocultarApp\":false,\"ocultarWeb\":false},\"configTune\":{\"expandir\":false}}},{\"id\":\"uGX9ET3NNH\",\"type\":\"Botones\",\"data\":{\"botones\":[{\"id\":\"e1ee4ac0-677c-11ee-ab4f-8b7ecee75b42\",\"texto\":{},\"textoHtml\":\"EnvÃ­os 75% OFF\",\"textoT\":\"EnvÃ­os 75% OFF\",\"color\":\"#000000\",\"redirect\":{\"route\":\"page\",\"params\":{\"id\":\"352\",\"title\":\"Pink Days | EnvÃ­os Baratos | Modatex\",\"slug\":\"pinkdays\"}},\"class\":[\"m-2\"],\"offset\":0,\"tamano\":\"\",\"type\":\"enlace\"}],\"isList\":false},\"tunes\":{\"alignmentTune\":{\"alignment\":\"left\"},\"categoriaTune\":{\"ocultarApp\":false,\"ocultarWeb\":false},\"configTune\":{\"expandir\":false}}},{\"id\":\"nGBvI0NSe-\",\"type\":\"header\",\"data\":{\"text\":\"6. ElegÃ­ el mÃ©todo de pago\",\"level\":4},\"tunes\":{\"alignmentTune\":{\"alignment\":\"left\"},\"configTune\":{\"expandir\":false}}},{\"id\":\"fqo0Nuszc0\",\"type\":\"paragraph\",\"data\":{\"text\":\"Ofrecemos todos los medios de pago: depÃ³sito, transferencia, o Modapago. Modapago incluye tarjetas de crÃ©dito o dÃ©bito, y efectivo a travÃ©s de Pago FÃ¡cil, Rapipago, etc. AprovechÃ¡ la promo de loss jueves y obtenÃ© hasta 0 de reintegro abonando por este medio.\",\"alignment\":\"left\"},\"tunes\":{\"categoriaTune\":{\"ocultarApp\":false,\"ocultarWeb\":false},\"configTune\":{\"expandir\":false}}},{\"id\":\"smHwmsu4wF\",\"type\":\"Botones\",\"data\":{\"botones\":[{\"id\":\"c8d550a0-677d-11ee-ab4f-8b7ecee75b42\",\"texto\":{},\"textoHtml\":\"Ver info Modapago\",\"textoT\":\"Ver info Modapago\",\"color\":\"#000000\",\"redirect\":{\"route\":\"page\",\"params\":{\"id\":\"201\",\"title\":\"Modapago | MÃ©todos de pago | Modatex\",\"slug\":\"modapago\"}},\"class\":[\"m-2\"],\"offset\":0,\"tamano\":\"\",\"type\":\"enlace\"},{\"id\":\"f0bee7c0-677d-11ee-ab4f-8b7ecee75b42\",\"texto\":{},\"textoHtml\":\"Ver Promo Jueves Modapago\",\"textoT\":\"Ver Promo Jueves Modapago\",\"color\":\"#000000\",\"redirect\":{\"route\":\"ancla\",\"params\":\"/juevesmodapago\"},\"class\":[\"m-2\"],\"offset\":0,\"tamano\":\"\",\"type\":\"enlace\"}],\"isList\":false},\"tunes\":{\"alignmentTune\":{\"alignment\":\"left\"},\"categoriaTune\":{\"ocultarApp\":false,\"ocultarWeb\":false},\"configTune\":{\"expandir\":false}}},{\"id\":\"QjYRfL52Tm\",\"type\":\"header\",\"data\":{\"text\":\"7. RecibÃ­ la confirmaciÃ³n, abonÃ¡ y listo!\",\"level\":4},\"tunes\":{\"alignmentTune\":{\"alignment\":\"left\"},\"configTune\":{\"expandir\":false}}},{\"id\":\"u76EUgKfmZ\",\"type\":\"paragraph\",\"data\":{\"text\":\"Una vez que mandaste el pedido a la marca, Ã©sta chequea que tenga stock de todo. Luego, te va a llegar el cupÃ³n de pago o los datos bancarios por el email o celular registrado en tu perfil.\",\"alignment\":\"left\"},\"tunes\":{\"categoriaTune\":{\"ocultarApp\":false,\"ocultarWeb\":false},\"configTune\":{\"expandir\":false}}},{\"id\":\"BpwqHDJXsU\",\"type\":\"paragraph\",\"data\":{\"text\":\"Finalmente, cuando se confirma el pago, la marca lleva tu paquete a la oficina de envÃ­os y vas a poder ver el nÃºmero de guÃ­a en tu perfil. Â¡Y listo! Solo hay que esperar a que llegue en los plazos del envÃ­o elegido.\",\"alignment\":\"left\"},\"tunes\":{\"categoriaTune\":{\"ocultarApp\":false,\"ocultarWeb\":false},\"configTune\":{\"expandir\":false}}}],\"version\":\"2.28.2\"}","abierto":false},{"id":"5404ba80-6463-11ee-b5d6-5be9872b7c85","title":"Â¿CÃ³mo aplico cupones de descuentos?","titleHtml":"Â¿CÃ³mo aplico cupones de descuentos?","bodyEditorJSON":"{\"time\":1698270929051,\"blocks\":[{\"id\":\"JwHc-Ep44k\",\"type\":\"paragraph\",\"data\":{\"text\":\"Una vez que armes tu carrito, hacÃ© click en . Si tenÃ©s cupones vÃ¡lidos en esa tienda, van a aparecer antes de seleccionar el mÃ©todo de envÃ­o. Para aplicarlos, seleccionÃ¡ el que quiere usar, y listo!\",\"alignment\":\"left\"},\"tunes\":{\"categoriaTune\":{\"ocultarApp\":false,\"ocultarWeb\":false},\"configTune\":{\"expandir\":false}}},{\"id\":\"Ht0hr0klVA\",\"type\":\"Botones\",\"data\":{\"botones\":[{\"id\":\"1788e9e0-6464-11ee-b5d6-5be9872b7c85\",\"texto\":{},\"textoHtml\":\"Conseguir cupones\",\"textoT\":\"Conseguir cupones\",\"color\":\"#000000\",\"redirect\":{\"params\":\"\"},\"class\":[\"m-2\"],\"offset\":0,\"tamano\":\"\",\"type\":\"enlace\"}],\"isList\":false},\"tunes\":{\"alignmentTune\":{\"alignment\":\"left\"},\"categoriaTune\":{\"ocultarApp\":false,\"ocultarWeb\":false},\"configTune\":{\"expandir\":false}}}],\"version\":\"2.28.2\"}","abierto":false}]}},"tunes":{"categoriaTune":{"ocultarApp":false,"ocultarWeb":false},"configTune":{"expandir":false}}},{"id":"_3GdaE8UzX","type":"Imagenes","data":{"id":"4b8e9e30-7380-11ee-a7ea-cf6fb3ae35e5","productos":[{"image":"https://netivooregon.s3.amazonaws.com/modatexrosa2/img/logo/consultas-imagen1.png?n1","caption":{},"captionHtmlw":"TÃ­tulo","ocultarTitulo":true,"redirect":{"params":""},"clases":{"mobile":["12"],"desktop":["4"]},"offset":4,"captionHtml":"TÃ­tulo"}],"config":{"expandir":false,"isSlider":false}},"tunes":{"categoriaTune":{"ocultarApp":false,"ocultarWeb":false},"configTune":{"expandir":false}}},{"id":"FyU8uesctB","type":"paragraph","data":{"text":"Â¿Te quedaste con preguntas?","alignment":"center"},"tunes":{"categoriaTune":{"ocultarApp":false,"ocultarWeb":false},"configTune":{"expandir":false}}},{"id":"rCS2GY1J77","type":"paragraph","data":{"text":"<font size=\"2\">Estamos para ayudarte</font>","alignment":"center"},"tunes":{"categoriaTune":{"ocultarApp":false,"ocultarWeb":false},"configTune":{"expandir":false}}},{"id":"hkz-8p2wQ_","type":"Botones","data":{"botones":[{"id":"2d0e2060-7381-11ee-a7ea-cf6fb3ae35e5","texto":{},"textoHtml":"Consultas","textoT":"Consultas","color":"#000000","redirect":{"route":"page","params":{"id":"457","title":"Nueva temporada","slug":"nueva-temporada"}},"class":["m-2"],"offset":0,"tamano":"","type":"btn"}],"isList":false},"tunes":{"alignmentTune":{"alignment":"center"},"categoriaTune":{"ocultarApp":false,"ocultarWeb":false},"configTune":{"expandir":false}}},{"id":"sz2-h05qHQ","type":"paragraph","data":{"text":"&nbsp; &nbsp;&nbsp;","alignment":"left"},"tunes":{"categoriaTune":{"ocultarApp":false,"ocultarWeb":false},"configTune":{"expandir":false}}}],"version":"2.28.2"}'
        // "redirect"=> [
        //   "route"=> "/how_to_buy",
        //   "params"=> []
        // ]
      ],
      [
        "icon" => '~/assets/icons/icon_menu_2.png',
        "name" => 'Envíos a todo el país',
        "disabled" => false,
        'editor' => '{"time":1697566601023,"blocks":[{"id":"ew0kPG0D00","type":"Imagenes","data":{"id":"df7f2b00-6d18-11ee-a836-296ac69cff49","productos":[{"image":"https://netivooregon.s3.amazonaws.com/modatexrosa2/img/logo/envios-cms.gif?n11","caption":{},"captionHtmlw":"TÃ­tulo","ocultarTitulo":true,"redirect":{"params":""},"clases":{"mobile":["12"],"desktop":["6"]},"offset":3,"captionHtml":"TÃ­tulo"}],"config":{"expandir":false,"isSlider":false}},"tunes":{"categoriaTune":{"ocultarApp":false,"ocultarWeb":false},"configTune":{"backgroundColor":"#639e87","expandir":true}}},{"id":"wDCJP045Hv","type":"header","data":{"text":"TARIFAS DESDE OCTUBRE 2023","level":3},"tunes":{"alignmentTune":{"alignment":"center"},"configTune":{"expandir":false,"margin":{"top":{"node":{},"value":"21.3334","placeholder":"Arriba","clave":"top"},"right":{"node":{},"value":0,"placeholder":"Derecha","clave":"right"},"bottom":{"node":{},"value":"21.3334","placeholder":"Abajo","clave":"bottom"},"left":{"node":{},"value":0,"placeholder":"Izquierda","clave":"left"}}}}},{"id":"QK1aRMP8Jq","type":"Imagenes","data":{"id":"df7fa030-6d18-11ee-a836-296ac69cff49","productos":[{"image":"https://netivooregon.s3.amazonaws.com/modatexrosa2/img/logo/oca-banner-cms.gif","caption":{},"captionHtmlw":"TÃ­tulo","ocultarTitulo":true,"redirect":{"params":""},"clases":{"mobile":["12"],"desktop":["6"]},"offset":3,"captionHtml":"TÃ­tulo"}],"config":{"expandir":false,"isSlider":false}},"tunes":{"categoriaTune":{"ocultarApp":false,"ocultarWeb":false},"configTune":{"backgroundColor":"#472f75","expandir":true}}},{"id":"OzsFIeY5Yz","type":"Imagenes","data":{"id":"df7fc740-6d18-11ee-a836-296ac69cff49","productos":[{"image":"https://netivooregon.s3.amazonaws.com/modatexrosa2/img/logo/precios-envios-octubre2.gif","caption":{},"captionHtmlw":"TÃ­tulo","ocultarTitulo":true,"redirect":{"params":""},"clases":{"mobile":["12"],"desktop":["8"]},"offset":2,"captionHtml":"TÃ­tulo"}],"config":{"expandir":false,"isSlider":false}},"tunes":{"categoriaTune":{"ocultarApp":false,"ocultarWeb":false},"configTune":{"backgroundColor":"#f4f4f4","expandir":true}}},{"id":"ZuaExsedjN","type":"Botones","data":{"botones":[{"id":"5ff6a770-6cfd-11ee-9e3b-eb8435e60a5b","texto":{},"textoHtml":"<span style=\"color: rgb(255, 255, 255);\">MirÃ¡ por dÃ³nde estÃ¡ tu paquete</span>","textoT":"MirÃ¡ por dÃ³nde estÃ¡ tu paquete","color":"#000000","redirect":{"route":"ancla","params":"/?page=58"},"class":["m-2"],"offset":0,"tamano":"","type":"btn"}],"isList":false},"tunes":{"alignmentTune":{"alignment":"center"},"categoriaTune":{"ocultarApp":false,"ocultarWeb":false},"configTune":{"expandir":false,"margin":{"top":{"node":{},"value":"42.6668","placeholder":"Arriba","clave":"top"},"right":{"node":{},"value":0,"placeholder":"Derecha","clave":"right"},"bottom":{"node":{},"value":"42.6668","placeholder":"Abajo","clave":"bottom"},"left":{"node":{},"value":0,"placeholder":"Izquierda","clave":"left"}}}}},{"id":"b83aiv-h-Q","type":"Imagenes","data":{"id":"df801560-6d18-11ee-a836-296ac69cff49","productos":[{"image":"https://netivooregon.s3.amazonaws.com/modatexrosa2/img/logo/moto-correo-logo.gif","caption":{},"captionHtmlw":"TÃ­tulo","ocultarTitulo":true,"redirect":{"route":"ancla","params":"/?page=269"},"clases":{"mobile":["4"],"desktop":["2"]},"offset":5,"captionHtml":"TÃ­tulo"}],"config":{"expandir":false,"isSlider":false}},"tunes":{"categoriaTune":{"ocultarApp":false,"ocultarWeb":false},"configTune":{"expandir":false,"margin":{"top":{"value":0,"placeholder":"Arriba","clave":"top"},"right":{"value":0,"placeholder":"Derecha","clave":"right"},"bottom":{"value":0,"placeholder":"Abajo","clave":"bottom"},"left":{"value":0,"placeholder":"Izquierda","clave":"left"}}}}},{"id":"0gUzeN4dN8","type":"Botones","data":{"botones":[{"id":"2f61b800-6d18-11ee-ba42-899e7618f622","texto":{},"textoHtml":"Ver servicio de moto CABA y GBA","textoT":"Ver servicio de moto CABA y GBA","color":"#000000","redirect":{"route":"ancla","params":"/?page=269"},"class":["m-2"],"offset":0,"tamano":"","type":"enlace"}],"isList":false},"tunes":{"alignmentTune":{"alignment":"center"},"categoriaTune":{"ocultarApp":false,"ocultarWeb":false},"configTune":{"expandir":false,"margin":{"top":{"value":0,"placeholder":"Arriba","clave":"top","node":{}},"right":{"value":0,"placeholder":"Derecha","clave":"right","node":{}},"bottom":{"value":"64.0002","placeholder":"Abajo","clave":"bottom","node":{}},"left":{"value":0,"placeholder":"Izquierda","clave":"left","node":{}}}}}},{"id":"Cp29b5DlmZ","type":"header","data":{"text":"Plazos","level":3},"tunes":{"alignmentTune":{"alignment":"center"},"configTune":{"expandir":false,"margin":{"top":{"node":{},"value":"0","placeholder":"Arriba","clave":"top"},"right":{"node":{},"value":0,"placeholder":"Derecha","clave":"right"},"bottom":{"node":{},"value":"0","placeholder":"Abajo","clave":"bottom"},"left":{"node":{},"value":0,"placeholder":"Izquierda","clave":"left"}}}}},{"id":"Gm1Z0CXlLq","type":"paragraph","data":{"text":"Los plazos de entrega comienzan al dÃ­a siguiente que la marca entrega el paquete en nuestro depÃ³sito (no desde el dÃ­a de compra)","alignment":"center"},"tunes":{"categoriaTune":{"ocultarApp":false,"ocultarWeb":false},"configTune":{"expandir":false,"margin":{"top":{"value":0,"placeholder":"Arriba","clave":"top"},"right":{"value":0,"placeholder":"Derecha","clave":"right"},"bottom":{"value":0,"placeholder":"Abajo","clave":"bottom"},"left":{"value":0,"placeholder":"Izquierda","clave":"left"}}}}},{"id":"6zpPk0-BeP","type":"paragraph","data":{"text":"Si tu localidad es muy alejada puede ser que tarde un poco mÃ¡s de lo establecido","alignment":"center"},"tunes":{"categoriaTune":{"ocultarApp":false,"ocultarWeb":false},"configTune":{"expandir":false,"margin":{"top":{"value":0,"placeholder":"Arriba","clave":"top","node":{}},"right":{"value":0,"placeholder":"Derecha","clave":"right","node":{}},"bottom":{"value":"0","placeholder":"Abajo","clave":"bottom","node":{}},"left":{"value":0,"placeholder":"Izquierda","clave":"left","node":{}}}}}},{"id":"9dpZXth8JZ","type":"header","data":{"text":"Seguro","level":3},"tunes":{"alignmentTune":{"alignment":"center"},"configTune":{"expandir":false,"margin":{"top":{"value":"42.6668","placeholder":"Arriba","clave":"top","node":{}},"right":{"value":0,"placeholder":"Derecha","clave":"right","node":{}},"bottom":{"value":0,"placeholder":"Abajo","clave":"bottom","node":{}},"left":{"value":0,"placeholder":"Izquierda","clave":"left","node":{}}}}}},{"id":"FS5s0sUYb9","type":"paragraph","data":{"text":"<editorjs-style style=\"font-weight:900\">Modatex Garantiza el envÃ­o por OCA, CORREO ARGENTINO, INTEGRAL PACK, MOTO, y Transporte Tradicional,</editorjs-style> ya que en cao de extravÃ­o o pÃ©rdida, posee un seguro. Si hay algÃºn problema con la entrega de tu mercaderÃ­a Modatex te la reenvÃ­a TOTALMENTE GRATIS","alignment":"center"},"tunes":{"categoriaTune":{"ocultarApp":false,"ocultarWeb":false},"configTune":{"expandir":false,"margin":{"top":{"value":0,"placeholder":"Arriba","clave":"top"},"right":{"value":0,"placeholder":"Derecha","clave":"right"},"bottom":{"value":0,"placeholder":"Abajo","clave":"bottom"},"left":{"value":0,"placeholder":"Izquierda","clave":"left"}}}}},{"id":"-BjGulPo9U","type":"paragraph","data":{"text":"Si en el segundo envÃ­o no llega a ser satisfactoria a entrega si se pagarÃ¡ el nuevo (3er) reenvÃ­o.","alignment":"center"},"tunes":{"categoriaTune":{"ocultarApp":false,"ocultarWeb":false},"configTune":{"expandir":false,"margin":{"top":{"value":0,"placeholder":"Arriba","clave":"top"},"right":{"value":0,"placeholder":"Derecha","clave":"right"},"bottom":{"value":0,"placeholder":"Abajo","clave":"bottom"},"left":{"value":0,"placeholder":"Izquierda","clave":"left"}}}}},{"id":"c8QDBDb2oA","type":"Botones","data":{"botones":[{"id":"da89ebc0-6d05-11ee-b674-cd2e7adfa9f2","texto":{},"textoHtml":"Condiciones del seguro","textoT":"Condiciones del seguro","color":"#000000","redirect":{"route":"ancla","params":"/?page=268"},"class":["m-2"],"offset":0,"tamano":"","type":"enlace"}],"isList":false},"tunes":{"alignmentTune":{"alignment":"center"},"categoriaTune":{"ocultarApp":false,"ocultarWeb":false},"configTune":{"expandir":false,"margin":{"top":{"value":0,"placeholder":"Arriba","clave":"top"},"right":{"value":0,"placeholder":"Derecha","clave":"right"},"bottom":{"value":0,"placeholder":"Abajo","clave":"bottom"},"left":{"value":0,"placeholder":"Izquierda","clave":"left"}}}}},{"id":"S9qTzDUAAG","type":"header","data":{"text":"MÃ¡s promo de envÃ­os","level":3},"tunes":{"alignmentTune":{"alignment":"center"},"configTune":{"expandir":false,"margin":{"top":{"node":{},"value":"42.6668","placeholder":"Arriba","clave":"top"},"right":{"node":{},"value":0,"placeholder":"Derecha","clave":"right"},"bottom":{"node":{},"value":"42.6668","placeholder":"Abajo","clave":"bottom"},"left":{"node":{},"value":0,"placeholder":"Izquierda","clave":"left"}}}}},{"id":"751RVWtNmv","type":"Imagenes","data":{"id":"df80ffc0-6d18-11ee-a836-296ac69cff49","productos":[{"image":"https://netivooregon.s3.amazonaws.com/modatexrosa2/img/logo/pinkdays-promos.gif?n8","caption":{},"captionHtmlw":"TÃ­tulo","ocultarTitulo":true,"redirect":{"route":"page","params":{"id":"352","title":"Pink Days | EnvÃ­os Baratos | Modatex","slug":"pinkdays"}},"clases":{"mobile":["12"],"desktop":["6"]},"offset":3,"captionHtml":"TÃ­tulo"},{"image":"https://netivooregon.s3.amazonaws.com/modatexrosa2/img/logo/enviogratis-promos.gif?n2","caption":{},"captionHtmlw":"TÃ­tulo","ocultarTitulo":true,"redirect":{"route":"ancla","params":"/enviogratis"},"clases":{"mobile":["12"],"desktop":["6"]},"offset":3,"captionHtml":"TÃ­tulo"}],"config":{"expandir":false,"isSlider":false}},"tunes":{"categoriaTune":{"ocultarApp":false,"ocultarWeb":false},"configTune":{"expandir":false,"margin":{"top":{"value":0,"placeholder":"Arriba","clave":"top"},"right":{"value":0,"placeholder":"Derecha","clave":"right"},"bottom":{"value":0,"placeholder":"Abajo","clave":"bottom"},"left":{"value":0,"placeholder":"Izquierda","clave":"left"}}}}}],"version":"2.29.0-rc.1"}',
        // "redirect"=> [
        //   "route"=> "/shipping",
        //   "params"=> []
        // ]
      ],
      [
        "icon" => '~/assets/icons/icon_menu_3.png',
        "name" => 'Formas de pago',
        "disabled" => false,
        'editor' => '{"time":1698111411222,"blocks":[{"id":"oihWZL_xtB","type":"paragraph","data":{"text":"PagÃ¡ tu pedido en Modatex con todo los medio de pago: DepÃ³sito/Transferencia bancaria, o con <span style=\"color: rgb(103, 58, 183);\">Modapago</span>","alignment":"center"},"tunes":{"categoriaTune":{"ocultarApp":false,"ocultarWeb":false},"configTune":{"expandir":false,"margin":{"top":{"node":{},"value":"42.6668","placeholder":"Arriba","clave":"top"},"right":{"node":{},"value":0,"placeholder":"Derecha","clave":"right"},"bottom":{"node":{},"value":0,"placeholder":"Abajo","clave":"bottom"},"left":{"node":{},"value":0,"placeholder":"Izquierda","clave":"left"}}}}},{"id":"7cQL9k8aGN","type":"Imagenes","data":{"id":"851dc9c0-720c-11ee-81b7-8187a2144d6d","productos":[{"image":"https://netivooregon.s3.amazonaws.com/modatexrosa2/img/logo/modapago-logo-cms.png?n1","caption":{},"captionHtmlw":"TÃ­tulo","ocultarTitulo":true,"redirect":{"params":""},"clases":{"mobile":["4"],"desktop":["2"]},"offset":5,"captionHtml":"TÃ­tulo"}],"config":{"expandir":false,"isSlider":false}},"tunes":{"categoriaTune":{"ocultarApp":false,"ocultarWeb":false},"configTune":{"expandir":false,"margin":{"top":{"value":0,"placeholder":"Arriba","clave":"top","node":{}},"right":{"value":0,"placeholder":"Derecha","clave":"right","node":{}},"bottom":{"value":"42.6668","placeholder":"Abajo","clave":"bottom","node":{}},"left":{"value":0,"placeholder":"Izquierda","clave":"left","node":{}}}}}},{"id":"WHQJ0fTfQQ","type":"Imagenes","data":{"id":"851e17e0-720c-11ee-81b7-8187a2144d6d","productos":[{"image":"https://netivooregon.s3.amazonaws.com/modatexrosa2/img/logo/modapago-pagos.png?n4","caption":{},"captionHtmlw":"TÃ­tulo","ocultarTitulo":true,"redirect":{"params":""},"clases":{"mobile":["12"],"desktop":["8"]},"offset":2,"captionHtml":"TÃ­tulo"}],"config":{"expandir":false,"isSlider":false}},"tunes":{"categoriaTune":{"ocultarApp":false,"ocultarWeb":false},"configTune":{"expandir":true,"margin":{"top":{"value":0,"placeholder":"Arriba","clave":"top"},"right":{"value":0,"placeholder":"Derecha","clave":"right"},"bottom":{"value":0,"placeholder":"Abajo","clave":"bottom"},"left":{"value":0,"placeholder":"Izquierda","clave":"left"}},"backgroundColor":"#f4f4f4"}}},{"id":"qkjGymAAOg","type":"paragraph","data":{"text":"Con la garantÃ­a de seguridad","alignment":"center"},"tunes":{"categoriaTune":{"ocultarApp":false,"ocultarWeb":false},"configTune":{"expandir":false,"margin":{"top":{"node":{},"value":"42.6668","placeholder":"Arriba","clave":"top"},"right":{"node":{},"value":0,"placeholder":"Derecha","clave":"right"},"bottom":{"node":{},"value":"0","placeholder":"Abajo","clave":"bottom"},"left":{"node":{},"value":0,"placeholder":"Izquierda","clave":"left"}}}}},{"id":"X4mhheJiMo","type":"Imagenes","data":{"id":"851e6600-720c-11ee-81b7-8187a2144d6d","productos":[{"image":"https://www.modatex.com.ar/common/img/icon/comodo_secure_seal_113x59_transp.png","caption":{},"captionHtmlw":"TÃ­tulo","ocultarTitulo":true,"redirect":{"params":""},"clases":{"mobile":["4"],"desktop":["2"]},"offset":5,"captionHtml":"TÃ­tulo"}],"config":{"expandir":false,"isSlider":false}},"tunes":{"categoriaTune":{"ocultarApp":false,"ocultarWeb":false},"configTune":{"expandir":false,"margin":{"top":{"node":{},"value":0,"placeholder":"Arriba","clave":"top"},"right":{"node":{},"value":0,"placeholder":"Derecha","clave":"right"},"bottom":{"node":{},"value":"42.6668","placeholder":"Abajo","clave":"bottom"},"left":{"node":{},"value":0,"placeholder":"Izquierda","clave":"left"}}}}},{"id":"yr9MAoatrB","type":"header","data":{"text":"Â¿CÃ³mo uso Modapago?","level":3},"tunes":{"alignmentTune":{"alignment":"center"},"configTune":{"expandir":false,"margin":{"top":{"node":{},"value":"0","placeholder":"Arriba","clave":"top"},"right":{"node":{},"value":0,"placeholder":"Derecha","clave":"right"},"bottom":{"node":{},"value":"21.3334","placeholder":"Abajo","clave":"bottom"},"left":{"node":{},"value":0,"placeholder":"Izquierda","clave":"left"}}}}},{"id":"vyLRJOeoFu","type":"Imagenes","data":{"id":"851e8d10-720c-11ee-81b7-8187a2144d6d","productos":[{"image":"https://netivooregon.s3.amazonaws.com/modatexrosa2/img/logo/modapago-pagos2.png?n1","caption":{},"captionHtmlw":"TÃ­tulo","ocultarTitulo":true,"redirect":{"params":""},"clases":{"mobile":["12"],"desktop":["6"]},"offset":3,"captionHtml":"TÃ­tulo"}],"config":{"expandir":false,"isSlider":false}},"tunes":{"categoriaTune":{"ocultarApp":false,"ocultarWeb":false},"configTune":{"backgroundColor":"#f4f4f4","expandir":true,"margin":{"top":{"node":{},"value":0,"placeholder":"Arriba","clave":"top"},"right":{"node":{},"value":0,"placeholder":"Derecha","clave":"right"},"bottom":{"node":{},"value":"42.6668","placeholder":"Abajo","clave":"bottom"},"left":{"node":{},"value":0,"placeholder":"Izquierda","clave":"left"}}}}},{"id":"hmeFJRNGc_","type":"paragraph","data":{"text":"DespuÃ©s de hacer tu pedido, la tienda te va a enviar un mail con botÃ³n de pago.","alignment":"center"},"tunes":{"categoriaTune":{"ocultarApp":false,"ocultarWeb":false},"configTune":{"expandir":false,"margin":{"top":{"value":0,"placeholder":"Arriba","clave":"top"},"right":{"value":0,"placeholder":"Derecha","clave":"right"},"bottom":{"value":0,"placeholder":"Abajo","clave":"bottom"},"left":{"value":0,"placeholder":"Izquierda","clave":"left"}}}}},{"id":"Cn3XK7D9WU","type":"paragraph","data":{"text":"Para abonar con crÃ©dito o dÃ©bito, ingresÃ¡ los datos de la tarjeta en la web.","alignment":"center"},"tunes":{"categoriaTune":{"ocultarApp":false,"ocultarWeb":false},"configTune":{"expandir":false,"margin":{"top":{"value":0,"placeholder":"Arriba","clave":"top"},"right":{"value":0,"placeholder":"Derecha","clave":"right"},"bottom":{"value":0,"placeholder":"Abajo","clave":"bottom"},"left":{"value":0,"placeholder":"Izquierda","clave":"left"}}}}},{"id":"WfOKRqw6nr","type":"paragraph","data":{"text":"Para abonar en efectivo a travÃ©s de PagofacÃ­l o Rapipago, imprimÃ­ el cupÃ³n y acercate a la sucursal mÃ¡s cercana.","alignment":"center"},"tunes":{"categoriaTune":{"ocultarApp":false,"ocultarWeb":false},"configTune":{"expandir":false,"margin":{"top":{"value":0,"placeholder":"Arriba","clave":"top"},"right":{"value":0,"placeholder":"Derecha","clave":"right"},"bottom":{"value":0,"placeholder":"Abajo","clave":"bottom"},"left":{"value":0,"placeholder":"Izquierda","clave":"left"}}}}},{"id":"e1dEWfPsMu","type":"paragraph","data":{"text":"Una vez que el pago se acredite, la marca va a pasar a realizar el envÃ­o","alignment":"center"},"tunes":{"categoriaTune":{"ocultarApp":false,"ocultarWeb":false},"configTune":{"expandir":false,"margin":{"top":{"node":{},"value":0,"placeholder":"Arriba","clave":"top"},"right":{"node":{},"value":0,"placeholder":"Derecha","clave":"right"},"bottom":{"node":{},"value":"21.3334","placeholder":"Abajo","clave":"bottom"},"left":{"node":{},"value":0,"placeholder":"Izquierda","clave":"left"}}}}},{"id":"5szSRIjuOY","type":"paragraph","data":{"text":"La acreditaciÃ³n del pago suele ser instantÃ¡nea o puede tardar unos dÃ­as. Vas a poder ver el estado del pago en tu perfil.","alignment":"center"},"tunes":{"categoriaTune":{"ocultarApp":false,"ocultarWeb":false},"configTune":{"expandir":false,"margin":{"top":{"value":0,"placeholder":"Arriba","clave":"top"},"right":{"value":0,"placeholder":"Derecha","clave":"right"},"bottom":{"value":0,"placeholder":"Abajo","clave":"bottom"},"left":{"value":0,"placeholder":"Izquierda","clave":"left"}}}}},{"id":"eDCBBgzBzP","type":"Botones","data":{"botones":[{"id":"f03dd830-6d20-11ee-b415-7bef76b3d6b6","texto":{},"textoHtml":"Ver perfil","textoT":"Ver perfil","color":"#000000","redirect":{"route":"ancla","params":"/perfil"},"class":["m-2"],"offset":0,"tamano":"","type":"btn"}],"isList":false},"tunes":{"alignmentTune":{"alignment":"center"},"categoriaTune":{"ocultarApp":false,"ocultarWeb":false},"configTune":{"expandir":false,"margin":{"top":{"value":0,"placeholder":"Arriba","clave":"top"},"right":{"value":0,"placeholder":"Derecha","clave":"right"},"bottom":{"value":0,"placeholder":"Abajo","clave":"bottom"},"left":{"value":0,"placeholder":"Izquierda","clave":"left"}}}}},{"id":"sI5cPdZlIe","type":"paragraph","data":{"text":"<span style=\"font-size: x-small;\">Si tenÃ©s mÃ¡s consultas sobre Modapago, podÃ©s mandarnos un mail a <span style=\"color: rgb(236, 29, 132);\">info@modapago.com</span></span>","alignment":"center"},"tunes":{"categoriaTune":{"ocultarApp":false,"ocultarWeb":false},"configTune":{"expandir":false,"margin":{"top":{"node":{},"value":0,"placeholder":"Arriba","clave":"top"},"right":{"node":{},"value":0,"placeholder":"Derecha","clave":"right"},"bottom":{"node":{},"value":"64.0002","placeholder":"Abajo","clave":"bottom"},"left":{"node":{},"value":0,"placeholder":"Izquierda","clave":"left"}}}}},{"id":"3lO_YTsdld","type":"Imagenes","data":{"id":"851f9e80-720c-11ee-81b7-8187a2144d6d","productos":[{"image":"https://netivooregon.s3.amazonaws.com/modatexrosa2/img/logo/juevesmodapago-cms2.gif?n1","caption":{},"captionHtmlw":"TÃ­tulo","ocultarTitulo":true,"redirect":{"params":""},"clases":{"mobile":["12"],"desktop":["6"]},"offset":3,"captionHtml":"TÃ­tulo"}],"config":{"expandir":false,"isSlider":false}},"tunes":{"categoriaTune":{"ocultarApp":false,"ocultarWeb":false},"configTune":{"expandir":false,"margin":{"top":{"value":0,"placeholder":"Arriba","clave":"top"},"right":{"value":0,"placeholder":"Derecha","clave":"right"},"bottom":{"value":0,"placeholder":"Abajo","clave":"bottom"},"left":{"value":0,"placeholder":"Izquierda","clave":"left"}}}}},{"id":"iRqQvaURkN","type":"paragraph","data":{"text":"Â¡AprovechÃ¡ la <editorjs-style style=\"font-weight: bold\">promo de lo jueves</editorjs-style> y obtenÃ© hasta <editorjs-style style=\"font-weight: 900\">1000 de reintegro</editorjs-style>!<br>","alignment":"center"},"tunes":{"categoriaTune":{"ocultarApp":false,"ocultarWeb":false},"configTune":{"expandir":false,"margin":{"top":{"value":0,"placeholder":"Arriba","clave":"top"},"right":{"value":0,"placeholder":"Derecha","clave":"right"},"bottom":{"value":0,"placeholder":"Abajo","clave":"bottom"},"left":{"value":0,"placeholder":"Izquierda","clave":"left"}}}}},{"id":"0jjYCW9blx","type":"paragraph","data":{"text":"Â¡Comprando todos los <editorjs-style style=\"font-weight:900\">jueves</editorjs-style> y abonando con <editorjs-style style=\"font-weight:900\">Modapago</editorjs-style>, Modatex <editorjs-style style=\"font-weight:900\">te devuelve el 5%</editorjs-style> en forma de un cupÃ³n para tu prÃ³xima compra!","alignment":"center"},"tunes":{"categoriaTune":{"ocultarApp":false,"ocultarWeb":false},"configTune":{"expandir":false,"margin":{"top":{"node":{},"value":0,"placeholder":"Arriba","clave":"top"},"right":{"node":{},"value":0,"placeholder":"Derecha","clave":"right"},"bottom":{"node":{},"value":"42.6668","placeholder":"Abajo","clave":"bottom"},"left":{"node":{},"value":0,"placeholder":"Izquierda","clave":"left"}}}}},{"id":"LsqpjioeE2","type":"paragraph","data":{"text":"<span style=\"color: rgb(103, 58, 183);\"><editorjs-style style=\"font-weight:900\">Â¿CÃ³mo funciona?</editorjs-style></span>","alignment":"center"},"tunes":{"categoriaTune":{"ocultarApp":false,"ocultarWeb":false},"configTune":{"expandir":false,"margin":{"top":{"value":0,"placeholder":"Arriba","clave":"top"},"right":{"value":0,"placeholder":"Derecha","clave":"right"},"bottom":{"value":0,"placeholder":"Abajo","clave":"bottom"},"left":{"value":0,"placeholder":"Izquierda","clave":"left"}}}}},{"id":"uZHKhlhg9K","type":"paragraph","data":{"text":"<editorjs-style style=\"font-weight:900\">Pagando por Modapago </editorjs-style>con tarjeta de crÃ©dito o dÃ©bito, Rapipago o PagofÃ¡cil; se te va a generar automÃ¡ticamente un cupÃ³n en tu perfil. (2)(3)","alignment":"center"},"tunes":{"categoriaTune":{"ocultarApp":false,"ocultarWeb":false},"configTune":{"expandir":false,"margin":{"top":{"value":0,"placeholder":"Arriba","clave":"top","node":{}},"right":{"value":0,"placeholder":"Derecha","clave":"right","node":{}},"bottom":{"value":"42.6668","placeholder":"Abajo","clave":"bottom","node":{}},"left":{"value":0,"placeholder":"Izquierda","clave":"left","node":{}}}}}},{"id":"hOIIWPzuIz","type":"paragraph","data":{"text":"<span style=\"color: rgb(103, 58, 183);\"><editorjs-style style=\"font-weight:900\">Â¿De cuÃ¡nto es el cupÃ³n?</editorjs-style></span>","alignment":"center"},"tunes":{"categoriaTune":{"ocultarApp":false,"ocultarWeb":false},"configTune":{"expandir":false,"margin":{"top":{"value":0,"placeholder":"Arriba","clave":"top"},"right":{"value":0,"placeholder":"Derecha","clave":"right"},"bottom":{"value":0,"placeholder":"Abajo","clave":"bottom"},"left":{"value":0,"placeholder":"Izquierda","clave":"left"}}}}},{"id":"_7wD7KJl7b","type":"paragraph","data":{"text":"Tu cupÃ³n va a ser igual al 5% de tu compra con un lÃ­mite de devoluciÃ³n de 1000","alignment":"center"},"tunes":{"categoriaTune":{"ocultarApp":false,"ocultarWeb":false},"configTune":{"expandir":false,"margin":{"top":{"value":0,"placeholder":"Arriba","clave":"top"},"right":{"value":0,"placeholder":"Derecha","clave":"right"},"bottom":{"value":0,"placeholder":"Abajo","clave":"bottom"},"left":{"value":0,"placeholder":"Izquierda","clave":"left"}}}}},{"id":"HT8wa68k-J","type":"Imagenes","data":{"id":"851fc590-720c-11ee-81b7-8187a2144d6d","productos":[{"image":"https://netivooregon.s3.amazonaws.com/modatexrosa2/img/logo/juevesmodapago-news2.gif","caption":{},"captionHtmlw":"TÃ­tulo","ocultarTitulo":true,"redirect":{"params":""},"clases":{"mobile":["4"],"desktop":["4"]},"offset":4,"captionHtml":"TÃ­tulo"}],"config":{"expandir":false,"isSlider":false}},"tunes":{"categoriaTune":{"ocultarApp":false,"ocultarWeb":false},"configTune":{"expandir":false,"margin":{"top":{"value":0,"placeholder":"Arriba","clave":"top"},"right":{"value":0,"placeholder":"Derecha","clave":"right"},"bottom":{"value":0,"placeholder":"Abajo","clave":"bottom"},"left":{"value":0,"placeholder":"Izquierda","clave":"left"}}}}},{"id":"pz_vo9MrXa","type":"paragraph","data":{"text":"<span style=\"color: rgb(103, 58, 183);\"><editorjs-style style=\"font-weight: 900\">Â¿Hasta cuÃ¡ndo puedo usar el cupÃ³n?</editorjs-style></span>","alignment":"center"},"tunes":{"categoriaTune":{"ocultarApp":false,"ocultarWeb":false},"configTune":{"expandir":false,"margin":{"top":{"value":0,"placeholder":"Arriba","clave":"top"},"right":{"value":0,"placeholder":"Derecha","clave":"right"},"bottom":{"value":0,"placeholder":"Abajo","clave":"bottom"},"left":{"value":0,"placeholder":"Izquierda","clave":"left"}}}}},{"id":"OFcNZTmMd0","type":"paragraph","data":{"text":"TenÃ©s una semana para usarlo desde el dÃ­a del pedido. Aprovechalo! (4)","alignment":"center"},"tunes":{"categoriaTune":{"ocultarApp":false,"ocultarWeb":false},"configTune":{"expandir":false,"margin":{"top":{"value":0,"placeholder":"Arriba","clave":"top"},"right":{"value":0,"placeholder":"Derecha","clave":"right"},"bottom":{"value":0,"placeholder":"Abajo","clave":"bottom"},"left":{"value":0,"placeholder":"Izquierda","clave":"left"}}}}},{"id":"TXwRvw2c_L","type":"paragraph","data":{"text":"<span style=\"color: rgb(103, 58, 183);\"><editorjs-style style=\"font-weight: 900\">Â¿En quÃ© marcas puedo usar mi cupÃ³n?</editorjs-style></span>","alignment":"center"},"tunes":{"categoriaTune":{"ocultarApp":false,"ocultarWeb":false},"configTune":{"expandir":false,"margin":{"top":{"value":0,"placeholder":"Arriba","clave":"top"},"right":{"value":0,"placeholder":"Derecha","clave":"right"},"bottom":{"value":0,"placeholder":"Abajo","clave":"bottom"},"left":{"value":0,"placeholder":"Izquierda","clave":"left"}}}}},{"id":"_DfGrD96vd","type":"paragraph","data":{"text":"En toda las marcas","alignment":"center"},"tunes":{"categoriaTune":{"ocultarApp":false,"ocultarWeb":false},"configTune":{"expandir":false,"margin":{"top":{"value":0,"placeholder":"Arriba","clave":"top"},"right":{"value":0,"placeholder":"Derecha","clave":"right"},"bottom":{"value":0,"placeholder":"Abajo","clave":"bottom"},"left":{"value":0,"placeholder":"Izquierda","clave":"left"}}}}},{"id":"0BLnF5Xvk0","type":"paragraph","data":{"text":"<span style=\"color: rgb(103, 58, 183);\"><editorjs-style style=\"font-weight: 900\">Â¿DÃ³nde veo mis cupones?</editorjs-style></span>","alignment":"center"},"tunes":{"categoriaTune":{"ocultarApp":false,"ocultarWeb":false},"configTune":{"expandir":false,"margin":{"top":{"value":0,"placeholder":"Arriba","clave":"top"},"right":{"value":0,"placeholder":"Derecha","clave":"right"},"bottom":{"value":0,"placeholder":"Abajo","clave":"bottom"},"left":{"value":0,"placeholder":"Izquierda","clave":"left"}}}}},{"id":"oZzomMslbo","type":"paragraph","data":{"text":"No olvide que para participar debÃ©s <span style=\"color: rgb(0, 112, 255);\">ingresar con tu usuario de Modatex.</span>","alignment":"center"},"tunes":{"categoriaTune":{"ocultarApp":false,"ocultarWeb":false},"configTune":{"expandir":false,"margin":{"top":{"node":{},"value":0,"placeholder":"Arriba","clave":"top"},"right":{"node":{},"value":0,"placeholder":"Derecha","clave":"right"},"bottom":{"node":{},"value":"106.667","placeholder":"Abajo","clave":"bottom"},"left":{"node":{},"value":0,"placeholder":"Izquierda","clave":"left"}}}}},{"id":"k5455ETPv7","type":"list","data":{"style":"ordered","items":["<font size=\"2\">PromociÃ³n vÃ¡lida solo para la RepÃºblica Argentina. Los cupones se generan una vez realizado el pago.</font>","<font size=\"2\">El pago puede ser hecho en cualquier momento antes del jueves siguiente a hacer el pedido.</font>","<font size=\"2\">Son vÃ¡lidos solamente los pagos relacionados a compras dentro de Modatex. No son vÃ¡lidos los pagos independientes. El nÃºmero de cupÃ³n debe coincidir con el nÃºmero de compra.</font>","<font size=\"2\">Los cupones son vÃ¡lidos hasta el miÃ©rcoles siguiente de hacer el pedido, a las 23:59 hs. El total a redimir no incluye el valor del envÃ­o.</font>","<font size=\"2\">Aplican Bases y Condiciones</font><br>"]},"tunes":{"categoriaTune":{"ocultarApp":false,"ocultarWeb":false},"configTune":{"expandir":false,"margin":{"top":{"value":0,"placeholder":"Arriba","clave":"top"},"right":{"value":0,"placeholder":"Derecha","clave":"right"},"bottom":{"value":0,"placeholder":"Abajo","clave":"bottom"},"left":{"value":0,"placeholder":"Izquierda","clave":"left"}}}}}],"version":"2.28.2"}',
        // "redirect"=> [
        //   "route"=> "/payment_methods",
        //   "params"=> []
        // ]
      ],
      
    ];

  }

}
