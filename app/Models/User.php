<?php

namespace App\Models;

use App\Enums\UserTypeEnum;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Support\Facades\Storage;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class User extends Authenticatable
{
    use HasApiTokens;
    use Notifiable;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'name',
        'email',
        'phone',
        'password',
        'profile_photo',
        'user_type',
        'status',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'user_type' => UserTypeEnum::class,
            'status' => 'boolean',
        ];
    }

    public function getProfilePhotoUrlAttribute(): string
    {
        return ! empty($this->profile_photo)
            ? Storage::url($this->profile_photo)
            : asset('images/user-icon.png');
    }

    public function setProfilePhotoAttribute($value): void
    {
        if (! empty($value) && $value instanceof UploadedFile) {
            $this->attributes['profile_photo'] = $value->store('company_user/photo');
        }
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }
}
