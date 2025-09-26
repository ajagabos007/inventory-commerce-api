<?php

if (! function_exists('current_store')) {

    function current_store()
    {
        return app()->bound('currentStore') ? app('currentStore') : null;
    }
}
