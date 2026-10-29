<?php

namespace App\Exceptions;

use RuntimeException;

/** De training is al afgerond, bijvoorbeeld in een ander tabblad of op een ander apparaat */
class SessionNotRunning extends RuntimeException {}
