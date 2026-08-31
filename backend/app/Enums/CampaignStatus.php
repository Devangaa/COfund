<?php

namespace App\Enums;

enum CampaignStatus: string
{
    case DRAFT = 'draft';
    case REVIEW = 'review';
    case ACTIVE = 'active';
    case SUCCESS = 'success';
    case FAILED = 'failed';
}