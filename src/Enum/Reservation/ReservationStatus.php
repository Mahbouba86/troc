<?php
namespace App\Enum\Reservation;

enum ReservationStatus: string
{
    case PENDING  = 'Pending';
    case ACCEPTED = 'Accepted';
    case REFUSED  = 'Refused';
    case CANCELLED = 'Cancelled';
}

