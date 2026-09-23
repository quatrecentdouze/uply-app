<?php

declare(strict_types=1);

namespace App\StatusPages\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['organization_id', 'slug', 'title', 'description', 'is_published'])]
final class StatusPage extends Model
{
    use HasFactory;
    use HasUlids;

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
