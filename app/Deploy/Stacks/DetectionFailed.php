<?php

namespace App\Deploy\Stacks;

use RuntimeException;

/**
 * The source was recognised (or not) but cannot be built automatically.
 * The message is written for the user and shown in the UI.
 */
class DetectionFailed extends RuntimeException {}
