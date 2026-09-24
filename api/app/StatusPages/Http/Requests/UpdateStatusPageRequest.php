<?php

declare(strict_types=1);

namespace App\StatusPages\Http\Requests;

use App\Monitoring\Contracts\MonitorDirectory;
use App\StatusPages\Settings\StatusPagesSettings;
use App\StatusPages\Values\PageDraft;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateStatusPageRequest extends FormRequest
{
    public function rules(StatusPagesSettings $settings, MonitorDirectory $monitors): array
    {
        return (new StatusPageFields($settings, $monitors))->rules($this, $this->routeText('organization'), $this->routeText('statusPage'), partial: true);
    }

    public function draft(StatusPagesSettings $settings, MonitorDirectory $monitors): PageDraft
    {
        return (new StatusPageFields($settings, $monitors))->draft($this);
    }

    private function routeText(string $parameter): string
    {
        $value = $this->route($parameter);

        return is_string($value) ? $value : '';
    }
}
