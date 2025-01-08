<?php

namespace App\Http\Controllers;


use Carbon\Carbon;
use App\Models\Store;
use App\Models\Favorite;
use App\Models\Products;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Http\Traits\StoreTraits;
use Illuminate\Http\Client\Pool;
use App\Http\Traits\HelpersTraits;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Auth;

use Illuminate\Support\Facades\Cache;
use App\Helpers\General\CollectionHelper;

class StoresController extends Controller
{
    use StoreTraits;

    private $categories = ['woman','man','xl','kids','accessories','sportive','lingerie','home','shoes'];

    private $allowedPlanes = ['premium', 'black','platinum','gold','blue'];

    private $url = 'https://www.modatex.com.ar/modatexrosa3/';

    private $urlStore = 'https://www.modatex.com.ar/?c=Store::';


    private $storesPlanes = [
        '_black.json',
        '_platinum.json',
        '_gold.json',
        '_blue.json'
    ];

    private $urlProduct = 'https://www.modatex.com.ar/modatexrosa3/?c=Products::get&';

    private $urlPromos = 'https://www.modatex.com.ar/?c=Canpaigns::promos&store_id=';


    /**
     * REQUEST STORE
     */

    public function getStoresRosa(Request $request)
    {
      $this->validate($request, [
          'categorie' => 'required',
      ]);

      if(isset($request->no_paginate) && $request->no_paginate == true){
        // $response = Http::accept('application/json')->get($this->urlStore.'all');
        return response()->json($this->consultaStoresRosa($request->all()));
      }

      return response()->json(CollectionHelper::paginate(  $this->consultaStoresRosa($request->all()), $request->paginate ?? 16));
    }

    /**
     * CONSULTA API MODATEX.COM.AR
     */
    public function consultaStoresRosa($request)
    {
      $response = Cache::get('stores');

    //   dd($response);
      $ids = collect($response)->pluck('id')->all();

      $favoritos = Favorite::whereIn('LOCAL_CD',$ids)->where('STAT_CD','1000')->where('CLIENT_NUM',Auth::user()->num)->get();

      $data = $this->crearConsulta(collect($response)->map(function($tienda) use ($favoritos) {
        $tienda['favorite'] = $favoritos->where('LOCAL_CD', $tienda['id'])->count() ? true : false;
        return $tienda;
      }),$request);

      $data = $data->sortBy(function ($item) {
          $position = array_search($item['paquete'], $this->allowedPlanes);
          return $position === false ? PHP_INT_MAX : $position;
      })->values();

      return $data;
    }

    /**
     * COLLECT()
     * FILTROS DE BUSQUEDA TIENDA
     */
    private function crearConsulta($data,$request)
    {

      if(isset($request['search'])){
        $f = $data->filter(fn ($store) =>
          Str::is(Str::lower($request['search']).'*',Str::lower($store['cleaned']))
        );

        if(!$f->count()){
          $f = $data->filter(fn ($store) =>
            Str::is(Str::lower($request['search']).'*',Str::lower($store['name']))
          );
        }
        $data = $f;
      }


      if(isset($request['categorie'])){
        if($request['categorie'] == 'all'){
          $data = $data->shuffle();
        }else{
          $data = $data->filter(fn ($store) => Str::is(Str::lower($request['categorie']).'*',Str::lower($store['categorie'])) );
        }
      }

      if(isset($request['plan']) && $request['plan']!=''){
        $data = $data->filter(fn ($store) => Str::lower($request['plan'])  == Str::lower($store['paquete']) );
      }

      if(isset($request['in'])){

        $data = $data->whereIn('local_cd', $request['in']);
      }

      if(isset($request['local_cd'])){
        $data = $data->where('local_cd',$request['local_cd'] );
      }

      return $data;
    }

    /**
     * CONSULTA PROMOCIONES DE LA TIENDAS (PASTILLAS)
     * MODATEX.COM.CAR
     */

