<?php

declare(strict_types=1);

namespace App\StatusPages\Http\Requests;

use App\Monitoring\Contracts\MonitorDirectory;
use App\StatusPages\Settings\StatusPagesSettings;
use App\StatusPages\Values\PageDraft;
use Illuminate\Foundation\Http\FormRequest;

final class StoreStatusPageRequest extends FormRequest
{
    public function rules(StatusPagesSettings $settings, MonitorDirectory $monitors): array
    {
        $organization = $this->route('organization');

        return (new StatusPageFields($settings, $monitors))->rules($this, is_string($organization) ? $organization : '', null, partial: false);
    }

    public function draft(StatusPagesSettings $settings, MonitorDirectory $monitors): PageDraft
    {
        return (new StatusPageFields($settings, $monitors))->draft($this);
    }
}
