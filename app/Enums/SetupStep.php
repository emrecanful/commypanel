<?php

namespace App\Enums;

/**
 * Where an app is in the creation wizard. Step 1 (basics) is the form that
 * creates the draft, so it has no case here.
 */
enum SetupStep: string
{
    case Source = 'source';
    case Settings = 'settings';
}
