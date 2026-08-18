<?php

namespace App\Enums;

/**
 * SRD.md 2.2 user classes / ARCHITECTURE.md 3.2 Policies.
 */
enum UserRole: string
{
    case RegularUser = 'regular_user';
    case WorkspaceAdmin = 'workspace_admin';
    case SystemAdmin = 'system_admin';
}
