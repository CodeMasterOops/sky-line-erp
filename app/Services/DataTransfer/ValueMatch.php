<?php

namespace App\Services\DataTransfer;

class ValueMatch
{
    public const STATUS_MATCHED = 'matched';

    public const STATUS_SUGGESTED = 'suggested';

    public const STATUS_UNMATCHED = 'unmatched';

    public const STATUS_SKIP = 'skip';

    /**
     * @param  list<array{id: int, label: string}>  $suggestions
     */
    public function __construct(
        public string $status,
        public ?int $id = null,
        public array $suggestions = [],
    ) {}

    public static function matched(int $id): self
    {
        return new self(self::STATUS_MATCHED, $id);
    }

    /**
     * @param  list<array{id: int, label: string}>  $suggestions
     */
    public static function suggested(array $suggestions): self
    {
        return new self(self::STATUS_SUGGESTED, null, $suggestions);
    }

    public static function unmatched(): self
    {
        return new self(self::STATUS_UNMATCHED);
    }

    public static function skip(): self
    {
        return new self(self::STATUS_SKIP);
    }

    public function isMatched(): bool
    {
        return $this->status === self::STATUS_MATCHED;
    }

    public function isSkip(): bool
    {
        return $this->status === self::STATUS_SKIP;
    }
}
