<?php

use Illuminate\Support\Facades\Broadcast;

// সাধারণ ইউজারের চ্যানেল (এটি হয়তো আগেই ছিল)
Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// ২. 🔴 অ্যাডমিনদের গ্লোবাল প্রাইভেট চ্যানেল (যেখানে নতুন রোলগুলোর পারমিশন দেওয়া হলো)
Broadcast::channel('admin-notifications', function ($user) {
    $adminRoles = ['super_admin', 'admin', 'moderator'];
    return in_array($user->role, $adminRoles);
});
