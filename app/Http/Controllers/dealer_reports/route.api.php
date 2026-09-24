<?php

use App\Http\Controllers as Controllers;
use Illuminate\Support\Facades\Route;

$url='dealer_reports/dealer_sales_target';
$controllerClass= Controllers\dealer_reports\DealerSalesTargetReportController::class;
/** @noinspection DuplicatedCode */
Route::middleware('logged-user')->group(function()use ($url,$controllerClass){
    Route::match(['GET','POST'],$url.'/initialize', [$controllerClass, 'initialize']);
    Route::match(['GET','POST'],$url.'/get-items', [$controllerClass, 'getItems']);
});
$url='dealer_reports/dealers_sales_entry_details';
$controllerClass= Controllers\dealer_reports\DealersSalesEntryDetailsController::class;
/** @noinspection DuplicatedCode */
Route::middleware('logged-user')->group(function()use ($url,$controllerClass){
    Route::match(['GET','POST'],$url.'/initialize', [$controllerClass, 'initialize']);
    Route::match(['GET','POST'],$url.'/get-items', [$controllerClass, 'getItems']);
});
$url='dealer_reports/dealer_sales_distributor_vs_dealer';
$controllerClass= Controllers\dealer_reports\DealerSalesDistributorDealerReportController::class;
/** @noinspection DuplicatedCode */
Route::middleware('logged-user')->group(function()use ($url,$controllerClass){
    Route::match(['GET','POST'],$url.'/initialize', [$controllerClass, 'initialize']);
    Route::match(['GET','POST'],$url.'/get-items', [$controllerClass, 'getItems']);
});
