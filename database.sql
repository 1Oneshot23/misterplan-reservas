-- ============================================================
-- MisterPlan - Gestión de reservas de alojamiento
-- Base de datos: reservas_db
-- ============================================================

-- Crear la base de datos si no existe
CREATE DATABASE IF NOT EXISTS reservas_db
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE reservas_db;

-- Borrar las tablas antiguas (primero la hija, luego la padre)
DROP TABLE IF EXISTS reserva_evento;
DROP TABLE IF EXISTS reserva;

-- ------------------------------------------------------------
-- Tabla principal: reserva
-- ------------------------------------------------------------
CREATE TABLE reserva (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre_huesped VARCHAR(100) NOT NULL,
  email_huesped VARCHAR(150) NOT NULL,
  nombre_alojamiento VARCHAR(150) NOT NULL,
  fecha_entrada DATE NOT NULL,
  fecha_salida DATE NOT NULL,
  estado ENUM('pendiente', 'confirmada', 'cancelada') NOT NULL DEFAULT 'pendiente',
  importe DECIMAL(10,2) NOT NULL,
  notas TEXT NULL,
  fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- ------------------------------------------------------------
-- Historial de cambios de cada reserva: reserva_evento
-- ------------------------------------------------------------
CREATE TABLE reserva_evento (
  id INT AUTO_INCREMENT PRIMARY KEY,
  reserva_id INT NOT NULL,
  tipo VARCHAR(50) NOT NULL,
  descripcion TEXT NOT NULL,
  fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  -- Si se borra una reserva, se borran también sus eventos
  FOREIGN KEY (reserva_id) REFERENCES reserva(id) ON DELETE CASCADE
);

-- ------------------------------------------------------------
-- Datos de ejemplo: reservas en distintos estados
-- ------------------------------------------------------------
INSERT INTO reserva
  (nombre_huesped, email_huesped, nombre_alojamiento, fecha_entrada, fecha_salida, estado, importe, notas, fecha_creacion)
VALUES
  ('María García', 'maria.garcia@example.com', 'Hotel Sol y Mar', '2026-11-05', '2026-11-10', 'confirmada', 450.00, 'Habitación con vistas al mar', '2026-10-01 10:15:00'),
  ('Juan Pérez', 'juan.perez@example.com', 'Hotel Gran Vía', '2026-11-12', '2026-11-14', 'pendiente', 220.50, NULL, '2026-10-05 14:30:00'),
  ('Laura Martínez', 'laura.martinez@example.com', 'Parador de Mérida', '2026-12-20', '2026-12-27', 'confirmada', 980.00, 'Viaje familiar, cuna para bebé', '2026-10-07 09:00:00'),
  ('Carlos Ruiz', 'carlos.ruiz@example.com', 'Hotel Sol y Mar', '2026-10-25', '2026-10-28', 'cancelada', 300.00, 'Cancelado por motivos de trabajo', '2026-09-20 18:45:00'),
  ('Ana López', 'ana.lopez@example.com', 'Hostal La Plaza', '2026-11-30', '2026-12-02', 'pendiente', 140.00, NULL, '2026-10-10 11:20:00'),
  ('Pedro Sánchez', 'pedro.sanchez@example.com', 'Hotel Gran Vía', '2027-01-10', '2027-01-15', 'confirmada', 625.75, 'Llegada tarde, después de las 22:00', '2026-10-12 16:05:00'),
  ('Lucía Fernández', 'lucia.fernandez@example.com', 'Parador de Mérida', '2026-11-18', '2026-11-20', 'cancelada', 310.00, NULL, '2026-10-02 08:40:00'),
  ('Miguel Torres', 'miguel.torres@example.com', 'Hostal La Plaza', '2026-12-05', '2026-12-08', 'confirmada', 255.00, 'Habitación individual', '2026-10-14 13:10:00');

-- Historial de ejemplo: cada reserva tiene al menos el evento "creada"
INSERT INTO reserva_evento (reserva_id, tipo, descripcion, fecha_creacion) VALUES
  (1, 'creada', 'Reserva creada en estado pendiente', '2026-10-01 10:15:00'),
  (1, 'confirmada', 'La reserva fue confirmada', '2026-10-02 09:00:00'),
  (2, 'creada', 'Reserva creada en estado pendiente', '2026-10-05 14:30:00'),
  (3, 'creada', 'Reserva creada en estado pendiente', '2026-10-07 09:00:00'),
  (3, 'confirmada', 'La reserva fue confirmada', '2026-10-07 12:30:00'),
  (4, 'creada', 'Reserva creada en estado pendiente', '2026-09-20 18:45:00'),
  (4, 'confirmada', 'La reserva fue confirmada', '2026-09-21 10:00:00'),
  (4, 'cancelada', 'La reserva fue cancelada', '2026-10-03 15:20:00'),
  (5, 'creada', 'Reserva creada en estado pendiente', '2026-10-10 11:20:00'),
  (6, 'creada', 'Reserva creada en estado pendiente', '2026-10-12 16:05:00'),
  (6, 'confirmada', 'La reserva fue confirmada', '2026-10-13 09:45:00'),
  (7, 'creada', 'Reserva creada en estado pendiente', '2026-10-02 08:40:00'),
  (7, 'cancelada', 'La reserva fue cancelada', '2026-10-06 17:00:00'),
  (8, 'creada', 'Reserva creada en estado pendiente', '2026-10-14 13:10:00'),
  (8, 'confirmada', 'La reserva fue confirmada', '2026-10-15 10:30:00');