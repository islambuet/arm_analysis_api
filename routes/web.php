<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers as Controllers;

Route::get('/', function () {
    return view('welcome');
});
$url='import';
$controllerClass=Controllers\ImportController::class;

Route::match(['GET','POST'],$url.'/distributors_targets', [$controllerClass, 'distributors_targets']);
