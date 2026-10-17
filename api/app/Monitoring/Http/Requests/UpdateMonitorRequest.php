<?php

declare(strict_types=1);

namespace App\Monitoring\Http\Requests;

use App\Monitoring\Enums\HttpMethod;
use App\Monitoring\Settings\MonitoringSettings;
use App\Monitoring\Values\AcceptedStatusCodes;
use App\Monitoring\Values\ConfirmationThresholds;
use App\Monitoring\Values\MonitorDraft;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateMonitorRequest extends FormRequest
{
    public function rules(MonitoringSettings $settings): array
    {
        return [
            ...(new MonitorFieldRules($settings))->rules(partial: true),
            'is_paused' => ['sometimes', 'boolean'],
        ];
    }

    public function draftOver(MonitorDraft $current): MonitorDraft
    {
        return new MonitorDraft(
            name: $this->has('name') ? $this->string('name')->toString() : $current->name,
            url: $this->has('url') ? $this->string('url')->toString() : $current->url,
            method: $this->has('method') ? HttpMethod::from($this->string('method')->toString()) : $current->method,
            intervalSeconds: $this->has('interval_seconds') ? $this->integer('interval_seconds') : $current->intervalSeconds,
            timeoutSeconds: $this->has('timeout_seconds') ? $this->integer('timeout_seconds') : $current->timeoutSeconds,
            acceptedStatusCodes: $this->has('accepted_status_codes') ? AcceptedStatusCodes::parse($this->array('accepted_status_codes')) : $current->acceptedStatusCodes,
            followRedirects: $this->has('follow_redirects') ? $this->boolean('follow_redirects') : $current->followRedirects,
            thresholds: new ConfirmationThresholds(
                $this->has('failure_threshold') ? $this->integer('failure_threshold') : $current->thresholds->failuresToConfirmDown,
                $this->has('recovery_threshold') ? $this->integer('recovery_threshold') : $current->thresholds->successesToConfirmUp,
            ),
            request: $current->request,
        );
    }

    public function paused(): ?bool
    {
        return $this->has('is_paused') ? $this->boolean('is_paused') : null;
    }
}
