<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use App\Http\Traits\ProductsTraits;
use Auth;

class Products extends Model
{   
    use ProductsTraits;

    protected $connection   = 'mysql';

    public const TABLE_NAME = 'MODELOS';
    protected $table        = self::TABLE_NAME;

    public const CAMPOS = [
        self::TABLE_NAME.'.NUM',
        self::TABLE_NAME.'.MODA_NUM',
        self::TABLE_NAME.'.GROUP_CD',
        self::TABLE_NAME.'.LOCAL_CD',
        self::TABLE_NAME.'.CODIGO_MODELO',
        self::TABLE_NAME.'.DESCRIPCION',
        self::TABLE_NAME.'.TIPO_MODELO_NUM1',
        self::TABLE_NAME.'.TIPO_MODELO_NUM2',
        self::TABLE_NAME.'.TIPO_MODELO_NUM3',
        self::TABLE_NAME.'.TIPO_MODELO_NUM4',
        self::TABLE_NAME.'.TELA_NUM',
        self::TABLE_NAME.'.ESTAMPADO_NUM',
    ];

    public $name;

    public $store;

    public $products;

    public $product;

    public $perPage;

    public $pageName;

    public $product_id;

    public $request;

    public function __construct($params = null){
        $this->request    = $params;
        $this->store      = $params['store']             ?? null; 
        $this->product    = $params['product']           ?? null;
        $this->product_id = $params['product_id']        ?? null;
        $this->pageName   = $params['product_page_name'] ?? 'product_page';
        $this->perPage    = $params['product_per_page']  ?? 15;
        $this->name       = 'PRODUCTS';
    }

    /*
    * Filter products
    */
   
    public function scopeActive($query){
        return $query->select(self::CAMPOS)
                ->where('STAT_CD', '1000')
                ->where('IS_REGISTERED','Y');
    }

    public function scopeNUM($query, $product_num){
        if ($product_num) {
            return $query->where('NUM',$product_num);
        }
    }

    public function scopeCODIGO_MODELO($query, $product_codigo_modelo){
        if ($product_codigo_modelo) {
            return $query->where('CODIGO_MODELO','LIKE', '%'.$product_codigo_modelo.'%');
        }  
    }

    public function scopeDESCRIPCION($query, $product_description){
        if ($product_description) {
            return $query->where('DESCRIPCION','LIKE','%'.$product_description.'%');
        } 
    }

    public function scopeSlug($query, $slug){
        if($slug)
            return $query->where('DESCRIPCION','LIKE', str_replace("-", " ", $slug).'%');
    }

    public function scopeCategory($query, $category){
        if($category)
            return $query->where('TIPO_MODELO_NUM1', $category);
    }

    public function scopeSubCategory($query, $subcategory)
    {
        if($subcategory)
            return $query->where('TIPO_MODELO_NUM2', $subcategory);
    }

    public function scopeStore($query, $params = ['store_local_cd'=> null,'store_group_cd' => null]){

        if(isset($params['store_local_cd']) && isset($params['store_group_cd']))
            // dd($params);
            return $query->where('LOCAL_CD',$params['store_local_cd'])
                    ->where('GROUP_CD',$params['store_group_cd']);
    }

    public function scopeallFilter($query, $params){
        return $query->NUM($params['product_num']??null)
                    ->CODIGO_MODELO($params['product_codigo_modelo']??null)
                    ->DESCRIPCION($params['product_description']??null)
                    ->Category($params['product_category']??null)
                    ->SubCategory($params['product_subcategory']??null)
                    ->Store($params);
    }

    /*
    *  Filter products parent Store::class
    *  with relations
    *  Accept a collection Required
    */
    public function scopeproductsStore($query, $store){
        if ($store) {
            return $query->Active()
                    ->where('LOCAL_CD',$store['local_cd'])
                    ->where('GROUP_CD',$store['group_cd'])
                    ->with(['detalle']);
        }
    }

    public function scopeproductsStoreNoDetalle($query, $store){
        if ($store) {
            return $query->Active()
                    ->where('LOCAL_CD',$store['local_cd'])
                    ->where('GROUP_CD',$store['group_cd']);
        }
    }

