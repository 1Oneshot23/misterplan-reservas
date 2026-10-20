<?php
class Reserva
{
    // Estados permitidos (deben coincidir con el ENUM de la base de datos)
    public const ESTADOS = ['pendiente', 'confirmada', 'cancelada'];

    // Campos que no pueden venir vacíos al crear una reserva
    private const OBLIGATORIOS = [
        'nombre_huesped',
        'email_huesped',
        'nombre_alojamiento',
        'fecha_entrada',
        'fecha_salida',
        'importe',
    ];

    // Valida los datos para crear una reserva. Devuelve un array de errores (vacío = todo correcto)
    public static function validarCreacion(array $datos): array
    {
        $errores = [];

        // 1) Campos obligatorios no vacíos
        foreach (self::OBLIGATORIOS as $campo) {
            if (!isset($datos[$campo]) || trim((string) $datos[$campo]) === '') {
                $errores[$campo] = 'El campo es obligatorio';
            }
        }

        // 2) Email válido (solo si no ha fallado ya como vacío)
        if (!isset($errores['email_huesped'])
            && !filter_var($datos['email_huesped'], FILTER_VALIDATE_EMAIL)) {
            $errores['email_huesped'] = 'El email no es válido';
        }

        // 3) Fechas válidas
        if (!isset($errores['fecha_entrada']) && !self::fechaValida($datos['fecha_entrada'])) {
            $errores['fecha_entrada'] = 'Fecha no válida (formato AAAA-MM-DD)';
        }
        if (!isset($errores['fecha_salida']) && !self::fechaValida($datos['fecha_salida'])) {
            $errores['fecha_salida'] = 'Fecha no válida (formato AAAA-MM-DD)';
        }

        // 4) La salida debe ser posterior a la entrada
        if (!isset($errores['fecha_entrada']) && !isset($errores['fecha_salida'])
            && $datos['fecha_salida'] <= $datos['fecha_entrada']) {
            $errores['fecha_salida'] = 'La fecha de salida debe ser posterior a la de entrada';
        }

        // 5) Importe numérico y mayor que cero
        if (!isset($errores['importe'])
            && (!is_numeric($datos['importe']) || (float) $datos['importe'] <= 0)) {
            $errores['importe'] = 'El importe debe ser un número mayor que 0';
        }

        // 6) Límites de longitud (coinciden con los VARCHAR de la base de datos)
        if (!isset($errores['nombre_huesped']) && mb_strlen($datos['nombre_huesped']) > 100) {
            $errores['nombre_huesped'] = 'Máximo 100 caracteres';
        }
        if (!isset($errores['nombre_alojamiento']) && mb_strlen($datos['nombre_alojamiento']) > 150) {
            $errores['nombre_alojamiento'] = 'Máximo 150 caracteres';
        }

        return $errores;
    }

    // Valida el nuevo estado de una reserva
    public static function validarEstado($estado): ?string
    {
        if (!in_array($estado, self::ESTADOS, true)) {
            return 'Estado no válido. Valores permitidos: ' . implode(', ', self::ESTADOS);
        }
        return null;
    }

    // Limpia los textos antes de guardarlos (protección contra XSS)
    public static function limpiar(array $datos): array
    {
        $limpios = [];
        foreach (['nombre_huesped', 'email_huesped', 'nombre_alojamiento', 'notas'] as $campo) {
            if (isset($datos[$campo])) {
                $limpios[$campo] = htmlspecialchars(trim($datos[$campo]), ENT_QUOTES, 'UTF-8');
            }
        }
        $limpios['fecha_entrada'] = $datos['fecha_entrada'] ?? '';
        $limpios['fecha_salida']  = $datos['fecha_salida'] ?? '';
        $limpios['importe']       = round((float) ($datos['importe'] ?? 0), 2);
        return $limpios;
    }

    // Comprueba que un texto sea una fecha real con formato AAAA-MM-DD
    private static function fechaValida($fecha): bool
    {
        $d = DateTime::createFromFormat('Y-m-d', (string) $fecha);
        return $d !== false && $d->format('Y-m-d') === $fecha;
    }
}