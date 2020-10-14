<?php
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Dotenv\Dotenv;

if(file_exists(getcwd() . '/.env')){
	$dotenv = Dotenv::createImmutable();
	$dotenv->load();	
}

require __DIR__.'/kint.phar';

Kint::$aliases[] = 'ddd';

function ddd(...$v){
    d(...$v); die();
}

Kint::$aliases[] = 'dd';

if(!function_exists('dd')){
    function dd(...$v){
        s(...$v); die();
    }
}


function normalize_path($path){
    // ensure it does not end in a slash, but does start with one
    return rtrim( Str::start(clean_path($path), '/'), '/');
}

function base_path($path = ''){
    return dirname(__FILE__) . normalize_path($path);
}

function storage_path($path = ''){
    $path_base = env('STORAGE_PATH', '/files');
    return base_path($path_base . normalize_path($path));
}


function clean_path($path){
    $path_array = explode('/', $path);
    return implode('/', array_filter($path_array));
}


