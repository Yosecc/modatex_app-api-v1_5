<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Http\Traits\SlidersTraits;

class Slider extends Model
{
  use SlidersTraits;

    protected $connection = 'mysql';
    protected $table = 'SLIDE';

    public $sliders;

    public $params;

    public function __construct($params = null)
    {
        $this->params = $params;
    }

    public  function getSliders()
    {
      if (isset($this->params['slide_category'])) {
        $categories = explode(',' , $this->params['slide_category']);

        $query = $this->where(function($query) use ($categories){
          foreach ($categories as $key => $value) {
            if($key == 0){
              $query->where('category','LIKE', '%'.$value.'%');
            }else{
              $query->orWhere('category','LIKE', '%'.$value.'%');
            }
          }
        })
        ->where('day','LIKE','%'.date('N').'%')
        ->where('day','!=','null')
        ->where('type',0)
        ->orderBy('orden','asc')
        ->get();

        return $this->slidersArray($query);
      }   
    }
}
