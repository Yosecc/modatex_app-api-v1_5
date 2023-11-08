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
            'title' => $cms->title,  
            'data_json' => json_decode($cms->data_json),  
        ];
        
        return response()->json($cms);
    }
}
