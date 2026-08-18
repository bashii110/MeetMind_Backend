<?php

namespace App\Enums;

enum WorkspaceRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Member = 'member';

    /** Owner/Admin can manage workspace-scoped content (meetings, tasks, ...); Member cannot. */
    public function isManager(): bool
    {
        return $this === self::Owner || $this === self::Admin;
    }
}
