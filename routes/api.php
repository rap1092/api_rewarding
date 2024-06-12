<?php

use App\Http\Controllers\Api\Members;
use App\Http\Controllers\Api\Refferal;
use App\Http\Controllers\Api\Tasks;
use App\Http\Controllers\Api\Withdraw;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/endairdrop',[Withdraw::class,'getTime']);
Route::post('/register',[Members::class,'register']);
Route::post('/isready',[Members::class,'memberIsLogedin']);
Route::post('/userInfo',[Members::class,'getUserInfo']);
Route::post('/farming',[Members::class,'farming']);
Route::post('/getfarming',[Members::class,'getFarming']);
Route::post('/claim',[Members::class,'claim']);
Route::post('/refferal/getinfo',[Refferal::class,'getInfo']);
Route::post('/tasks/getinfo',[Tasks::class,'getTasks']);
Route::post('/tasks/claimCreate',[Tasks::class,'claimCreate']);
Route::post('/tasks/claim',[Tasks::class,'claim']);
