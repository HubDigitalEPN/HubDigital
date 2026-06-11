<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\Exceptions;

/** Excepción base de la capa de aplicación; extiende RuntimeException para distinguir fallos de use-cases de errores de infraestructura. */
abstract class ApplicationException extends \RuntimeException {}
