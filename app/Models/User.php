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
        'username',
        'email',
        'password',
        'role',
        'is_approved',
        'last_login_at',
        'last_login_ip',
        'failed_login_attempts',
        'locked_until',
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
            'locked_until' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime'
        ];
    }

    // Role constants
    const ROLE_ADMIN = 'admin';     // Admin
    const ROLE_MEMBER = 'member';   // Musical member
    const ROLE_EBM = 'ebm';         // Executive Board Member
    const ROLE_MH = 'mh';           // Membership pHead
    const ROLE_EP = 'ep';           // Event Planner
    const ROLE_EO = 'eo';           // Event Organizer
    const ROLE_CM = 'cm';           // Credit Manager

    private static array $ROLE_ABILITIES = [
        User::ROLE_ADMIN => ['*'],
        User::ROLE_MH => ['users:approve', 'users:manage', 'blogs:create', 'blogs:update', 'teams:manage'],
        User::ROLE_EBM => ['events:manage', 'events:create', 'blogs:create', 'blogs:update'],
        User::ROLE_CM => ['credits:manage', 'credits:create', 'credits:update'],
        User::ROLE_EP => ['blogs:create', 'blogs:update'],
        User::ROLE_EO => ['blogs:create', 'blogs:update'],
        User::ROLE_MEMBER => ['events:register'],
    ];

    public static function getRoles(): array
    {
        return [
            self::ROLE_ADMIN,
            self::ROLE_MEMBER,
            self::ROLE_EBM,
            self::ROLE_MH,
            self::ROLE_EP,
            self::ROLE_EO,
            self::ROLE_CM,
        ];
    }

    public static function getRolesWithoutAdmin()
    {
        return [
            self::ROLE_EBM,
            self::ROLE_MH,
            self::ROLE_EP,
            self::ROLE_EO,
            self::ROLE_CM,
            self::ROLE_MEMBER,
        ];
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
    //         self::ROLE_MH => 80,
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
        return ($this->is_deleted === null) ? false : true;
    }

    public function getLastLoginIp(): string
    {
        return !empty($this->last_login_ip) ? $this->last_login_ip : "IP Not Found";
    }

    public function incrementFailedAttempts(): void
    {
        $this->increment('failed_login_attempts');

        if ($this->failed_login_attempts >= 5) {
            $this->update(['locked_until' => now()->addMinutes(10)]);
        }
    }

    public function resetFailedAttempts(): void
    {
        $this->update([
            'failed_login_attempts' => 0,
            'locked_until' => null,
        ]);
    }

    public function isAccountLocked(): bool
    {
        return $this->locked_until && $this->locked_until->isFuture();
    }

    // public function hasRoleLevel(int $level): bool
    // {
    //     $hierarchy = self::getRoleHierarchy();
    //     return ($hierarchy[$this->role] ?? 0) >= $level;
    // }

    public function userExists(): bool
    {
        return $this->id > 0;
    }

    // write seperate traits for all these functions

    public function isAdmin(): bool
    {
        return $this->hasRole(self::ROLE_ADMIN);
    }

    public function isMembershipHead(): bool
    {
        return $this->hasRole(self::ROLE_MH);
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

    public function isClubMember(): bool
    {
        return $this->hasAnyRole([self::ROLE_EO, self::ROLE_EP, self::ROLE_EBM]);
    }


    public function hasRole(string $role): bool
    {
        return $this->role === $role;
    }

    public function hasAnyRole(array $roles): bool
    {
        return in_array($this->role, $roles);
    }

    public function canApproveUsers(): bool
    {
        return $this->hasAnyRole([self::ROLE_ADMIN, self::ROLE_MH]);
    }

    public function canManageEvents(): bool
    {
        return $this->hasAnyRole([self::ROLE_ADMIN, self::ROLE_EBM]);
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

    public function credits(): HasMany
    {
        return $this->hasMany(Credit::class);
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


    public static function getUserAbilities(User $user): array
    {
        return self::$ROLE_ABILITIES[$user->role] ?? ['read'];
    }

    public function hasEligibleCreditRole(): bool
    {
        return !$this->hasAnyRole([self::ROLE_ADMIN, self::ROLE_CM]);
    }

    public function EligibleForEventRegistrations()
    {
        return !$this->hasAnyRole([self::ROLE_ADMIN, self::ROLE_CM, self::ROLE_MH]);
    }

    // public function getRouteKeyName()
    // {
    //     return 'slug'; // instead of 'id'
    // }
}
