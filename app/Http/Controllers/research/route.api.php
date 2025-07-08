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
$url='research/sales_distributors';
$controllerClass= Controllers\research\SalesDistributorsController::class;
/** @noinspection DuplicatedCode */
Route::middleware('logged-user')->group(function()use ($url,$controllerClass){
    Route::match(['GET','POST'],$url.'/initialize', [$controllerClass, 'initialize']);
    Route::match(['GET','POST'],$url.'/get-items', [$controllerClass, 'getItems']);
    Route::match(['GET','POST'],$url.'/get-item/{itemId}', [$controllerClass, 'getItem']);
    Route::post($url.'/save-item', [$controllerClass, 'saveItem']);
});
$url='research/targets_distributors';
$controllerClass= Controllers\research\TargetsDistributorsController::class;
/** @noinspection DuplicatedCode */
Route::middleware('logged-user')->group(function()use ($url,$controllerClass){
    Route::match(['GET','POST'],$url.'/initialize', [$controllerClass, 'initialize']);
    Route::match(['GET','POST'],$url.'/get-items', [$controllerClass, 'getItems']);
    Route::match(['GET','POST'],$url.'/get-item/{itemId}', [$controllerClass, 'getItem']);
    Route::post($url.'/save-item', [$controllerClass, 'saveItem']);
});
