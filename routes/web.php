<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', 'reservations_controller@selector');
Route::post('/reservations/gettribune', 'reservations_controller@gettribune');
Route::post('/reservations/checkseats', 'reservations_controller@checkseats');
Route::post('/reservations/add_reservation', 'reservations_controller@add_reservation');


Route::get('/tribune/new', 'tribune_controller@new_tribune');
Route::get('/tribune/update', 'tribune_controller@update_tribune');
Route::post('/tribune/save', 'tribune_controller@save');
Route::any('/tribune/change', 'tribune_controller@change');
Route::post('/tribune/delete', 'tribune_controller@delete');
Route::post('/tribune/modify', 'tribune_controller@update');

Route::get( '/download/example', 'tribune_controller@download');

	
Route::get('/clear-cache', function() {
    Artisan::call('cache:clear');
    return "Cache is cleared";
});