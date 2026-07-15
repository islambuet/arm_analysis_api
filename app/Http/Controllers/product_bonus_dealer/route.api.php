<?php

use App\Http\Controllers as Controllers;
use Illuminate\Support\Facades\Route;


$url='product_bonus_dealer/bonus_setup';
$controllerClass= Controllers\product_bonus_dealer\BonusSetupController::class;
/** @noinspection DuplicatedCode */
Route::middleware('logged-user')->group(function()use ($url,$controllerClass){
    Route::match(['GET','POST'],$url.'/initialize', [$controllerClass, 'initialize']);
    Route::match(['GET','POST'],$url.'/get-items', [$controllerClass, 'getItems']);
    Route::match(['GET','POST'],$url.'/get-item/{itemId}', [$controllerClass, 'getItem']);
    Route::post($url.'/save-item', [$controllerClass, 'saveItem']);
    Route::post($url.'/save-items', [$controllerClass, 'saveItems']);
});
$url='product_bonus_dealer/generate_eligible_list';
$controllerClass= Controllers\product_bonus_dealer\GenerateEligibleListController::class;
/** @noinspection DuplicatedCode */
Route::middleware('logged-user')->group(function()use ($url,$controllerClass){
    Route::match(['GET','POST'],$url.'/initialize', [$controllerClass, 'initialize']);
    Route::match(['GET','POST'],$url.'/get-items', [$controllerClass, 'getItems']);
    Route::match(['GET','POST'],$url.'/get-item/{itemId}', [$controllerClass, 'getItem']);
    Route::match(['GET','POST'],$url.'/get-item-details/{itemId}', [$controllerClass, 'getItemDetails']);
    Route::post($url.'/save-item', [$controllerClass, 'saveItem']);
    Route::post($url.'/save-items', [$controllerClass, 'saveItems']);
});
$url='product_bonus_dealer/eligible_list';
$controllerClass= Controllers\product_bonus_dealer\EligibleListController::class;
/** @noinspection DuplicatedCode */
Route::middleware('logged-user')->group(function()use ($url,$controllerClass){
    Route::match(['GET','POST'],$url.'/initialize', [$controllerClass, 'initialize']);
    Route::match(['GET','POST'],$url.'/get-items', [$controllerClass, 'getItems']);
    Route::match(['GET','POST'],$url.'/get-item/{itemId}', [$controllerClass, 'getItem']);
    Route::post($url.'/save-item', [$controllerClass, 'saveItem']);
    Route::post($url.'/save-items', [$controllerClass, 'saveItems']);
});