    public function getPromociones($local_cd)
    {
      if($local_cd){

        $response = Http::accept('application/json')->get($this->urlPromos.$local_cd);
        $response = $response->json();

        if(isset($response['data']['custom']) && count($response['data']['custom'])){
          $response['data']['custom'] = collect($response['data']['custom'])->map(function($pastilla){
            $pastilla['title'] = html_entity_decode($pastilla['title']);

            return $pastilla;
          });
        }

        // dd(Auth::user()->num);
        // if(Auth::user()->num == 1115249){

          if(isset($response['data']) && isset($response['data']['general']))
          {
            $response['data']['general'] = collect($response['data']['general'])->map(function($pastilla){
              // Expresión regular para encontrar todas las etiquetas <a href="...">...</a>
              $pattern = '/<a\s+href=["\']([^"\']+)["\'].*?>(.*?)<\/a>/i';

              // Array para almacenar los links encontrados
              $botones = [];

              // Buscar todas las etiquetas <a> y extraer el contenido
              preg_match_all($pattern, $pastilla['text'], $matches);

              // Eliminar las etiquetas <a> del texto
              $textWithoutLinks = preg_replace($pattern, '', $pastilla['text']);

              // Recorrer todas las coincidencias y reemplazar la etiqueta <a> con su contenido formateado
              if (!empty($matches[1])) {
                foreach ($matches[1] as $index => $href) {
                  $botones[] = [
                    'text' => $matches[2][$index],
                    'link' => ltrim($href, '/')
                  ];
                }
              }

              $pastilla['text'] = $textWithoutLinks;

              $pastilla['buttons'] =  collect($botones)->map(function($boton){
                // dd($boton);
                $cms = cmsController::searchCms(['slug'=> $boton['link']]);
                $boton['redirect'] = [
                  'route' => '/page',
                  'params' => [ 'id' => $cms['id'], 'editor' => $cms['editor'], 'name' => $cms['name'] ]
                ];
                return $boton;
              });

              return $pastilla;
            });
          }
        // }

        return response()->json($response);
      }
      return [];
    }

    /**
     * CATEGORIAS Y SUBCATEGORIAS DE LA TIENDA
     */

    private function calculaNameCategoriaStore($categoria)
    {

      switch ($categoria) {
        case 'woman':
          $categoria_name = 'Mujer';
          break;

        case 'man':
          $categoria_name = 'Hombre';
          break;

        case 'accessories':
          $categoria_name = 'Accesorios';
          break;

        case 'xl':
          $categoria_name = 'Talle Especial';
          break;

        case 'kids':
          $categoria_name = 'Niños';
          break;

        case 'ofertas':
          $categoria_name = 'Ofertas';
          break;

        case 'sportive':
          $categoria_name = 'Deportivo';
          break;

        case 'lingerie':
            $categoria_name = 'Lenceria';
            break;

        case 'shoes':
          $categoria_name = 'Calzado';
          break;

        case 'home':
          $categoria_name = 'Hogar';
          break;

        default:
          $categoria_name = '';
          break;
      }

      return $categoria_name;
    }

    public function getCategoriesStore(Request $request)
    {

      $this->validate($request, [
          'local_cd' => 'required',
      ]);

      $response = Http::acceptJson()->get($this->url.'?c=Products::categories&store='.$request->local_cd.'&daysExpir=365');
      $store = Store::LOCAL_CD($request->local_cd)->first();
      $predefSection = $this->categorieDefaultId($store);

      return $response->collect()->map(function($section) use ($predefSection){
        return [
          'categoria' => [
            'id' => $section['section_id'],
            'clave' => $section['section'],
            'name' => $this->calculaNameCategoriaStore($section['section']),
            'is_default' => $predefSection == $section['section_id'] ? true: false,
          ],
          'subcategorias' => collect($section['categories'])->map(function($categorie) use($section) {
            return [
              "id" =>  $categorie['category_code'],
              "description" => $categorie['category_name'],
              "name" => $categorie['category_name'],
              "amount" => $categorie['amount'],
              "category_id" =>  $section['section_id']
            ];
          })
        ];
      });

      // $store = Store::LOCAL_CD($request->local_cd)->first();

      // $categorias = $this->categoriesCollection($store, null);

      // $categories = [];

      // $predefSection = $this->categorieDefaultId($store);


      //

      // dd($response->json());

      // foreach ($categorias as $c => $categoria) {



      //   $d = $this->urlProduct.'store='.$request->local_cd.'&sections='.$categoria['clave'].'&start=0&length=9999';

      //   $response = Http::acceptJson()->get($d);

      //   $idsSubscategories = [];
      //   foreach ($response->collect()->all()['data'] as $key => $value) {
      //     if(!in_array($value['category_id'], $idsSubscategories)){
      //       $idsSubscategories[] = $value['category_id'];
      //     }
      //   }

      //   $subcategories = [];
      //   foreach ($categoria['subcategories'] as $key => $sub) {
      //     if(in_array($sub['id'], $idsSubscategories)){
      //       $subcategories[] = $sub;
      //     }
      //   }

      //   $categories[] = [
      //     'categoria' => [
      //       'id' => $categoria['id'],
      //       'name' => $categoria['name'],
      //       'clave' => $categoria['clave'],
      //       'is_default' => $predefSection == $categoria['id'] ? true: false
      //     ],
      //     'subcategorias' => $subcategories,
      //   ];

      // }

      // return $categories;

    }

