<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Client;
use App\Models\Stores;
use Illuminate\Support\Facades\Auth;

class ClientController extends Controller
{
    public function index(Request $request){
        // dd();
      $client = Client::find(Auth::user()->num);
      // $client = Client::find(1026071);
      

      return response()->json($client);
    }
}
