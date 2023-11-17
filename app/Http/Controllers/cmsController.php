<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PagesCms;

class cmsController extends Controller
{
    public function get($id)
    {   
        $cms = PagesCms::find($id);
        $cms = [
            'name' =>utf8_decode($cms->title),  
            'editor' => utf8_decode($cms->data_json),  
        ];
        
        return response()->json($cms);
    }
}
