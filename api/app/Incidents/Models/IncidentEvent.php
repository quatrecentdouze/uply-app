<?php

declare(strict_types=1);

namespace App\Incidents\Models;

use App\Incidents\Enums\IncidentEventKind;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['incident_id', 'kind', 'occurred_at', 'data'])]
final class IncidentEvent extends Model
{
    use HasUlids;

    public $timestamps = false;

    public function text(string $key): ?string
    {
        $value = $this->detail($key);

        return is_string($value) ? $value : null;
    }

    public function number(string $key): ?int
    {
        $value = $this->detail($key);

        return is_int($value) ? $value : null;
    }

    private function detail(string $key): mixed
    {
        $data = $this->getAttribute('data');

        return is_array($data) ? ($data[$key] ?? null) : null;
    }

    protected function casts(): array
    {
        return [
            'kind' => IncidentEventKind::class,
            'occurred_at' => 'immutable_datetime',
            'data' => 'array',
        ];
    }
}
