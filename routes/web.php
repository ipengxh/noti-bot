<?php

use Illuminate\Support\Facades\Route;

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

Route::get('/login', 'AuthController@login')->name('login');

Route::group(['middleware' => 'auth'], function () {
    Route::get('/', 'HomeController@index')->name('home');

    Route::post('/logout', 'AuthController@logout')->name('logout');
    Route::get('/logout', function () {
        return view('logout');
    })->name('logout.page');

    Route::get('/api', 'ApiController@index')->name('api.index');

    Route::post('/api/example/test', 'ApiController@exampleTest')->name('api.example.test');

    Route::get('/bots/my', 'BotController@my')->name('bot.my');
    Route::get('/bots/create', 'BotController@create')->name('bot.create');
    Route::post('/bots/create', 'BotController@store')->name('bot.store');
    Route::get('/bots/{id}/edit', 'BotController@edit')->name('bot.edit');
    Route::put('/bots/{id}', 'BotController@update')->name('bot.update');
    Route::delete('/bots/{id}', 'BotController@destroy')->name('bot.destroy');
    Route::get('/bots/explore', 'BotController@explore')->name('bot.explore');
});

Route::any('/send', 'ApiController@send')->name('api');
Route::any('/gitlab', 'ApiController@gitlab')->name('gitlab.webhook');

Route::any('/callback', 'CallbackController@index')->name('callback');

Route::get('/mac', 'AuthController@getMac');
Route::get('/scripts', 'ApiController@scripts');