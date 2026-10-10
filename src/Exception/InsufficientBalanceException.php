<?php

declare(strict_types=1);

namespace Tueen\Database\Exception;

use Throwable;

class InsufficientBalanceException extends DatabaseException
{
    public ?string $balance {
        get => $this->available;
    }

    public function __construct(
        string $message,
        public readonly ?string $available = null,
        public readonly ?string $required = null,
        public readonly ?string $currency = null,
        public readonly ?int $chatId = null,
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    public static function forChat(int $chatId, string $currency, string $available, string $required): self
    {
        return new self(
            message: "Insufficient balance for chat [{$chatId}] in [{$currency}]. Available: {$available}, Required: {$required}.",
            available: $available,
            required: $required,
            currency: $currency,
            chatId: $chatId,
        );
    }

    public static function forTreasury(string $currency, string $available, string $required): self
    {
        return new self(
            message: "Insufficient treasury balance in [{$currency}]. Available: {$available}, Required: {$required}.",
            available: $available,
            required: $required,
            currency: $currency,
        );
    }
}
