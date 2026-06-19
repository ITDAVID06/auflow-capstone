<?php

namespace App\Modules\UserManagement\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\UserManagement\Requests\UpdateProfilePictureRequest;

class ProfileController extends Controller
{
    public function updateProfilePicture(UpdateProfilePictureRequest $request)
    {
        $user = auth()->user();

        // store file on the private profile-pictures disk
        $path = $request->file('profile_picture')->store('', 'profile-pictures');

        // update DB
        $profile = $user->profile; // relation tbl_user → tbl_userprofile
        $profile->profile_picture = $path;
        $profile->save();

        return back()->with('success', 'Profile picture updated!');
    }
}
