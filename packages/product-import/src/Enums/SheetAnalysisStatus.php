<?php

namespace Lunar\ProductImport\Enums;

enum SheetAnalysisStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Failed = 'failed';
    case ProfileOffered = 'profile_offered';
    case ProfileApplied = 'profile_applied';
}
