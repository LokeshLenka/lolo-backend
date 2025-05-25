<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use DateTime;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Ramsey\Uuid\Type\Integer;
use Illuminate\Support\Arr;
use Laravel\Sanctum\HasApiTokens;



// create models [ managementProfile,memberProfile,userApproval,loginAttempts]

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'email',
        'password',
        'role',
        'is_approved',
        // 'last_login_at',
        // 'last_login_ip',
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
            'password' => 'hashed',
            'is_approved' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime'
        ];
    }

    // Role constants
    const ROLE_ADMIN = 'admin';     // Admin
    const ROLE_MEMBER = 'member';   // Musical member
    const ROLE_EBM = 'ebm';         // Executive Board Member
    const ROLE_MCH = 'mch';         // Management Committee Head
    const ROLE_EP = 'ep';           // Event Planner
    const ROLE_EO = 'eo';           // Event Organizer
    const ROLE_CM = 'cm';           // Credit Manager


    public static function getRoles(): array
    {
        return [
            self::ROLE_ADMIN,
            self::ROLE_MEMBER,
            self::ROLE_EBM,
            self::ROLE_MCH,
            self::ROLE_EP,
            self::ROLE_EO,
            self::ROLE_CM,
        ];
    }

    protected static function getRolesWithoutAdmin()
    {
        $roles = User::getRoles();

        if (Arr::exists($roles, self::ROLE_ADMIN)) {
            $filteredRoles = Arr::expect(self::ROLE_ADMIN);
        }

        return !empty($filteredRoles) ? $filteredRoles : ['Error Occured'];
    }


    protected static array $blogManagers = [
        self::ROLE_EBM,
        self::ROLE_EO,
        self::ROLE_EP,
        self::ROLE_MEMBER,
    ];

    // Role hierarchy for permissions
    // public static function getRoleHierarchy(): array
    // {
    //     return [
    //         self::ROLE_ADMIN => 100,
    //         self::ROLE_MCH => 80,
    //         self::ROLE_EBM => 70,
    //         self::ROLE_CM => 60,
    //         self::ROLE_EP => 50,
    //         self::ROLE_EO => 40,
    //         self::ROLE_MEMBER => 10,
    //     ];
    // }

    public function getUserName(): string
    {
        return !empty($this->username) ? $this->username : 'UserName Not Found';
    }

    public function getUserEmail(): string
    {
        return !empty($this->email) ? $this->email : 'Email Not Found';
    }

    public function getUserRole(): string
    {
        return !empty($this->role) ? $this->role : 'Role Not Found';
    }

    public function isApproved(): bool
    {
        return $this->is_approved;
    }

    public function getCreatedAt(): string
    {
        return !empty($this->created_at)
            ? $this->created_at->format('d M Y, h:i A')
            : 'Not Available';
    }

    public function getUpdatedAt(): string
    {
        return !empty($this->updated_at)
            ? $this->updated_at->format('d M Y, h:i A')
            : 'Not Available';
    }

    public function getDeletedAt(): string
    {
        return !empty($this->deleted_at)
            ? $this->deleted_at->format('d M Y, h:i A')
            : 'Not Available';
    }

    public function getLastLoginAt(): string
    {
        return !empty($this->created_at)
            ? $this->created_at->format('d M Y, h:i A')
            : 'Not Available';
    }

    public function trashed(): bool
    {
        return $this->trashed();
    }

    public function getLastLoginIp(): string
    {
        return !empty($this->last_login_ip) ? $this->last_login_ip : "IP Not Found";
    }

    public function incrementFailedAttempts(): void
    {
        $this->increment('failed_login_attempts');

        if ($this->failed_login_attempts >= 5) {
            $this->update(['locked_until' => now()->addMinutes(30)]);
        }
    }

    public function resetFailedAttempts(): void
    {
        $this->update([
            'failed_login_attempts' => 0,
            'locked_until' => null,
        ]);
    }

    public function isAdmin(): bool
    {
        return $this->hasRole(self::ROLE_ADMIN);
    }

    public function isMembershipCommitteHead(): bool
    {
        return $this->hasRole(self::ROLE_MCH);
    }

    public function isExecutiveBodyMember(): bool
    {
        return $this->hasRole(self::ROLE_EBM);
    }

    public function isCreditManager(): bool
    {
        return $this->hasRole(self::ROLE_CM);
    }

    public function isEventManager(): bool
    {
        return $this->hasRole(self::ROLE_EO);
    }

    public function isEventPlanner(): bool
    {
        return $this->hasRole(self::ROLE_EP);
    }

    public function isMember(): bool
    {
        return $this->hasRole(self::ROLE_MEMBER);
    }

    public function hasRole(string $role): bool
    {
        return $this->role === $role;
    }

    public function hasAnyRole(array $roles): bool
    {
        return in_array($this->role, $roles);
    }

    // public function hasRoleLevel(int $level): bool
    // {
    //     $hierarchy = self::getRoleHierarchy();
    //     return ($hierarchy[$this->role] ?? 0) >= $level;
    // }

    public function canApproveUsers(): bool
    {
        return $this->hasAnyRole([self::ROLE_ADMIN, self::ROLE_MCH]);
    }

    public function canManageEvents(): bool
    {
        return $this->hasAnyRole([self::ROLE_ADMIN, self::ROLE_MCH, self::ROLE_EBM]);
    }

    public function canManageCredits(): bool
    {
        return $this->hasAnyRole([self::ROLE_ADMIN, self::ROLE_CM]);
    }

    public function canManageBlogs(): bool
    {
        return $this->hasAnyRole(self::$blogManagers);
    }

    public function canManageTeam(): bool
    {
        return $this->isAdmin();
    }


    /*
    public function isAccountLocked(): bool
    {
        return $this->locked_until && $this->locked_until->isFuture();
    }
    */


    // Relationships
    public function managementProfile(): HasOne
    {
        return $this->hasOne(ManagementProfile::class);
    }

    public function memberProfile(): HasOne
    {
        return $this->hasOne(MemberProfile::class);
    }

    public function userApproval(): HasOne
    {
        return $this->hasOne(UserApproval::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    public function blogs(): HasMany
    {
        return $this->hasMany(Blog::class);
    }

    public function loginAttempts(): HasMany
    {
        return $this->hasMany(LoginAttempt::class, 'email', 'email');
    }

    public function teamProfile(): Hasone
    {
        return $this->hasOne(TeamProfile::class);
    }

}
