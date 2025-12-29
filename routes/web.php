<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers as Controllers;

Route::get('/', function () {
    return view('welcome');
});
$url='import';
$controllerClass=Controllers\ImportController::class;

Route::match(['GET','POST'],$url.'/distributors_sales', [$controllerClass, 'distributors_sales']);
Route::match(['GET','POST'],$url.'/distributors_targets', [$controllerClass, 'distributors_targets']);
Route::match(['GET','POST'],$url.'/incentive_varieties', [$controllerClass, 'incentive_varieties']);
Route::match(['GET','POST'],$url.'/market_size_setup_territory', [$controllerClass, 'market_size_setup_territory']);
