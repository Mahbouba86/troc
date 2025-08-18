<?php
namespace Enum\Reservation;

enum ReservationStatus: string
{
    case PENDING  = 'Pending';
    case ACCEPTED = 'Accepted';
    case REFUSED  = 'Refused';
    case CANCELLED = 'Cancelled';
}

