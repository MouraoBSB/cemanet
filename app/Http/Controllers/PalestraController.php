<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-25

namespace App\Http\Controllers;

class PalestraController extends Controller
{
    public function index()
    {
        return view('palestras.index');
    }
}