    public function productsHome(Request $request)
    {
      $this->validate($request, [
          'categorie' => 'required',
          'plan'    => 'required',
      ]);

      $url = $this->url.'json/cache_'.$request->categorie."_".$request->plan.'.json';

      $response = Http::accept('application/json')->get($url);
      $stores = [];
      foreach($response->collect()->all()['stores'] as $key => $store){

        $stores[] = [
            "logo" => env('URL_IMAGE').'/'. $store['profile']['logo'],
            "local_cd" => $store['local_cd'],
            "fix" => isset($store['fix']) ? true: false,
            "name" => $store['cover']['title'],
            "min" => $store['profile']['min'],
        ];
      }

      $urls = [];
      $products = [];
      // dd($stores);
      foreach ($stores as $key => $value) {
        if(isset($value['fix']) && $value['fix']){
          $response = Http::acceptJson()->get($this->urlProduct.Arr::query($request->all()).'&store='.$value['local_cd']);
          $arreglo = [];
          foreach ($response->collect()->all()['data'] as $d => $data) {
            $data = Arr::add($data, 'store_data', [
              'logo' => $value['logo'],
              'name' => $value['name'],
              'min' => $value['min'],
              "id" => $value['local_cd']
            ]);
            $arreglo[] = $data;
          }

          $products[] = $arreglo;

        }
      }

      foreach ($stores as $key => $value) {
        if($key < 5 && (isset($value['fix']) && !$value['fix'])){
          $response = Http::acceptJson()->get($this->urlProduct.Arr::query($request->all()).'&store='.$value['local_cd']);
          $arreglo = [];
          foreach ($response->collect()->all()['data'] as $d => $data) {
            $data = Arr::add($data, 'store_data', [
              'logo' => $value['logo'],
              'name' => $value['name'],
              'min' => $value['min'],
            ]);
            $arreglo[] = $data;
          }

          $products[] = $arreglo;
        }
      }

      return response()->json(Arr::collapse($products));
    }

    public function searchStoresSegunPlanes($planes)
    {
      $urls = [];
      foreach($planes as $key => $plan){
        foreach($this->categories as $k => $categorie){
          $urls[] = $this->url.'json/cache_'.$categorie."_".$plan.'.json';
        }
      }

      $collection = collect($urls);
      $consultas = Http::pool(fn (Pool $pool) =>
        $collection->map(fn ($url) =>
              $pool->accept('application/json')->get($url)
        )
      );

      $storesAll = [];
      foreach ($consultas as $c => $consulta) {
        $storesAll[] = $consulta->collect()->all()['stores'];
      }


      $storesAll = collect(Arr::collapse($storesAll));

      return $storesAll;
    }

    public function categorieDefaultId($store)
    {
      /**
         * Si PREDEF_SECTION != '' ??  Es es el ID por defecto.
         * Si PREDEF_SECTION == '' ??  Si subcaetgorias > 1 ? La prioridad es woman|talle especial|men|nino|accesorios : Es la posicion 0
         */
        // dd($store);

      $predefSection = 0;

      if( $store && $store['PREDEF_SECTION'] != '')
      {
        $predefSection = intval($store['PREDEF_SECTION']);
      }
      elseif( $store && $store['PREDEF_SECTION'] == '')
      {
        $categorias = $this->categoriesCollection($store, null);

          if($categorias->count() > 1){
            $predefSection = $categorias->where('descripcion' , 'mujer')->first();
            if($predefSection){
              $predefSection = $predefSection['id'];
            }else{
              $predefSection = $categorias->first();
              if($predefSection){
                $predefSection = $predefSection['id'];
              }
            }
          }else{
            $predefSection = $categorias->first();
            if($predefSection){
              $predefSection = $predefSection['id'];
            }
          }
      }
      return $predefSection;
    }

    public function getRatings($store_id)
    {
      $marca = Carbon::now()->timestamp * 1000;

      $response = Http::accept('application/json')->get($this->urlStore.'ratings&store_id='.$store_id.'&_='.$marca);

      $data = [];
      if($response->json()){
        $response = $response->json();
        if($response['status'] == 'success'){
          $data = $response['data'];
        }
      }

      return response()->json($data);

    }

    public function getDialogs($store_id, Request $request)
    {

      $marca = Carbon::now()->timestamp * 1000;

      $start = '&start='.$request->start;

      $length = '&length='.$request->length;

      $url = $this->urlStore.'dialogs&store_id='.$store_id.$start.$length.'&_='.$marca;

      $response = Http::accept('application/json')->get($url);

      $data = [];
      if($response->json()){
        $response = $response->json();
        if($response['status'] == 'success'){
          $data = $response['data'];
        }
      }

      return response()->json($data);

    }

}

