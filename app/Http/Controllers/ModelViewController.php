<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreModelViewRequest;
use App\Http\Requests\UpdateModelViewRequest;
use App\Models\ModelView;

class ModelViewController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreModelViewRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(ModelView $modelView)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(ModelView $modelView)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateModelViewRequest $request, ModelView $modelView)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ModelView $modelView)
    {
        //
    }
}
