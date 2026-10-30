<?php

namespace App\Deploy;

use RuntimeException;

/**
 * A deploy step failed in a way the user should hear about; the message is
 * shown in the deployment log and the UI.
 */
class DeploymentFailed extends RuntimeException {}
