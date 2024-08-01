<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/wl-nft',function(Request $request){
    $data = DB::table('whitelist_nft')->select('walletAddress')->orderBy('walletAddress','asc');
    $wl = $data->pluck('walletAddress');
    foreach($wl as $item){
        echo $item."\n";
    }
});

