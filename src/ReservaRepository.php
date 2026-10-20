<?php
class ReservaRepository
{
    // Conexión a la base de datos (se recibe desde fuera)
    private PDO $pdo;

    // El constructor recibe la conexión ya creada por la clase Database
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // Devuelve las reservas aplicando los filtros que lleguen (todos son opcionales)
    public function obtenerTodas(array $filtros = []): array
    {
        // "WHERE 1 = 1" siempre es verdadero: nos permite añadir "AND ..." sin preocuparnos
        $sql = 'SELECT * FROM reserva WHERE 1 = 1';
        $parametros = [];

        // Filtro por estado (pendiente, confirmada, cancelada)
        if (!empty($filtros['estado'])) {
            $sql .= ' AND estado = :estado';
            $parametros[':estado'] = $filtros['estado'];
        }

        // Filtro por nombre del huésped (búsqueda parcial)
        if (!empty($filtros['nombre'])) {
            $sql .= ' AND nombre_huesped LIKE :nombre';
            $parametros[':nombre'] = '%' . $filtros['nombre'] . '%';
        }

        // Filtro por rango de fechas: entrada desde...
        if (!empty($filtros['fecha_desde'])) {
            $sql .= ' AND fecha_entrada >= :fecha_desde';
            $parametros[':fecha_desde'] = $filtros['fecha_desde'];
        }

        // ...y salida hasta
        if (!empty($filtros['fecha_hasta'])) {
            $sql .= ' AND fecha_salida <= :fecha_hasta';
            $parametros[':fecha_hasta'] = $filtros['fecha_hasta'];
        }

        $sql .= ' ORDER BY fecha_creacion DESC';

        // Los valores se envían por separado: protege contra inyección SQL
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($parametros);
        return $stmt->fetchAll();
    }

    // Devuelve una reserva por su id, o null si no existe
    public function obtenerPorId(int $id): ?array
    {
        // Consulta preparada: :id es un marcador, no el valor real
        $stmt = $this->pdo->prepare('SELECT * FROM reserva WHERE id = :id');

        // El valor se envía por separado para evitar inyección SQL
        $stmt->execute([':id' => $id]);

        // fetch() devuelve false si no hay resultado
        $fila = $stmt->fetch();

        // Convertimos false en null para que el resultado sea más claro
        return $fila === false ? null : $fila;
    }

    // Devuelve el historial de eventos de una reserva, del más antiguo al más reciente
    public function obtenerEventos(int $reservaId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM reserva_evento WHERE reserva_id = :id ORDER BY fecha_creacion ASC, id ASC'
        );
        $stmt->execute([':id' => $reservaId]);
        return $stmt->fetchAll();
    }

    // Crea una reserva nueva y registra el evento "creada". Devuelve el id nuevo
    public function crear(array $datos): int
    {
        // Transacción: o se guardan las dos cosas, o no se guarda nada
        $this->pdo->beginTransaction();

        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO reserva
                    (nombre_huesped, email_huesped, nombre_alojamiento, fecha_entrada, fecha_salida, estado, importe, notas)
                 VALUES
                    (:nombre, :email, :alojamiento, :entrada, :salida, :estado, :importe, :notas)'
            );
            $stmt->execute([
                ':nombre'      => $datos['nombre_huesped'],
                ':email'       => $datos['email_huesped'],
                ':alojamiento' => $datos['nombre_alojamiento'],
                ':entrada'     => $datos['fecha_entrada'],
                ':salida'      => $datos['fecha_salida'],
                ':estado'      => 'pendiente',
                ':importe'     => $datos['importe'],
                ':notas'       => $datos['notas'] ?? null,
            ]);

            // lastInsertId() devuelve el id que MySQL acaba de asignar
            $id = (int) $this->pdo->lastInsertId();

            $this->registrarEvento($id, 'creada', 'Reserva creada en estado pendiente');

            $this->pdo->commit();
            return $id;
        } catch (Throwable $e) {
            // Si algo falla, deshacemos todo y volvemos a lanzar el error
            $this->pdo->rollBack();
            throw $e;
        }
    }

    // Cambia el estado de una reserva y registra el evento correspondiente
    public function cambiarEstado(int $id, string $nuevoEstado): void
    {
        $this->pdo->beginTransaction();

        try {
            $stmt = $this->pdo->prepare('UPDATE reserva SET estado = :estado WHERE id = :id');
            $stmt->execute([':estado' => $nuevoEstado, ':id' => $id]);

            $this->registrarEvento($id, $nuevoEstado, 'La reserva fue ' . $nuevoEstado);

            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    // Inserta una fila en reserva_evento (función interna, por eso es private)
    private function registrarEvento(int $reservaId, string $tipo, string $descripcion): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO reserva_evento (reserva_id, tipo, descripcion)
             VALUES (:reserva_id, :tipo, :descripcion)'
        );
        $stmt->execute([
            ':reserva_id'  => $reservaId,
            ':tipo'        => $tipo,
            ':descripcion' => $descripcion,
        ]);
    }
}