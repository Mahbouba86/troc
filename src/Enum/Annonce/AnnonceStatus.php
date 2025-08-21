<?php

namespace App\Enum\Annonce;

enum AnnonceStatus: string
{
    case PENDING = 'Pending';
    case PUBLISHED = 'Published';
    case CANCELLED = 'Drafted';
    case AVAILABLE = 'Available';
    case RESERVED = 'Reserved';
    case FINISHED = 'Finished';
}
