<?php

namespace App\Models;

use App\Enums\DateModeEnum;
use App\Enums\UserTypeEnum;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
        'date_mode',
        'dashboard_pinned_links',
        'status',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $attributes = [
        'date_mode' => DateModeEnum::Bs->value,
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'user_type' => UserTypeEnum::class,
            'date_mode' => DateModeEnum::class,
            'dashboard_pinned_links' => 'array',
            'status' => 'boolean',
        ];
    }

    public function getProfilePhotoUrlAttribute(): string
    {
        return ! empty($this->profile_photo)
            ? Storage::disk('public')->url($this->profile_photo)
            : asset('images/user-icon.png');
    }

    public function setProfilePhotoAttribute($value): void
    {
        if (! empty($value) && $value instanceof UploadedFile) {
            $this->attributes['profile_photo'] = $value->store('company_user/photo', 'public');
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

    public function branchAssignments(): HasMany
    {
        return $this->hasMany(BranchUser::class);
    }

    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class, 'branch_users')
            ->withPivot(['role_id', 'is_active'])
            ->wherePivot('is_active', true)
            ->withTimestamps();
    }

    public function isAdmin(): bool
    {
        return $this->user_type === UserTypeEnum::ADMIN;
    }

    public function canAccessBranch(int $branchId): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        return $this->branches()->where('branches.id', $branchId)->exists();
    }

    public function accessibleBranchIds(): Collection
    {
        if ($this->isAdmin()) {
            return Branch::query()
                ->where('company_id', $this->company_id)
                ->pluck('id');
        }

        return $this->branches()->pluck('branches.id');
    }

    public function branchRole(int $branchId): ?Role
    {
        $assignment = $this->branchAssignments()
            ->where('branch_id', $branchId)
            ->where('is_active', true)
            ->first();

        return $assignment?->role_id
            ? $assignment->role
            : $this->roles()->first();
    }
}
