<?php

namespace App\Enum\Annonce;

enum AnnonceStatus: string
{
    case PENDING   = 'pending';
    case AVAILABLE = 'available';
    case RESERVED  = 'reserved';
    case FINISHED  = 'finished';
}
