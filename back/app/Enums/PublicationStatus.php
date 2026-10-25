<?php

namespace App\Enums;

enum PublicationStatus: string
{
    case Draft = 'draft';
    case Validating = 'validating';
    case Published = 'published';
    case Superseded = 'superseded';
    case Archived = 'archived';
}
