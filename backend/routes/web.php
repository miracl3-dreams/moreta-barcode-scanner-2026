<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

// No public landing page. This backend only serves the JSON API under /api,
// so `/` is left unrouted and returns 404 instead of fingerprinting the
// framework with Laravel's welcome page.
