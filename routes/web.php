<?php

use Illuminate\Support\Facades\Route;

// This is an API; the only page is its documentation.
Route::redirect('/', '/docs/api');
