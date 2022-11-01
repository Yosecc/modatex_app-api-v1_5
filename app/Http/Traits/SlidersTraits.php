<?php

namespace App\Http\Traits;
use Illuminate\Support\Facades\Auth;
use App\Http\Traits\HelpersTraits;
use Illuminate\Http\Request;
use App\Models\Store;

trait SlidersTraits {

	use HelpersTraits;

  public function sliderCollection($slider)
  {
    // dd($slider);
    return collect([
      'url' => $slider['url'],
      'img' => $this->image($slider['img_path']),
      'title' => $slider['img_text'],
    ]);
  }

  public function slidersArray($sliders)
  {
    $func = function($slider){
      $slider = $this->sliderCollection($slider);
      return $slider;
    };
    return array_map($func, $sliders->all());
  }

  public function image($img)
  {
    return env('URL_IMAGE').'/common/img/main_slide/1.1/'.$img;
  }


}