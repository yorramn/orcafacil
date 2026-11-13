<?php

declare(strict_types=1);

namespace App\Enums;

enum QuoteStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Viewed = 'viewed';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Expired = 'expired';
    case Canceled = 'canceled';

    public function canTransitionTo(self $next): bool
    {
        return match ($this) {
            self::Draft => in_array($next, [self::Sent, self::Approved, self::Rejected, self::Canceled], true),
            self::Sent => in_array($next, [self::Viewed, self::Approved, self::Rejected, self::Expired], true),
            self::Viewed => in_array($next, [self::Approved, self::Rejected], true),
            default => false,
        };
    }
}
