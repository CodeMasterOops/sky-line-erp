<?php

use App\Models\User;

function userWithPhoto(?string $profilePhoto): User
{
    $user = new User;

    // The profile_photo mutator only accepts UploadedFile, so seed the raw
    // stored path directly to exercise the accessor.
    $user->setRawAttributes(['profile_photo' => $profilePhoto]);

    return $user;
}

it('returns an absolute url for a stored profile photo', function () {
    $user = userWithPhoto('company_user/photo/example.png');

    expect($user->profile_photo_url)
        ->toStartWith('http')
        ->toBe(url('/storage/company_user/photo/example.png'));
});

it('falls back to the default user icon when no photo is set', function () {
    $user = userWithPhoto(null);

    expect($user->profile_photo_url)->toBe(asset('images/user-icon.png'));
});
