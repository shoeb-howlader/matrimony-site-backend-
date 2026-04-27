<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use Illuminate\Http\Request;

class AdminContactController extends Controller
{
  public function index(Request $request)
{
    $query = ContactMessage::query();

    // ১. স্ট্যাটাস ফিল্টার (ট্যাবের জন্য)
    if ($request->status && $request->status !== 'all') {
        $query->where('status', $request->status);
    }

    // ২. সার্চ ফিল্টার (নাম, ইমেইল, মোবাইল বা বিষয়)
    if ($request->search) {
        $search = $request->search;
        $query->where(function($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
              ->orWhere('email', 'like', "%{$search}%")
              ->orWhere('mobile', 'like', "%{$search}%")
              ->orWhere('subject', 'like', "%{$search}%");
        });
    }

    // ৩. সর্টিং (ডিফল্ট লেটেস্ট)
    $sortField = $request->sort_by ?? 'created_at';
    $sortOrder = $request->sort_dir ?? 'desc';
    $query->orderBy($sortField, $sortOrder);

    // ৪. পেজিনেশন
    $messages = $query->paginate($request->per_page ?? 15);

    // ৫. ট্যাবের কাউন্ট (Stats)
    $stats = [
        'all' => ContactMessage::count(),
        'pending' => ContactMessage::where('status', 'pending')->count(),
        'resolved' => ContactMessage::where('status', 'resolved')->count(),
    ];

    return response()->json([
        'success' => true,
        'data' => $messages,
        'stats' => $stats
    ]);
}

    public function updateStatus(Request $request, $id)
    {
        $request->validate(['status' => 'required|in:pending,resolved']);

        $message = ContactMessage::findOrFail($id);
        $message->update(['status' => $request->status]);

        return response()->json([
            'success' => true,
            'message' => 'স্ট্যাটাস আপডেট করা হয়েছে।'
        ]);
    }

    public function destroy($id)
    {
        ContactMessage::findOrFail($id)->delete();
        return response()->json(['success' => true, 'message' => 'মেসেজ ডিলিট করা হয়েছে।']);
    }
}
