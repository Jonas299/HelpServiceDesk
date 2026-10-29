<?php

namespace App\Domain\Users\Enums;

enum Role: string
{
    case Admin = 'admin';
    case Agent = 'agent';
    case Client = 'client';
}
