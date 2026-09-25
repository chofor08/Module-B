<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ItemsResource;
use App\Models\Items;

class ItemsController extends Controller
{
    public function index()
    {
        return ItemsResource::Collection(Items::all());
    }

}
