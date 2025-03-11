<?php

/** @var \Laravel\Lumen\Routing\Router $router */

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/

$router->get('/', function () use ($router) {
    return $router->app->version();
});

$router->group(['prefix' => 'api'], function () use ($router) {
    $router->post('/register', 'AuthController@register');
    $router->post('/login', 'AuthController@login');

    $router->group(['middleware' => 'auth'], function () use ($router) {
        $router->post('/logout', 'AuthController@logout'); // Logout
        $router->post('/blogs', 'BlogController@store'); // Create blog
        $router->get('/blogs', 'BlogController@index'); // Fetch all blogs
        $router->get('/blogs/search', 'BlogController@search'); // Search blogs
        $router->get('/blogs/archived', 'BlogController@archivedBlogs'); // Get archived blogs
        $router->get('/blogs/{id}', 'BlogController@show'); // Fetch single blog
        $router->post('/blogs/{id}', 'BlogController@update'); // Update blog
        $router->delete('/blogs/{id}', 'BlogController@destroy'); // Delete blog
    
        $router->post('/blogs/{id}/like', 'BlogController@like'); // Like/unlike blog
        $router->post('/blogs/{id}/rate', 'BlogController@rate'); // Rate blog

        $router->post('/blogs/{id}/comments', 'BlogController@comment'); // Comment on blog
        $router->delete('/comments/{id}', 'BlogController@deleteComment'); // Delete comment

        $router->post('/blogs/{id}/restore', 'BlogController@restore'); // Restore archived blog
    });
});