<?php

declare(strict_types=1);

namespace App\Monitoring\Http\Requests;

use App\Monitoring\Enums\StatsWindow;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StatsRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'window' => ['nullable', 'string', Rule::enum(StatsWindow::class)],
        ];
    }

    public function window(): StatsWindow
    {
        $window = $this->validated('window');

        return is_string($window) ? StatsWindow::from($window) : StatsWindow::Day;
    }
}
