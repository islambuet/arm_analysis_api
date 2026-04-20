<?php

use App\Http\Controllers as Controllers;
use Illuminate\Support\Facades\Route;


$url='product_presentation_data_entry/product_information';
$controllerClass= Controllers\product_presentation_data_entry\ProductInformationController::class;
/** @noinspection DuplicatedCode */
Route::middleware('logged-user')->group(function()use ($url,$controllerClass){
    Route::match(['GET','POST'],$url.'/initialize', [$controllerClass, 'initialize']);
    Route::match(['GET','POST'],$url.'/get-items', [$controllerClass, 'getItems']);
    Route::match(['GET','POST'],$url.'/get-item/{itemId}', [$controllerClass, 'getItem']);
    Route::post($url.'/save-item', [$controllerClass, 'saveItem']);
    Route::post($url.'/save-items', [$controllerClass, 'saveItems']);
});
