<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
});

Route::middleware(['auth', 'verified', 'role:admin'])->group(function () {
    Route::livewire('users', 'pages::users.index')->name('users.index');
    Route::livewire('roles', 'pages::roles.index')->name('roles.index');
    Route::livewire('permissions', 'pages::permissions.index')->name('permissions.index');
});

require __DIR__.'/settings.php';
