<?php

namespace App\Http\Controllers\main;

use App\Http\Controllers\Controller;
use App\Models\Sources;
use Illuminate\Http\Request;

class SourceController extends Controller
{
    public function index() {
       $sources = Sources::all();
       return parent::handleRespond($sources);
    }
}
