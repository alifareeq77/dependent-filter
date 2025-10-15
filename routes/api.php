<?php

use Illuminate\Support\Facades\Route;

Route::get('/{resource}/filters/options', [\AwesomeNova\Http\Controllers\FilterController::class, 'options'])->name('dependent-filter.resource.options');
Route::get('/{resource}/lens/{lens}/filters/options', [\AwesomeNova\Http\Controllers\LensFilterController::class, 'options'])->name('dependent-filter.lens.options');
