<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function getMetadata()
{
    $settings = \App\Models\Setting::orderBy('order')->get()->groupBy('group');

    return response()->json($settings);
}
}
