<?php

namespace App\Deploy\Source;

use RuntimeException;

/**
 * An uploaded archive cannot be used. The message is written for the user.
 */
class InvalidArchive extends RuntimeException {}
