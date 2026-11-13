<?php

namespace App\Policies;

use App\Enums\QuoteStatus;
use App\Models\Quote;
use App\Models\User;

final class QuotePolicy
{
    public function viewAny(User $u): bool
    {
        return $u->tenants()->wherePivot('status', 'active')->exists();
    }

    public function view(User $u, Quote $q): bool
    {
        return $this->same($u, $q);
    }

    public function create(User $u): bool
    {
        return $this->sameTenant($u);
    }

    public function update(User $u, Quote $q): bool
    {
        return $this->same($u, $q) && $q->status->value === 'draft';
    }

    public function send(User $u, Quote $q): bool
    {
        return $this->same($u, $q) && $q->status->canTransitionTo(QuoteStatus::Sent);
    }

    public function revoke(User $u, Quote $q): bool
    {
        return $this->same($u, $q);
    }

    public function resend(User $u, Quote $q): bool
    {
        return $this->same($u, $q) && in_array($q->status, [QuoteStatus::Draft, QuoteStatus::Sent, QuoteStatus::Viewed], true);
    }

    public function convert(User $u, Quote $q): bool
    {
        return $this->same($u, $q) && $q->status === QuoteStatus::Approved;
    }

    private function same(User $u, Quote $q): bool
    {
        return $u->tenants()->whereKey($q->tenant_id)->wherePivot('status', 'active')->exists();
    }

    private function sameTenant(User $u): bool
    {
        return $u->tenants()->wherePivot('status', 'active')->exists();
    }
}
