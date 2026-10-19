<?php

declare(strict_types=1);

namespace App\Monitoring\Http\Requests;

use App\Monitoring\Enums\HttpMethod;
use App\Monitoring\Enums\KeywordMatch;
use App\Monitoring\Settings\MonitoringSettings;
use App\Monitoring\Storage\MonitorQuery;
use App\Monitoring\Values\AcceptedStatusCodes;
use App\Monitoring\Values\ConfirmationThresholds;
use App\Monitoring\Values\MonitorDraft;
use App\Monitoring\Values\MonitorRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class UpdateMonitorRequest extends FormRequest
{
    public function rules(MonitoringSettings $settings): array
    {
        return [
            ...(new MonitorFieldRules($settings))->rules(partial: true),
            'is_paused' => ['sometimes', 'boolean'],
        ];
    }

    public function after(MonitorQuery $monitors): array
    {
        return [
            function (Validator $validator) use ($monitors): void {
                if ($validator->errors()->isEmpty()) {
                    $current = $monitors->get($this->routeId('organization'), $this->routeId('monitor'));
                    DraftConflictCheck::report($validator, $this->draftOver($current->draft()), MonitorFieldRules::sentFields($this));
                }
            },
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
            keyword: $this->has('keyword') ? MonitorFieldRules::keywordFrom($this->input('keyword')) : $current->keyword,
            keywordMatch: $this->has('keyword_match') ? KeywordMatch::from($this->string('keyword_match')->toString()) : $current->keywordMatch,
            thresholds: new ConfirmationThresholds(
                $this->has('failure_threshold') ? $this->integer('failure_threshold') : $current->thresholds->failuresToConfirmDown,
                $this->has('recovery_threshold') ? $this->integer('recovery_threshold') : $current->thresholds->successesToConfirmUp,
            ),
            request: $this->requestOver($current->request),
        );
    }

    public function paused(): ?bool
    {
        return $this->has('is_paused') ? $this->boolean('is_paused') : null;
    }

    private function requestOver(MonitorRequest $current): MonitorRequest
    {
        $request = $this->has('request_headers') ? $current->withHeaders(MonitorFieldRules::headersFrom($this->input('request_headers'))) : $current;

        return $this->has('request_body') ? $request->withBody(MonitorFieldRules::bodyFrom($this->input('request_body'))) : $request;
    }

    private function routeId(string $name): string
    {
        $value = $this->route($name);

        return is_string($value) ? $value : '';
    }
}
