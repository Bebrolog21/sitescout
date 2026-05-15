<?php

namespace App\Enums;

enum SiteStatus: string
{
    case New = 'new';
    case Screening = 'screening';
    case Inspection = 'inspection';
    case Scoring = 'scoring';
    case Negotiation = 'negotiation';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Launched = 'launched';
    case Archived = 'archived';
}
