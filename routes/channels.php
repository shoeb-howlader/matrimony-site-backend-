<?php

use Illuminate\Support\Facades\Broadcast;

// সাধারণ ইউজারের চ্যানেল (এটি হয়তো আগেই ছিল)
Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// 🔴 অ্যাডমিনদের জন্য নতুন চ্যানেল অথরাইজেশন
Broadcast::channel('admin-notifications', function ($user) {
    // শুধু অ্যাডমিনরাই এই চ্যানেলে জয়েন করতে পারবে
    return $user->role === 'admin';
});
