<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Client;
use App\Models\Stores;

class ClientController extends Controller
{
    public function index(Request $request){
        dd(Stores::limit(1)->first());
        dd(Client::Active()->SearchClient($request->client_id)->limit(1)->first());
    }
}
