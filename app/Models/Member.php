<?php

namespace App\Models;

use App\Traits\HasTags;
use App\Traits\HasNotes;
use App\Enums\GenderEnum;
use App\Traits\Auditable;
use App\Traits\MultiTenant;
use App\Traits\BranchTenant;
use App\Traits\HasActivities;
use App\Enums\MemberStatusEnum;
use Illuminate\Http\UploadedFile;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * A gym member: the fitness-specific half of a `Party` of type customer.
 *
 * Identity, contact details and everything financial stay on the party, so a
 * member is invoiced, receipted, aged and tagged by the same code as any other
 * customer. Read `$member->party` for name/phone/email.
 */
class Member extends Model
{
    /** @use HasFactory<\Database\Factories\MemberFactory> */
    use Auditable;
    use BranchTenant;
    use HasActivities;
    use HasFactory;
    use HasNotes;
    use HasTags;
    use MultiTenant;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'branch_id',
        'party_id',
        'member_code',
        'photo',
        'date_of_birth',
        'gender',
        'blood_group',
        'occupation',
        'emergency_contact_name',
        'emergency_contact_phone',
        'height_cm',
        'weight_kg',
        'medical_notes',
        'joined_on',
        'status',
        'source',
        'referred_by_member_id',
        'assigned_trainer_id',
        'created_by_id',
    ];

    protected function casts(): array
    {
        return [
            // 'date:Y-m-d' rather than 'date' — SQLite stores a plain 'date'
            // cast with a time component, which breaks equality comparisons.
            'date_of_birth' => 'date:Y-m-d',
            'joined_on' => 'date:Y-m-d',
            'gender' => GenderEnum::class,
            'status' => MemberStatusEnum::class,
            'height_cm' => 'float',
            'weight_kg' => 'float',
        ];
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    public function referredBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'referred_by_member_id');
    }

    public function referrals(): HasMany
    {
        return $this->hasMany(self::class, 'referred_by_member_id');
    }

    public function trainer(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'assigned_trainer_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    /**
     * Every term this member has ever held, newest first — the membership
     * history shown on their profile.
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class)->orderByDesc('start_date')->orderByDesc('id');
    }

    /**
     * The term that occupies the member's current slot, if any.
     */
    public function checkIns(): HasMany
    {
        return $this->hasMany(MemberCheckIn::class)->orderByDesc('checked_in_at');
    }

    public function currentMembership(): HasOne
    {
        return $this->hasOne(Membership::class)
            ->whereIn('status', \App\Enums\MembershipStatusEnum::occupyingValues())
            ->latestOfMany('start_date');
    }

    /**
     * Same mutator pattern as the company logo: assigning an upload stores the
     * file and replaces whatever was there before.
     */
    public function setPhotoAttribute($value): void
    {
        if (! empty($value) && $value instanceof UploadedFile) {
            if ($this->photo && Storage::disk('public')->exists($this->photo)) {
                Storage::disk('public')->delete($this->photo);
            }

            $this->attributes['photo'] = $value->store('gym/members', 'public');

            return;
        }

        if (is_string($value) || $value === null) {
            $this->attributes['photo'] = $value;
        }
    }

    public function getPhotoUrlAttribute(): string
    {
        return $this->photo ? Storage::disk('public')->url($this->photo) : '';
    }

    public function scopeFilter($query, array $param = [])
    {
        if (! empty($param['search'])) {
            $key = '%'.trim($param['search']).'%';
            $query->where(function ($q) use ($key) {
                $q->where('member_code', 'like', $key)
                    ->orWhereHas('party', function ($partyQuery) use ($key) {
                        $partyQuery->where('name', 'like', $key)
                            ->orWhere('phone', 'like', $key)
                            ->orWhere('email', 'like', $key);
                    });
            });
        }

        if (! empty($param['status'])) {
            $query->where('status', $param['status']);
        }

        if (! empty($param['gender'])) {
            $query->where('gender', $param['gender']);
        }

        if (! empty($param['trainer_id'])) {
            $query->where('assigned_trainer_id', $param['trainer_id']);
        }

        return $query;
    }
}
