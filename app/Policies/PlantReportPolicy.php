<?php

namespace App\Policies;

use App\Models\PlantReport;
use App\Models\User;

class PlantReportPolicy
{
    public function view(User $user, PlantReport $report): bool
    {
        return $user->isAdmin() || $user->hasRole('supervisor') || $report->reporter_id === $user->id;
    }

    public function delete(User $user, PlantReport $report): bool
    {
        return $user->isAdmin();
    }
}
