<?php

declare(strict_types=1);

namespace Tests\Unit\CRM;

use App\Enums\ServiceStatus;
use PHPUnit\Framework\TestCase;

final class ServiceStatusTest extends TestCase
{
    public function test_only_allowed_service_status_transitions_are_accepted(): void
    {
        $allowed = [
            'planned' => ['in_progress', 'canceled'],
            'in_progress' => ['paused', 'completed', 'canceled'],
            'paused' => ['in_progress', 'canceled'],
            'completed' => [],
            'canceled' => [],
        ];

        foreach (ServiceStatus::cases() as $current) {
            foreach (ServiceStatus::cases() as $next) {
                $this->assertSame(
                    in_array($next->value, $allowed[$current->value], true),
                    $current->canTransitionTo($next),
                    "Transição {$current->value} → {$next->value}",
                );
            }
        }
    }
}
