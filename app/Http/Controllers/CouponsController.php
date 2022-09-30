<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Coupons;

class CouponsController extends Controller
{
    public function index()
    {
        $cupones = Coupons::where(function($query) {
          $query->where('CLIENT_NUM',1026071)
                ->whereIn('STAT_CD',[1000,2000]);
        })
        // ->where('COUPON_STR' != 'PROMOEMOTV916')
        ->orderBy('REGISTER_DATE','desc')
        ->get();
        
        return response()->json($cupones);
    }
}
