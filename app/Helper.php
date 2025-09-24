<?php

if (! function_exists('current_store_id')) {

    function current_store_id()
    {
        return app()->bound('currentStoreId') ? app('currentStoreId') : null;
    }
}

if (! function_exists('current_store')) {

    function current_store()
    {
        return app()->bound('current_store') ? app('current_store') : null;
    }
}
