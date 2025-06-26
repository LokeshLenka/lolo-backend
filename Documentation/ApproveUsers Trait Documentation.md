# ApproveUsers Trait Documentation

## Overview
The `ApproveUsers` trait provides a comprehensive user approval system with multi-level authorization. It supports three approval levels: **EBM (Executive Body Member)**, **Membership Head**, and **Admin**, each with different permissions and workflow requirements.

## 🔄 Approval Workflow

```
User Registration → EBM Approval → Membership Head Approval → User Activated
                                ↘
                              Admin Direct Approval → User Activated
```

## 📋 Core Methods

### Approval Methods

#### `approveByEBM(User $user, string $policyAbility, string $remarks)`
**Purpose**: First-level approval by Executive Body Member

**Features**:
- ✅ Validates EBM assignment
- ✅ Prevents duplicate approvals
- ✅ Updates approval status to `EBM_APPROVED`
- ⚠️ Does NOT generate username (waits for membership/admin approval)

**Validations**:
- User must have approval record
- EBM must not have already approved
- Current user must be assigned as EBM

---

#### `approveByMemberShipHead(User $user, string $policyAbility, string $remarks)`
**Purpose**: Final approval by Membership Head (after EBM approval)

**Features**:
- ✅ Generates username if not exists
- ✅ Sets user as approved (`is_approved = true`)
- ✅ Updates status to `MEMBERSHIP_APPROVED`
- ✅ Sets final approval timestamp

**Validations**:
- User must have approval record
- User must not already be approved
- Must not be already approved by Membership Head
- EBM approval must be completed first
- Current user must be assigned as Membership Head

---

#### `approveByAdmin(User $user, string $policyAbility, string $remarks)`
**Purpose**: Direct admin approval (bypasses EBM/Membership workflow)

**Features**:
- ✅ Generates username if not exists
- ✅ Sets user as approved (`is_approved = true`)
- ✅ Updates status to `ADMIN_APPROVED`
- ✅ Bypasses multi-level approval process

**Validations**:
- User must have approval record
- User must not already be approved

---

### Rejection Methods

#### `rejectByEBM(User $user, string $policyAbility, string $remarks)`
**Purpose**: Reject user application at EBM level

**Restrictions**:
- ❌ Cannot reject already approved users
- ❌ Cannot reject users approved by higher authority
- ✅ Must be assigned as EBM for the user

---

#### `rejectByMemberShipHead(User $user, string $policyAbility, string $remarks)`
**Purpose**: Reject user application at Membership Head level

**Restrictions**:
- ❌ Cannot reject already rejected users
- ✅ Must be assigned as Membership Head for the user

---

#### `rejectByAdmin(User $user, string $policyAbility, string $remarks)`
**Purpose**: Admin-level rejection (highest authority)

**Features**:
- ✅ Can reject users at any stage
- ✅ Highest level of rejection authority

---

## 🛠 Utility Methods

### `generateUsername()`
**Purpose**: Generates unique sequential usernames

**Format**: `{YY}0707{NNNN}`
- `YY`: Current year (2-digit)
- `0707`: Fixed middle part
- `NNNN`: 4-digit sequential number

**Example**: `240707001`, `240707002`, `240707003`

**Logic**:
1. Gets current year in 2-digit format
2. Finds the last username with current year pattern
3. Increments sequence number
4. Pads with zeros to ensure 4-digit sequence

---

### `reject(User $user, UserApproval $approval, string $role, string $remarks)` 
**Purpose**: Private method handling rejection logic

**Features**:
- 🔄 Database transaction for data integrity
- 📝 Appends rejection remarks with role and timestamp
- ⚠️ Sets `is_approved = false`
- 📅 Updates approval timestamp

---

## 📊 Status Flow

| Status | Description | Next Possible States |
|--------|-------------|---------------------|
| `PENDING` | Initial state | `EBM_APPROVED`, `ADMIN_APPROVED`, `REJECTED` |
| `EBM_APPROVED` | EBM has approved | `MEMBERSHIP_APPROVED`, `REJECTED` |
| `MEMBERSHIP_APPROVED` | Final approval | `REJECTED` (by admin only) |
| `ADMIN_APPROVED` | Direct admin approval | `REJECTED` (by admin only) |
| `REJECTED` | Application rejected | No further changes |

## 🔐 Security Features

- **Gate Authorization**: Every method uses Laravel's Gate system
- **Assignment Validation**: Users can only be approved by assigned approvers
- **Transaction Safety**: All operations wrapped in database transactions
- **Audit Trail**: Comprehensive remarks logging with timestamps and user info

## 💡 Usage Examples

### Basic EBM Approval
```php
$this->approveByEBM(
    $user, 
    'approve-user', 
    'Application looks good, approved for next level'
);
```

### Admin Direct Approval
```php
$this->approveByAdmin(
    $user, 
    'admin-approve-user', 
    'Fast-track approval for urgent case'
);
```

### Rejection with Reason
```php
$this->rejectByEBM(
    $user, 
    'reject-user', 
    'Incomplete documentation provided'
);
```

## ⚠️ Important Notes

1. **Username Generation**: Only happens during final approval (Membership Head or Admin)
2. **Remarks History**: All remarks are appended, creating a complete audit trail
3. **Transaction Safety**: All operations are wrapped in database transactions
4. **Assignment Validation**: Critical for maintaining proper approval workflow
5. **Status Checks**: Prevent duplicate approvals and maintain workflow integrity

## 🎯 Best Practices

- Always provide meaningful remarks for audit purposes
- Ensure proper policy abilities are defined in your Gate policies
- Handle exceptions appropriately in your controllers
- Validate user assignment before calling approval methods
- Use database transactions when calling multiple approval methods
