<?php

declare(strict_types=1);

namespace App\Monitoring\Models;

use App\Monitoring\Enums\HttpMethod;
use App\Monitoring\Enums\MonitorState;
use App\Monitoring\Values\AcceptedStatusCodes;
use App\Monitoring\Values\CheckDefinition;
use App\Monitoring\Values\ConfirmationThresholds;
use App\Monitoring\Values\MonitorDraft;
use App\Monitoring\Values\MonitorRequest;
use App\Monitoring\Values\MonitorStateSnapshot;
use App\Monitoring\Values\ProbeRequest;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use LogicException;

#[Fillable([
    'organization_id',
    'name',
    'url',
    'method',
    'interval_seconds',
    'timeout_seconds',
    'accepted_status_codes',
    'follow_redirects',
    'failure_threshold',
    'recovery_threshold',
    'request',
    'is_paused',
    'next_check_at',
])]
#[Hidden(['request'])]
final class Monitor extends Model
{
    use HasFactory;
    use HasUlids;

    public function probeRequest(): ProbeRequest
    {
        return new ProbeRequest($this->url, $this->method, $this->timeout_seconds, $this->follow_redirects, $this->checkRequest());
    }

    public function acceptedStatusCodes(): AcceptedStatusCodes
    {
        $texts = $this->getAttribute('accepted_status_codes');

        return AcceptedStatusCodes::parse(is_array($texts) ? $texts : []);
    }

    public function confirmationThresholds(): ConfirmationThresholds
    {
        return new ConfirmationThresholds($this->failure_threshold, $this->recovery_threshold);
    }

    public function checkRequest(): MonitorRequest
    {
        $stored = $this->getAttribute('request');

        return MonitorRequest::fromStored(is_array($stored) ? $stored : null);
    }

    public function stateSnapshot(): MonitorStateSnapshot
    {
        return new MonitorStateSnapshot(
            $this->state,
            $this->consecutive_failures,
            $this->consecutive_successes,
            $this->streak_started_at,
            $this->state_changed_at,
        );
    }

    public function draft(): MonitorDraft
    {
        return new MonitorDraft(
            $this->name,
            $this->url,
            $this->method,
            $this->interval_seconds,
            $this->timeout_seconds,
            $this->acceptedStatusCodes(),
            $this->follow_redirects,
            $this->confirmationThresholds(),
            $this->checkRequest(),
        );
    }

    public function checkDefinition(): CheckDefinition
    {
        return new CheckDefinition(
            $this->url,
            $this->method,
            $this->acceptedStatusCodes(),
            $this->follow_redirects,
            $this->interval_seconds,
            $this->is_paused,
            $this->checkRequest(),
        );
    }

    public function nextCheckAt(): CarbonImmutable
    {
        return $this->next_check_at ?? throw new LogicException("monitor {$this->id} has no next check time");
    }

    public function clearStreak(): void
    {
        $this->consecutive_failures = 0;
        $this->consecutive_successes = 0;
        $this->streak_started_at = null;
    }

    public function fillFrom(MonitorDraft $draft): void
    {
        $this->name = $draft->name;
        $this->url = $draft->url;
        $this->method = $draft->method;
        $this->interval_seconds = $draft->intervalSeconds;
        $this->timeout_seconds = $draft->timeoutSeconds;
        $this->setAttribute('accepted_status_codes', $draft->acceptedStatusCodes->toTexts());
        $this->follow_redirects = $draft->followRedirects;
        $this->failure_threshold = $draft->thresholds->failuresToConfirmDown;
        $this->recovery_threshold = $draft->thresholds->successesToConfirmUp;
        $this->setAttribute('request', $draft->request->toStored());
    }

    public function applyCheck(MonitorStateSnapshot $snapshot, CarbonImmutable $checkedAt, ?int $responseMs, CarbonImmutable $nextCheckAt): void
    {
        $this->state = $snapshot->state;
        $this->consecutive_failures = $snapshot->consecutiveFailures;
        $this->consecutive_successes = $snapshot->consecutiveSuccesses;
        $this->streak_started_at = $snapshot->streakStartedAt;
        $this->state_changed_at = $snapshot->stateChangedAt;
        $this->last_checked_at = $checkedAt;
        $this->last_response_ms = $responseMs;
        $this->next_check_at = $nextCheckAt;
    }

    protected function casts(): array
    {
        return [
            'method' => HttpMethod::class,
            'state' => MonitorState::class,
            'accepted_status_codes' => 'array',
            'request' => 'encrypted:array',
            'follow_redirects' => 'boolean',
            'is_paused' => 'boolean',
            'streak_started_at' => 'immutable_datetime',
            'state_changed_at' => 'immutable_datetime',
            'last_checked_at' => 'immutable_datetime',
            'next_check_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
