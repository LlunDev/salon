<?php

namespace App\Enums;

enum UserRole: string
{
    case OWNER = 'OWNER';
    case STAFF = 'STAFF';
    case CLIENT = 'CLIENT';
}
