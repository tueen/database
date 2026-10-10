<?php

declare(strict_types=1);

namespace Tueen\Database\Enum;

enum ChatType: string
{
    case Private = 'private';
    case Group = 'group';
    case Supergroup = 'supergroup';
    case Channel = 'channel';

    public function isDirect(): bool
    {
        return $this === self::Private;
    }

    public function isGroup(): bool
    {
        return $this === self::Group || $this === self::Supergroup;
    }

    public function isChannel(): bool
    {
        return $this === self::Channel;
    }
}
