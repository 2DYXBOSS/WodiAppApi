<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Erreur métier rendue au format attendu par les frontends :
 * `{ "message": "…", "errors": { "champ": "message" } }`.
 */
class ApiException extends RuntimeException
{
    /** @param array<string,string> $errors */
    public function __construct(public readonly int $status, string $message, public readonly array $errors = [])
    {
        parent::__construct($message);
    }

    public static function conflict(string $message, array $errors = []): self
    {
        return new self(409, $message, $errors);
    }

    public static function unprocessable(string $message, array $errors = []): self
    {
        return new self(422, $message, $errors);
    }

    public static function notFound(string $message = 'Ressource introuvable.'): self
    {
        return new self(404, $message);
    }

    public static function forbidden(string $message = 'Vous n’avez pas l’autorisation d’effectuer cette action.'): self
    {
        return new self(403, $message);
    }
}
