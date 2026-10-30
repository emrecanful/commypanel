<?php

namespace App\Enums;

enum CertificateStatus: string
{
    case Issuing = 'issuing';
    case Active = 'active';
    case Failed = 'failed';
}
