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
use Illuminate\Support\Facades\Cache;
use App\Helpers\General\CollectionHelper;


use Illuminate\Http\Client\Pool;
class StoresController extends Controller
{
    use StoreTraits;

    private $categories = ['woman','man','xl','kids','accessories'];

    private $url = 'https://www.modatex.com.ar/modatexrosa3/';

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

    public function getStoresRosa(Request $request)
    {

      $this->validate($request, [
          'categorie' => 'required',
          // 'plan'    => 'required',
      ]);

      $nameChache = 'stores';

      if (Cache::has($nameChache)) {
        $data = Cache::get($nameChache);
        $data = $data->where('type', $request->categorie)->pluck('data')->collapse();
      
        return response()->json(CollectionHelper::paginate($data, 16));
      }

      $urls = [];

      foreach ($this->storesPlanes as $p => $plan) {
        foreach ($this->categories as $c => $categorie) {
          $urls[$categorie.$plan][] = $this->url.'json/cache_'.$categorie.$plan;
        }
      }

      $collection = collect($urls);

      $consultas = Http::pool(fn (Pool $pool) => 
        $collection->map(function($urls, $plan) use($pool){
          foreach ($urls as $u => $url) {
            $url = $pool->as($plan)->accept('application/json')->get($url);
          }
          return $urls;
        })->collapse()
      );
    
      $consultas = collect($consultas)->map(function($response, $key) use ($collection){
        $data = [
          'type' => $key,
          'data' => $response->json()['stores'],
          'count' => count($response->json()['stores'])
        ];
        return $data;
      });


      $categories = collect($this->categories)->map(function($categorie) use ($consultas){
        
        $data = $consultas->filter(function($value, $key) use ($categorie) {
          return str_contains($key ,$categorie);
        })->map(function($value){
          return collect($value['data'])->map(function($store){
            return [
              "logo" => env('URL_IMAGE').'/'. $store['profile']['logo'],
              "name" => $store['cover']['title'],
              "local_cd" => $store['local_cd'],
              "min" => $store['profile']['min'],
              "rep" => $store['profile']['modapoints'],
              "vc" => $store['profile']['comp_sal_perc'],
            ];
          });
        })->collapse();

        return [
          'type' => $categorie,
          'data' => $data,
          'count' => count($data)
        ];

      });

      Cache::put($nameChache, $categories , $seconds = 10800);

      $data = $categories->where('type', $request->categorie)->pluck('data')->collapse();
      
      return response()->json(CollectionHelper::paginate($data, 16));
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
