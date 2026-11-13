<?php

namespace App\Enums;

enum ServiceStatus: string
{
    case Planned = 'planned';
    case InProgress = 'in_progress';
    case Paused = 'paused';
    case Completed = 'completed';
    case Canceled = 'canceled';

    public function canTransitionTo(self $next): bool
    {
        return match ($this) {
            self::Planned => in_array($next, [self::InProgress, self::Canceled], true), self::InProgress => in_array($next, [self::Paused, self::Completed, self::Canceled], true), self::Paused => in_array($next, [self::InProgress, self::Canceled], true), self::Completed, self::Canceled => false
        };
    }
}
