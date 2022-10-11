<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Coupons;
use Auth;

class CouponsController extends Controller
{
    public function index()
    {
        $cupones = Coupons::where(function($query) {
          $query->where('CLIENT_NUM',Auth::user()->num)
                ->whereIn('STAT_CD',[1000,2000]);
        })
        // ->where('COUPON_STR' != 'PROMOEMOTV916')
        ->orderBy('REGISTER_DATE','desc')
        ->get();
        
        return response()->json($cupones);
    }
}
