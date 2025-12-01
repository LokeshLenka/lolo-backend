<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use App\Enums\PromotedRole;
use App\Enums\ManagementCategories;
use App\Enums\MusicCategories;
use App\Enums\UserRoles;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
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
        'uuid',
        'username',
        'email',
        'password',
        'role',
        'is_active',
        'created_by',
        'management_level',
        'promoted_role',
        'promoted_by',
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
        'id',
        'created_by',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'role' => UserRoles::class,
        'management_level' => 'string',
        'promoted_role' => PromotedRole::class,
        'is_approved' => 'boolean',
        'locked_until' => 'datetime',

    ];

    // // === Abilities Map ===
    // private static array $ROLE_ABILITIES = [
    //     UserRoles::ROLE_ADMIN->value => ['*'],
    //     'membership_head' => ['users:approve', 'users:manage', 'blogs:create', 'blogs:update', 'teams:manage'],
    //     'executive_body_member' => ['events:manage', 'events:create', 'blogs:create', 'blogs:update', 'event_registrations:view'],
    //     'credit_manager' => ['credits:manage', 'credits:create', 'credits:update', 'event_registrations:view'],
    //     'event_planner' => ['blogs:create', 'blogs:update'],
    //     'event_organizer' => ['blogs:create', 'blogs:update'],
    //     'music' => ['events:register'],
    //     'promotor' => ['events:register']
    // ];


    private static array $ROLE_ABILITIES = [
        // Top-level Roles
        UserRoles::ROLE_ADMIN->value => ['*'],

        // Promoted Roles
        PromotedRole::EXECUTIVE_BODY_MEMBER->value => [
            'events:create',
            'blogs:create',
            'blogs:update',
            'event_registrations:viewAny'
        ],
        PromotedRole::CREDIT_MANAGER->value => [
            'credits:manage',
            'credits:create',
            'credits:update',
            'event_registrations:viewAny'
        ],
        PromotedRole::MEMBERSHIP_HEAD->value => [
            'users:approve',
            'users:manage',
            'blogs:create',
            'blogs:update',
            'teams:manage'
        ],

        // Management Sub-Roles
        ManagementCategories::MARKETING_COORDINATOR->value => [
            'events:register',
            'blogs:create',
            'blogs:update',
        ],
        ManagementCategories::EVENT_PLANNER->value => [
            'events:register',
            'blogs:create',
            'blogs:update',
        ],
        ManagementCategories::EVENT_ORGANIZER->value => [
            'events:register',
            'blogs:create',
            'blogs:update',
        ],
        ManagementCategories::SOCIAL_MEDIA_HANDLER->value => [
            'events:register',
            'blogs:create',
            'blogs:update',
        ],
        ManagementCategories::VIDEO_EDITOR->value => [
            'events:register',
            'blogs:create',
            'blogs:update',
        ],

        // Music Sub-Roles
        MusicCategories::DRUMMER->value => [
            'events:register',
            'blogs:create',
            'blogs:update',
        ],
        MusicCategories::VOCALIST->value => [
            'events:register',
            'blogs:create',
            'blogs:update',
        ],
        MusicCategories::FLUTIST->value => [
            'events:register',
            'blogs:create',
            'blogs:update',
        ],
        MusicCategories::GUITARIST->value => [
            'events:register',
            'blogs:create',
            'blogs:update',
        ],
        MusicCategories::PIANIST->value => [
            'events:register',
            'blogs:create',
            'blogs:update',
        ],
        MusicCategories::VIOLINIST->value => [
            'events:register',
            'blogs:create',
            'blogs:update',
        ],
    ];

    public static function getRoles(): array
    {
        return UserRoles::values();
    }

    public static function getRolesWithoutAdmin(): array
    {
        return array_filter(
            UserRoles::cases(),
            fn(UserRoles $role) => ($role !== UserRoles::ROLE_ADMIN && $role != UserRoles::ROLE_PUBLIC)
        );
    }


    protected static array $blogManagers = [
        UserRoles::ROLE_MANAGEMENT,
        UserRoles::ROLE_MUSIC,
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


    /**
     * Getters functions
     */
    public function getUserName(): string
    {
        return $this->username ?: 'UserName Not Found';
    }

    public function getUserEmail(): string
    {
        return $this->email ?: 'Email Not Found';
    }

    public function getUserRole(): string
    {
        return $this->role->value ?: 'Role Not Found';
    }

    public function isApproved(): bool
    {
        return $this->is_approved;
    }

    public function isActive(): bool
    {
        return $this->is_active;
    }

    public  function hasPromoted(): bool
    {
        return $this->hasPromotedAnyRole(
            [
                PromotedRole::CREDIT_MANAGER->value,
                PromotedRole::EXECUTIVE_BODY_MEMBER->value,
                PromotedRole::MEMBERSHIP_HEAD->value
            ]
        );
    }

    public function getManagementLevel(): string
    {
        return $this->management_level ?: 'base';
    }

    public function getLastLoginAt(): string
    {
        return optional($this->last_login_at)->format('d M Y, h:i A') ?: 'Not Available';
    }

    public function getLastLoginIp(): string
    {
        return $this->last_login_ip ?: 'IP Not Found';
    }

    public function trashed(): bool
    {
        return !is_null($this->deleted_at);
    }

    public function incrementFailedAttempts(): void
    {
        $this->increment('failed_login_attempts');
        if ($this->failed_login_attempts >= 5) {
            $this->update(['locked_until' => Carbon::now()->addMinutes(10)]);
        }
    }

    public function resetFailedAttempts(): void
    {
        $this->update(['failed_login_attempts' => 0, 'locked_until' => null]);
    }

    public function isAccountLocked(): bool
    {
        return $this->locked_until && $this->locked_until->isFuture();
    }

    public function getCreatedAt(): string
    {
        return optional($this->created_at)->format('d M Y, h:i A') ?: 'Not Available';
    }

    public function getUpdatedAt(): string
    {
        return optional($this->updated_at)->format('d M Y, h:i A') ?: 'Not Available';
    }

    public function getDeletedAt(): string
    {
        return optional($this->deleted_at)->format('d M Y, h:i A') ?: 'Not Available';
    }

    // public function hasRoleLevel(int $level): bool
    // {
    //     $hierarchy = self::getRoleHierarchy();
    //     return ($hierarchy[$this->role] ?? 0) >= $level;
    // }

    // write seperate traits for all these functions



    // === User Role ===

    public function hasRole(UserRoles $role): bool
    {
        return $this->role === $role;
    }

    public function hasAnyRole(array $roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    // === Promoted Role ===

    public function hasPromotedRole(PromotedRole $role): bool
    {
        // Assuming $this->promoted_role is enum (if it's a string, use $role->value)
        return $this->promoted_role === $role;
    }

    public function hasPromotedAnyRole(array $roles): bool
    {
        // Ensure strict comparison of enum instances
        return in_array($this->promoted_role, $roles, true);
    }


    // highlevel users


    public function isMembershipHead(): bool
    {
        return $this->hasPromotedRole(PromotedRole::MEMBERSHIP_HEAD) && $this->isActive();
    }

    public function isExecutiveBodyMember(): bool
    {
        return $this->hasPromotedRole(PromotedRole::EXECUTIVE_BODY_MEMBER) && $this->isActive();
    }

    public function isCreditManager(): bool
    {
        return $this->hasPromotedRole(PromotedRole::CREDIT_MANAGER) && $this->isActive();
    }


    // === Management Role checker

    public function getManagementCategory(): ?ManagementCategories
    {
        return $this->managementProfile?->sub_role ?? null;
    }

    public function hasManagementCategory(ManagementCategories $category): bool
    {
        return $this->getManagementCategory() === $category;
    }

    public function hasAnyManagementCategory(array $categories): bool
    {
        return in_array($this->getManagementCategory(), $categories, true);
    }

    /**
     * management categotries functions
     */
    public function isEventManager(): bool
    {
        return $this->hasManagementCategory(ManagementCategories::EVENT_ORGANIZER) && $this->isActive();
    }

    public function isEventPlanner(): bool
    {
        return $this->hasManagementCategory(ManagementCategories::EVENT_PLANNER) && $this->isActive();
    }

    public function isMarketingCoordinator(): bool
    {
        return $this->hasManagementCategory(ManagementCategories::MARKETING_COORDINATOR) && $this->isActive();
    }

    public function isSocialMediaHandler(): bool
    {
        return $this->hasManagementCategory(ManagementCategories::SOCIAL_MEDIA_HANDLER) && $this->isActive();
    }

    public function isVideoEditor(): bool
    {
        return $this->hasManagementCategory(ManagementCategories::VIDEO_EDITOR) && $this->isActive();
    }

    // === Music Role checker

    public function getMusicCategory(): ?MusicCategories
    {
        return $this->musicProfile?->sub_role ?? null;
    }

    public function hasMusicCategory(MusicCategories $category): bool
    {
        return $this->getMusicCategory() === $category;
    }

    public function hasAnyMusicCategory(array $categories): bool
    {
        return in_array($this->getMusicCategory(), $categories, true);
    }

    /**
     * Music categories functions
     */
    public function isDrummer(): bool
    {
        return $this->hasMusicCategory(MusicCategories::DRUMMER) && $this->isActive();
    }

    public function isFlutist(): bool
    {
        return $this->hasMusicCategory(MusicCategories::FLUTIST) && $this->isActive();
    }

    public function isGuitarist(): bool
    {
        return $this->hasMusicCategory(MusicCategories::GUITARIST) && $this->isActive();
    }

    public function isPianist(): bool
    {
        return $this->hasMusicCategory(MusicCategories::PIANIST) && $this->isActive();
    }

    public function isViolinist(): bool
    {
        return $this->hasMusicCategory(MusicCategories::VIOLINIST) && $this->isActive();
    }

    public function isVocalist(): bool
    {
        return $this->hasMusicCategory(MusicCategories::VOCALIST) && $this->isActive();
    }


    /**
     * check the user role
     */

    public function isAdmin(): bool
    {
        return $this->hasRole(UserRoles::ROLE_ADMIN);
    }

    public function isMusicMember(): bool
    {
        return $this->hasRole(UserRoles::ROLE_MUSIC) && $this->isApproved() && $this->isActive();
    }

    public function isClubMember(): bool
    {
        return $this->hasRole(UserRoles::ROLE_MANAGEMENT) && $this->isApproved() && $this->isActive();
    }

    public function isPublicUser(): bool
    {
        return $this->hasRole(UserRoles::ROLE_PUBLIC);
    }


    /**
     * rights
     */

    public function canApproveUsers(): bool
    {
        return $this->isAdmin() || $this->isMembershipHead() || $this->isExecutiveBodyMember();
    }

    public function canManageEvents(): bool
    {
        return $this->isAdmin();
    }

    public function canCreateEvents(): bool
    {
        return $this->isAdmin() || $this->isExecutiveBodyMember();
    }


    public function canManageCredits(): bool
    {
        return $this->isAdmin() || $this->isCreditManager();
    }

    public function canManageBlogs(): bool
    {
        return $this->isMusicMember() || $this->isClubMember();
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

    public function musicProfile(): HasOne
    {
        return $this->hasOne(MusicProfile::class);
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

    // In User.php model
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by'); // 'created_by' is the foreign key
    }

    // You might also want the inverse relationship
    public function createdUsers(): HasMany
    {
        return $this->hasMany(User::class, 'created_by');
    }


    /**
     * user abilities
     */

    public static function getUserAbilities(User $user): array
    {
        $abilities = [];

        // Top-level role (enum to string)
        if ($user->role instanceof \App\Enums\UserRoles) {
            $abilities = array_merge(
                $abilities,
                self::$ROLE_ABILITIES[$user->role->value] ?? []
            );
        }

        // Promoted role
        if ($user->promoted_role instanceof \App\Enums\PromotedRole) {
            $abilities = array_merge(
                $abilities,
                self::$ROLE_ABILITIES[$user->promoted_role->value] ?? []
            );
        }

        // Management category
        if ($user->managementProfile?->sub_role instanceof \App\Enums\ManagementCategories) {
            $abilities = array_merge(
                $abilities,
                self::$ROLE_ABILITIES[$user->managementProfile->sub_role->value] ?? []
            );
        }

        // Music category
        if ($user->musicProfile?->sub_role instanceof \App\Enums\MusicCategories) {
            $abilities = array_merge(
                $abilities,
                self::$ROLE_ABILITIES[$user->musicProfile->sub_role->value] ?? []
            );
        }

        return array_values(array_unique($abilities));
    }



    /**
     * eligibility
     */
    public function isEligibleParticipant(): bool
    {
        return $this->isMusicMember() || $this->isClubMember();
    }

    public function isEligibleToEarnCredits(): bool
    {
        return $this->isEligibleParticipant();
    }

    public function isEligibleForEventRegistration(): bool
    {
        return $this->isEligibleParticipant();
    }

    public function isEligibleEventCoordinator(): bool
    {
        return
            $this->hasAnyManagementCategory([
                ManagementCategories::EVENT_ORGANIZER,
                ManagementCategories::EVENT_PLANNER,
            ]) ||
            $this->hasPromotedRole(PromotedRole::EXECUTIVE_BODY_MEMBER) && $this->isActive();
    }

    public function isEligibleToPromoteUsers()
    {
        return $this->isAdmin() || $this->isMembershipHead();
    }

    public function canViewRegistrations()
    {
        return $this->isAdmin() || $this->isExecutiveBodyMember() || $this->isCreditManager();
    }

    public function getRouteKeyName()
    {
        return 'uuid';
    }

    public function coordinatorName()
    {
        return ($this->musicProfile->first_name . ' ' . $this->musicProfile->last_name)
            ?? ($this->managementProfile->first_name . ' ' . $this->managementProfile->last_name)
            ?? null;
    }
}
