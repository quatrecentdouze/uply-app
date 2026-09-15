<?php

declare(strict_types=1);

namespace App\Organizations\Models;

use App\Organizations\Contracts\Role;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable(['organization_id', 'email', 'role', 'token_hash', 'invited_by_user_id', 'expires_at', 'accepted_at'])]
#[Hidden(['token_hash'])]
final class Invitation extends Model
{
    use HasFactory;
    use HasUlids;

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by_user_id');
    }

    public function expiresAt(): CarbonImmutable
    {
        return $this->expires_at ?? throw new LogicException('an invitation always has an expiry');
    }

    public function isExpiredAt(CarbonImmutable $now): bool
    {
        return $this->expiresAt()->lessThanOrEqualTo($now);
    }

    protected function casts(): array
    {
        return [
            'role' => Role::class,
            'expires_at' => 'immutable_datetime',
            'accepted_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
