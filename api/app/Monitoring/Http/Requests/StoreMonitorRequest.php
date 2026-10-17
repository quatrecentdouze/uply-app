<?php

declare(strict_types=1);

namespace App\Monitoring\Http\Requests;

use App\Monitoring\Enums\HttpMethod;
use App\Monitoring\Settings\MonitoringSettings;
use App\Monitoring\Values\AcceptedStatusCodes;
use App\Monitoring\Values\ConfirmationThresholds;
use App\Monitoring\Values\MonitorDraft;
use App\Monitoring\Values\MonitorRequest;
use Illuminate\Foundation\Http\FormRequest;

final class StoreMonitorRequest extends FormRequest
{
    public function rules(MonitoringSettings $settings): array
    {
        return (new MonitorFieldRules($settings))->rules(partial: false);
    }

    public function draft(MonitoringSettings $settings): MonitorDraft
    {
        $defaults = $settings->monitorDefaults;

        return new MonitorDraft(
            name: $this->string('name')->toString(),
            url: $this->string('url')->toString(),
            method: $this->has('method') ? HttpMethod::from($this->string('method')->toString()) : $defaults->method,
            intervalSeconds: $this->has('interval_seconds') ? $this->integer('interval_seconds') : $settings->intervals->defaultSeconds,
            timeoutSeconds: $this->has('timeout_seconds') ? $this->integer('timeout_seconds') : $settings->probe->defaultTimeoutSeconds,
            acceptedStatusCodes: $this->has('accepted_status_codes') ? AcceptedStatusCodes::parse($this->array('accepted_status_codes')) : $defaults->acceptedStatusCodes,
            followRedirects: $this->has('follow_redirects') ? $this->boolean('follow_redirects') : MonitorFieldRules::DEFAULT_FOLLOW_REDIRECTS,
            thresholds: new ConfirmationThresholds(
                $this->has('failure_threshold') ? $this->integer('failure_threshold') : $settings->confirmation->thresholds->failuresToConfirmDown,
                $this->has('recovery_threshold') ? $this->integer('recovery_threshold') : $settings->confirmation->thresholds->successesToConfirmUp,
            ),
            request: MonitorRequest::none(),
        );
    }
}
