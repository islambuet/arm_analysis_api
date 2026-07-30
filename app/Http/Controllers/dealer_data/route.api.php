<?php

use App\Http\Controllers as Controllers;
use Illuminate\Support\Facades\Route;


$url='dealer_data/dealers_sales_request';
$controllerClass= Controllers\dealer_data\DealersSalesRequestController::class;
/** @noinspection DuplicatedCode */
Route::middleware('logged-user')->group(function()use ($url,$controllerClass){
    Route::match(['GET','POST'],$url.'/initialize', [$controllerClass, 'initialize']);
    Route::match(['GET','POST'],$url.'/get-items', [$controllerClass, 'getItems']);
    Route::match(['GET','POST'],$url.'/get-item/{itemId}', [$controllerClass, 'getItem']);
    Route::match(['GET','POST'],$url.'/delete-item/{itemId}', [$controllerClass, 'deleteItem']);
    Route::post($url.'/save-item', [$controllerClass, 'saveItem']);
    Route::post($url.'/save-items', [$controllerClass, 'saveItems']);
});
$url='dealer_data/dealers_sales_approval';
$controllerClass= Controllers\dealer_data\DealersSalesApprovalController::class;
/** @noinspection DuplicatedCode */
Route::middleware('logged-user')->group(function()use ($url,$controllerClass){
    Route::match(['GET','POST'],$url.'/initialize', [$controllerClass, 'initialize']);
    Route::match(['GET','POST'],$url.'/get-items', [$controllerClass, 'getItems']);
    Route::match(['GET','POST'],$url.'/get-item/{itemId}', [$controllerClass, 'getItem']);
    Route::match(['GET','POST'],$url.'/approve-item/{itemId}', [$controllerClass, 'approveItem']);
    Route::match(['GET','POST'],$url.'/delete-item/{itemId}', [$controllerClass, 'deleteItem']);
    Route::post($url.'/save-item', [$controllerClass, 'saveItem']);
    Route::post($url.'/save-items', [$controllerClass, 'saveItems']);
});
