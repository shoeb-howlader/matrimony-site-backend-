<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ConnectionPackage;
use Illuminate\Http\Request;

class AdminPackageController extends Controller
{
    public function index()
    {
        return response()->json([
            'success' => true,
            'data' => ConnectionPackage::orderBy('connection_count', 'asc')->get()
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'connection_count' => 'required|integer',
            'price' => 'required|numeric',
            'discount_price' => 'nullable|numeric',
            'badge_text' => 'nullable|string',
            'is_active' => 'boolean'
        ]);

        $package = ConnectionPackage::create($validated);

        return response()->json(['success' => true, 'data' => $package]);
    }

    public function update(Request $request, $id)
    {
        $package = ConnectionPackage::findOrFail($id);

        $validated = $request->validate([
            'name' => 'string',
            'connection_count' => 'integer',
            'price' => 'numeric',
            'discount_price' => 'nullable|numeric',
            'badge_text' => 'nullable|string',
            'is_active' => 'boolean'
        ]);

        $package->update($validated);

        return response()->json(['success' => true, 'data' => $package]);
    }

    public function destroy($id)
    {
        $package = ConnectionPackage::findOrFail($id);
        $package->delete();

        return response()->json(['success' => true, 'message' => 'প্যাকেজ ডিলিট করা হয়েছে']);
    }
}
