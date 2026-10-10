<?php

declare(strict_types=1);

namespace Tueen\Database\Exception;

use Symfony\Component\Validator\ConstraintViolationListInterface;

class ValidationException extends DatabaseException
{
    public function __construct(
        string $message,
        public private(set) ?ConstraintViolationListInterface $violations = null,
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    public static function fromViolations(ConstraintViolationListInterface $violations): self
    {
        $messages = [];
        foreach ($violations as $violation) {
            $messages[] = sprintf('%s: %s', $violation->getPropertyPath(), $violation->getMessage());
        }

        return new self('Validation failed: ' . implode('; ', $messages), $violations);
    }
}