    public function scopegetInProducts($query, $ids)
    {
        $query = $query->Active()->whereIn('NUM',$ids)->get();
        return $this->productsCollection($query, $this->request, true);
    }

    /////////////////////////////////////////
    ////////////////////////////////
    /////////////////////////

    /*
    * Query Products of stores
    * @params $page (optional)
    * @return Array 
    */
    public function scopegetProductsStore($query, $params = null){

        return $query->productsStore($this->store)->allProducts($params);
    }

    /*
    * Query Product 
    * @params $params (optional) | $slug = $this->product
    * @return Collection 
    */
    public function scopegetProduct($query, $params = null){
        $query = $query->Active()->NUM($this->product_id)->Slug($this->product)->with(['detalle'])->first();
        return $this->productCollect($query,$params);
    }

    /*
    * Query Products
    * Return Products limit|paginate|get
    * All Filter
    * @params Products $query
    * @params Request $params
    * @return Collection
    */
    public function scopeallProducts($query, $params){
        $query = $query->Active()->allFilter($params);
        if(isset($params['product_limit'])){
            $query = $query->limit($params['product_limit'])->get();
            return $this->productsCollection($query, $params);
        }
        if(isset($params['product_page'])){

            $query = $query->paginate($perPage = $this->perPage, $columns = ['*'], $pageName = $this->pageName);
            return $this->productsCollection($query, $params);
        }
        return $this->productsCollection($query->get(), $params);
    }

    public function productsCategories($category){
        $query = $this->Active()->Category($category)->orderBy('NUM','desc')->allFilter($this->request);
        
        if(isset($this->request['product_limit'])){
            $query = $query->limit($this->request['product_limit'])->get();
            return $this->productsCollection($query, $this->request);
        }

        if(isset($this->request['product_page'])){
            $query = $query->paginate($perPage = $this->perPage, $columns = ['*'], $pageName = $this->pageName);

            return $this->productsCollection($query, $this->request);
        }
    }

    /*
     * $query Products
     * Return subcategories of query Products
     * @params Product $query
     * @params Request $params
     * @return Collection
     */
    public function scopegetSubcategorias($query, $params){

        $query = $query->productsStore($this->store)
                    ->Category($params['product_category']??null)
                    ->get();

        return $this->dataSubcategoriasCollection($query);
    }

    /*
    *
    */
    public function scopegetRelacionados($query,$params){
      // dd($query->productsStore($this->store)->get());
    }
    
    /////////////////////////////////////////
    ////////////////////////////////
    /////////////////////////

    /*
    *  Relation children ProductsDetail::class
    */
    public function detalle(){
        return $this->hasMany(ProductsDetail::class,'PARENT_NUM','NUM')->where('STAT_CD','1000');
    }
    public function TipoModeloUno(){
        return $this->hasOne(TipoModeloUno::class,'NUM','TIPO_MODELO_NUM1')->where('STAT_CD','1000');
    }
    public function TipoModeloDos(){
        return $this->hasOne(TipoModeloDos::class,'NUM','TIPO_MODELO_NUM2')->where('STAT_CD','1000');
    }
    public function TipoModeloTres(){
        return $this->hasOne(TipoModeloTres::class,'NUM','TIPO_MODELO_NUM3')->where('STAT_CD','1000');
    }
    public function TipoModeloCuatro(){
        return $this->hasOne(TipoModeloCuatro::class,'NUM','TIPO_MODELO_NUM4')->where('STAT_CD','1000');
    }
    public function Tela(){
        return $this->hasOne(Code::class, 'NUM','TELA_NUM');
    }
    public function Estampado(){
        return $this->hasOne(Code::class, 'NUM','ESTAMPADO_NUM');
    }
    public function Images(){
        return $this->hasMany(Images::class,'PARENT_NUM','NUM')->where('CATEGORY_CODE', 1000);
    }
    public function Price(){
        return $this->hasOne(ModeloVenta::class, 'NUM_TSHOPMODELOS','NUM');
    }

    public function isFavorite(){
        return $this->hasOne(ProductFavorite::class,'MODELO_NUM','NUM')
                    ->where('CLIENT_NUM',Auth::user()->num);
    }
    
}
