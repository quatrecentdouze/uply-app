<?php

declare(strict_types=1);

namespace App\Shared\Database;

use Illuminate\Database\ConnectionInterface;
use LogicException;

final readonly class AdvisoryLock
{
    public function __construct(private ConnectionInterface $database) {}

    public function holdUntilCommit(string $scope, string $key): void
    {
        if ($this->database->transactionLevel() === 0) {
            throw new LogicException("the {$scope} lock is only held inside a transaction");
        }
        $this->database->select('SELECT pg_advisory_xact_lock(hashtextextended(?, 0))', [self::name($scope, $key)]);
    }

    public static function name(string $scope, string $key): string
    {
        return "{$scope}:{$key}";
    }
}
