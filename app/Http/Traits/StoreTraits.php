<?php

namespace App\Http\Traits;
use Illuminate\Support\Facades\Auth;
use App\Http\Traits\HelpersTraits;
use App\Models\TipoModeloUno;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use App\Models\Products;
use App\Models\Store;

trait StoreTraits {

	use HelpersTraits;

  public $directory_logo;

  public $directory_banner;

  public $directory_cover;

  public function __construct(){
    $this->directory_logo   = env('URL_IMAGE').'/modatexrosa2/img/modatexrosa2/';
    $this->directory_banner = env('URL_IMAGE').'/common/img/logo/';
    $this->directory_cover  = env('URL_IMAGE').'/modatexrosa2/img/covers/';
  }
	 /*
    * Prepare dataa
    * @params Store Object Query 
    * @return Collect
    */
    public function dataArrangement($store, $params = null){

      $data = $store;

      if(!$store){
        return null;
      }

      $store = collect([
        'id'          => $store->NUM,
        'group_cd'    => $store->GROUP_CD,
        'local_cd'    => $store->LOCAL_CD,
        'name'        => $store->LOCAL_NAME,
        'address'     => $store->ADDRESS,
        'phone'       => $store->PHONE,
        'email'       => $store->MAIL,
        'logo'        => env('URL_IMAGE').'/common/img/logo/'.$store->LOGO_FILE_NAME,
        'banner'      => $this->image('banner',$store->BANNER_FILE_NAME),
        'cover'       => $this->cover($store->LOCAL_NAME),
        'limit_price' => $store->LIMIT_PRICE,
        'appearance'  => unserialize($store->BANNER_BG_COLOR),
        'promos'      => [
          'envio_gratis' => [
            'days' => $this->transformStringNumberArray(json_decode($store->PROMO_ENVIO_GRATIS)),
            'price'=> $store->PROMO_ENVIO_GRATIS_MONTO
          ],
        ],
        
      ]);

      // if(!isset($params['store_not_categories'])){
        // dd($this->categoriesCollection($data, $params));
        $store->merge(['categories'  => $this->categoriesCollection($data, $params)]);
      // }

        // dd($this->categoriesCollection($data, $params));

      // if (empty($params['is_store'])) {
      //   $products      = new Products(['store'=>$store]);
      //   $subcategories = $products->getSubcategorias($params);
      //   $products      = $products->getProductsStore($params);
      //   $store = $store->merge(['subcategories' => $subcategories, 'products'=>$products ]);
      // }

      return $store;
    }

    public function categoriesCollection($store, $params){
      $categorias_ids = [];

      foreach (TipoModeloUno::CATEGORIES as $key => $value) {
        if ($store[$value['name']] == 'Y') {
          $categorias_ids[] = $value['table_id'];
        }
      }

      $categoria = new TipoModeloUno(['categorias' => $categorias_ids]);
      $categoria = $categoria->getCategory($params);

      return $categoria;
    }

    public function filterCategory($category_id){
      foreach (TipoModeloUno::CATEGORIES as $key => $value) {
        if ($value['table_id'] == $category_id) {
          return $value;
        }
      }
    }

    /*
    * Prepare dataa
    * @params Store Object Query 
    * @return Array
    */
    public function dataArrayArrangement($stores,$params = null){
    	$func = function($store) use ($params) {
    		$store = $this->dataArrangement($store, $params);
		    return $store;
			};

			return array_map($func, $stores->all());
    }

    public function image($type, $name){
      // dd($this );
      if ($type == 'banner') {
        return env('URL_IMAGE').'/common/img/logo/'.$name;
      }elseif($type == 'logo'){
        return env('URL_IMAGE').'/common/img/logo/'. Str::lower(Str::slug($name, '')).'.webp';
      }
      
    }

    public function cover($name){
      // https://netivooregon.s3.amazonaws.com/modatexrosa2/img/covers/sky.gif
      $name = env('URL_IMAGE').'/modatexrosa2/img/covers/black/'.Str::lower( Str::slug( $name, '' )).'.gif';
      return $name;
    }

    


	
}