<?php

use App\Http\Controllers as Controllers;
use Illuminate\Support\Facades\Route;
$url='research/research_sales_team';
$controllerClass= Controllers\research\ResearchSalesTeamController::class;
/** @noinspection DuplicatedCode */
Route::middleware('logged-user')->group(function()use ($url,$controllerClass){
    Route::match(['GET','POST'],$url.'/initialize', [$controllerClass, 'initialize']);
    Route::match(['GET','POST'],$url.'/{analysisYearId}/get-items', [$controllerClass, 'getItems']);
    Route::match(['GET','POST'],$url.'/{analysisYearId}/get-item/{itemId}', [$controllerClass, 'getItem']);
    Route::post($url.'/save-item', [$controllerClass, 'saveItem']);
});
$url='research/crop_business_team';
$controllerClass= Controllers\research\CropBusinessTeamController::class;
/** @noinspection DuplicatedCode */
Route::middleware('logged-user')->group(function()use ($url,$controllerClass){
    Route::match(['GET','POST'],$url.'/initialize', [$controllerClass, 'initialize']);
    Route::match(['GET','POST'],$url.'/get-items', [$controllerClass, 'getItems']);
    Route::match(['GET','POST'],$url.'/get-item/{itemId}', [$controllerClass, 'getItem']);
    Route::post($url.'/save-item', [$controllerClass, 'saveItem']);
});
