<?php

namespace MobileStock\Gatekeeper\Doctor;

use RuntimeException;

/**
 * Thrown by a probe that cannot run because another diagnostic already reports the cause.
 */
class DiagnosticSkipped extends RuntimeException
{
}
