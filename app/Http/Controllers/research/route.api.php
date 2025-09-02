<?php

use App\Http\Controllers as Controllers;
use Illuminate\Support\Facades\Route;
$url='research/market_size_setup';
$controllerClass= Controllers\research\MarketSizeSetupController::class;
/** @noinspection DuplicatedCode */
Route::middleware('logged-user')->group(function()use ($url,$controllerClass){
    Route::match(['GET','POST'],$url.'/initialize', [$controllerClass, 'initialize']);
    Route::match(['GET','POST'],$url.'/{analysisYearId}/get-items', [$controllerClass, 'getItems']);
    Route::match(['GET','POST'],$url.'/{analysisYearId}/get-item/{itemId}', [$controllerClass, 'getItem']);
    Route::post($url.'/save-item', [$controllerClass, 'saveItem']);
});
$url='research/distributors_sales';
$controllerClass= Controllers\research\DistributorsSalesController::class;
/** @noinspection DuplicatedCode */
Route::middleware('logged-user')->group(function()use ($url,$controllerClass){
    Route::match(['GET','POST'],$url.'/initialize', [$controllerClass, 'initialize']);
    Route::match(['GET','POST'],$url.'/get-items', [$controllerClass, 'getItems']);
    Route::match(['GET','POST'],$url.'/get-item/{itemId}', [$controllerClass, 'getItem']);
    Route::post($url.'/save-item', [$controllerClass, 'saveItem']);
});
$url='research/distributors_targets';
$controllerClass= Controllers\research\DistributorsTargetsController::class;
/** @noinspection DuplicatedCode */
Route::middleware('logged-user')->group(function()use ($url,$controllerClass){
    Route::match(['GET','POST'],$url.'/initialize', [$controllerClass, 'initialize']);
    Route::match(['GET','POST'],$url.'/get-items', [$controllerClass, 'getItems']);
    Route::match(['GET','POST'],$url.'/get-item/{itemId}', [$controllerClass, 'getItem']);
    Route::post($url.'/save-item', [$controllerClass, 'saveItem']);
});
$url='research/distributors_stock';
$controllerClass= Controllers\research\DistributorsStockController::class;
/** @noinspection DuplicatedCode */
Route::middleware('logged-user')->group(function()use ($url,$controllerClass){
    Route::match(['GET','POST'],$url.'/initialize', [$controllerClass, 'initialize']);
    Route::match(['GET','POST'],$url.'/get-item', [$controllerClass, 'getItem']);
    Route::post($url.'/save-item', [$controllerClass, 'saveItem']);
});
$url='research/distributors_plan_3yrs';
$controllerClass= Controllers\research\DistributorsPlan3yrsController::class;
/** @noinspection DuplicatedCode */
Route::middleware('logged-user')->group(function()use ($url,$controllerClass){
    Route::match(['GET','POST'],$url.'/initialize', [$controllerClass, 'initialize']);
    Route::match(['GET','POST'],$url.'/get-items', [$controllerClass, 'getItems']);
    Route::post($url.'/get-item/{itemId}', [$controllerClass, 'getItem']);
    Route::post($url.'/save-item', [$controllerClass, 'saveItem']);
});
