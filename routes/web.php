<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/wl-nft',function(Request $request){
    $data = DB::table('whitelist_nft_copy')->select('walletAddress')->orderBy('walletAddress','asc');
    $wl = $data->pluck('walletAddress');
    $html = "";
    foreach($wl as $item){
        $html .= htmlspecialchars($item) . "<br>";
    }
    return $html;
});

