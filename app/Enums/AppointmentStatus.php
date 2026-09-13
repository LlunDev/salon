<?php

namespace App\Enums;

enum AppointmentStatus: string
{
    case CONFIRMED = 'confirmed';
    case CANCELLED = 'cancelled';
    case COMPLETED = 'completed';
}
