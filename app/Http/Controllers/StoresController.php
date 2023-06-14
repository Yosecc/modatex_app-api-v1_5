<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Store;
use App\Models\Products;
use App\Http\Traits\HelpersTraits;
use App\Http\Traits\StoreTraits;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;
use App\Helpers\General\CollectionHelper;


use Illuminate\Http\Client\Pool;
class StoresController extends Controller
{
    use StoreTraits;

    private $categories = ['woman','man','xl','kids','accessories'];

    private $url = 'https://www.modatex.com.ar/modatexrosa3/';

    private $urlStore = 'https://www.modatex.com.ar/?c=Store::';

    private $storesPlanes = [
        '_black.json',
        '_platinum.json',
        '_gold.json',
        '_blue.json'
    ];

    private $urlProduct = 'https://www.modatex.com.ar/modatexrosa3/?c=Products::get&';


    /*
    * @return Store Array
    * @return $store = Collection()   
    */
    public function getStores(Request $request){

        // $stores = Store::Active()->paginate();
        $store = new Store($request->all());
        $stores = $store->getStores($request->all());

        return response()->json($stores,200);
    }

    /*
    * @params $store == LOCAL_NAME_KEYWORD value (slug)
    * @return Store Object
    * @return $store = Collection()   
    */
    public function getStore($store, Request $request){

      $store = new Store(['store' => $store]);
      $store = $store->getStore($request->all());

      return response()->json(['status'=>true,'store'=>$store],200);

    }

    private function crearConsulta($data,$request)
    {
      if($request->search){
        return $data->filter(fn ($store) => Str::is(Str::lower($request->search).'*',Str::lower($store['name'])) );
      }elseif($request->categorie == 'all'){
        return $data->shuffle();
      }else{
        return $data->filter(fn ($store) => Str::is(Str::lower($request->categorie).'*',Str::lower($store['categorie'])) );
      }

      // return $data;
    }

    public function getStoresRosa(Request $request)
    {

      $this->validate($request, [
          'categorie' => 'required',
          // 'plan'    => 'required',
      ]);

      // $nameChache = 'stores';

      $response = Http::accept('application/json')->get($this->urlStore.'all');
      if($response->json() == null){
        return response()->json(CollectionHelper::paginate(collect([]), 16));

      }
      $response = collect($response->collect()['data']);

      $stores = Store::whereIn('LOCAL_CD',$response->pluck('id')->all())->get();

      $response = $this->crearConsulta($response->map(function($tienda) use ($stores){
        $store = $stores->where('LOCAL_CD', $tienda['id'])->first();
        // dd($store);
        $categorie = '';
        if($store['USE_MAN'] == "Y"){
          $categorie = 'man';
        }elseif($store['USE_WOMAN'] == "Y"){
          $categorie = 'woman';
        }elseif($store['USE_CHILD'] == "Y"){
          $categorie = 'kids';
        }elseif($store['USE_ACCESORY'] == "Y"){
          $categorie = 'accessories';
        }elseif($store['USE_SPECIAL'] == "Y"){
          $categorie = 'xl';
        }

        return [
          "logo" => env('URL_IMAGE').'/common/img/logo/'. $tienda['logo'],
          "name" => $tienda['name'],
          "local_cd" => $tienda['id'],
          "min" => $store ? $store['LIMIT_PRICE']: '',
          "rep" => $store ? $store['MODAPOINT']-1: '',
          "vc" => '',
          "categorie" => $categorie
        ];
      }),$request);
      
      return response()->json(CollectionHelper::paginate( $response, 16));
      
      
      // if (Cache::has($nameChache) && !isset($request->reload)) {
      //   $data = Cache::get($nameChache);      
      //   return response()->json(CollectionHelper::paginate($this->crearConsulta($data,$request), 16));
      // }

      // $urls = [];
    
      // foreach ($this->storesPlanes as $p => $plan) {
      //   foreach ($this->categories as $c => $categorie) {
      //     $urls[$categorie.$plan][] = $this->url.'json/cache_'.$categorie.$plan;
      //   }
      // }

      // $collection = collect($urls);

      // $consultas = Http::pool(fn (Pool $pool) => 
      //   $collection->map(function($urls, $plan) use($pool){
      //     foreach ($urls as $u => $url) {
      //       $url = $pool->as($plan)->accept('application/json')->get($url);
      //     }
      //     return $urls;
      //   })->collapse()
      // );

      // $consultas = collect($consultas)->map(function($response, $key) use ($collection){
      //   $data = [
      //     'type' => $key,
      //     'data' => $response->json()['stores'],
      //     'count' => count($response->json()['stores'])
      //   ];
      //   return $data;
      // });

      // $categories = collect($this->categories)->map(function($categorie) use ($consultas){
      //   // dd($categorie);
      //   $data = $consultas->filter(function($value, $key) use ($categorie) {
      //     return Str::is($categorie.'*',$key);
      //   })
      //   ->map(function($value){
      //     return collect($value['data'])->map(function($store){
            // return [
            //   "logo" => env('URL_IMAGE').'/'. $store['profile']['logo'],
            //   "name" => $store['cover']['title'],
            //   "local_cd" => $store['local_cd'],
            //   "min" => $store['profile']['min'],
            //   "rep" => $store['profile']['modapoints'],
            //   "vc" => $store['profile']['comp_sal_perc'],
            // ];
      //     });
      //   })
      //   ->collapse()
      //   ;

      //   return [
      //     'type' => $categorie,
      //     'data' => $data,
      //     'count' => count($data)
      //   ];

      // });

      // Cache::put($nameChache, $categories , $seconds = 10800);

      // $data = $categories;

      // /return response()->json(CollectionHelper::paginate($this->crearConsulta($data,$request), 16));
    }

    public function getCategoriesStore(Request $request){

      $this->validate($request, [
          'local_cd' => 'required',
      ]);

      $store = Store::LOCAL_CD($request->local_cd)->first();

      $categorias = $this->categoriesCollection($store, null);

      $categories = [];

      foreach ($categorias as $c => $categoria) {

        if($categoria['descripcion'] == 'mujer'){
          $categoria['clave'] =  'woman';
        }
        elseif($categoria['descripcion'] == 'hombre'){
          $categoria['clave'] =  'man';
        }
        elseif($categoria['descripcion'] == 'Accesorios'){
          $categoria['clave'] =  'accessories';
        }
        // elseif($categoria['descripcion'] == ''){
        //   $categoria['clave'] =  'xl';
        // }
        // elseif($categoria['descripcion'] == ''){
        //   $categoria['clave'] =  'kids';
        // }


        $response = Http::acceptJson()->get($this->urlProduct.'store='.$request->local_cd.'&sections='.$categoria['clave'].'&start=0&length=9999');

        $idsSubscategories = [];
        foreach ($response->collect()->all()['data'] as $key => $value) {
          if(!in_array($value['category_id'], $idsSubscategories)){
            $idsSubscategories[] = $value['category_id'];
          }
        }

        $subcategories = [];
        foreach ($categoria['subcategories'] as $key => $sub) {
          if(in_array($sub['id'], $idsSubscategories)){
            $subcategories[] = $sub;
          }
        }

        $categories[] = [
          'categoria' => [
            'id' => $categoria['id'],
            'name' => $categoria['name'],
            'clave' => $categoria['clave'],
          ],
          'subcategorias' => $subcategories
        ];

      }


      return $categories;
     

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

}
