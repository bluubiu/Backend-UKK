<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\SoftDeletes;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'username',
        'password',
        'full_name',
        'email',
        'phone',
        'role_id',
        'is_active',
        'score',
        'profile_photo_path',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * Update user score with checks (Min 0, Max 120)
     */
    public function updateScore($change)
    {
        $newScore = $this->score + $change;
        
        if ($newScore > 120) {
            $newScore = 120;
        } elseif ($newScore < 0) {
            $newScore = 0;
        }

        $this->score = $newScore;
        $this->save();

        return $newScore;
    }

    public function scoreLogs()
    {
        return $this->hasMany(ScoreLog::class);
    }

    public function loans()
    {
        return $this->hasMany(Loan::class);
    }

    public function hasRole($roles)
    {
        if (!$this->role) return false;
        
        if (is_array($roles)) {
            return in_array($this->role->name, $roles);
        }
        return $this->role->name === $roles;
    }
}
