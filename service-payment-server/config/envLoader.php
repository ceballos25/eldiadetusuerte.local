<?php

/**
 * Carga de .env: si una clave aparece dos veces, gana la primera (no se sobrescribe).
 */
class EnvLoader {
    protected $path;

    public function __construct($path) {
        if (!file_exists($path)) {
            throw new Exception("El archivo .env no existe en: {$path}");
        }
        $this->path = $path;
    }

    public function load() {
        if (!is_readable($this->path)) {
            throw new Exception("El archivo .env no es legible");
        }

        $lines = file($this->path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) {
                continue;
            }

            if (strpos($line, '=') !== false) {
                list($name, $value) = explode('=', $line, 2);
                $name = trim($name);
                $value = trim(trim($value), '"\'');

                if (!array_key_exists($name, $_ENV)) {
                    $_ENV[$name] = $value;
                    putenv("{$name}={$value}");
                }
            }
        }
    }
}

function env($key, $default = null) {
    $value = $_ENV[$key] ?? getenv($key);

    if ($value === false) {
        return $default;
    }

    switch (strtolower($value)) {
        case 'true':
        case '(true)':
            return true;
        case 'false':
        case '(false)':
            return false;
        case 'empty':
        case '(empty)':
            return '';
        case 'null':
        case '(null)':
            return null;
    }

    return $value;
}
