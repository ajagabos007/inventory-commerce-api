<?php

if (! function_exists('current_store_id')) {

    function current_store_id()
    {
        return app()->bound('currentStoreId') ? app('currentStoreId') : null;
    }
}
