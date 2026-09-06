<?php

declare(strict_types=1);

namespace App\Alerting\Values;

use InvalidArgumentException;

final readonly class EmailRecipients
{
    private function __construct(
        public array $addresses,
    ) {}

    public static function parse(array $addresses, int $minimum, int $maximum): self
    {
        $unique = [];
        foreach ($addresses as $address) {
            $normalized = is_string($address) ? strtolower(trim($address)) : '';
            if (filter_var($normalized, FILTER_VALIDATE_EMAIL) === false) {
                throw new InvalidArgumentException('an email recipient is not a valid address');
            }
            if (! in_array($normalized, $unique, true)) {
                $unique[] = $normalized;
            }
        }
        if (count($unique) < $minimum || count($unique) > $maximum) {
            throw new InvalidArgumentException("an email channel needs {$minimum} to {$maximum} recipients");
        }

        return new self($unique);
    }
}
