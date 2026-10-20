# MisterPlan · Gestión de reservas

Aplicación web pequeña para gestionar reservas de alojamiento. Tiene una API en PHP sin framework, páginas con Twig y JavaScript que consume la API.

- **Demo:** _(pendiente)_
- **Repositorio:** _(pendiente)_

## Tecnologías

PHP 8.2 con PDO, MySQL/MariaDB, Twig 3 (con Composer), HTML, CSS y JavaScript con `fetch`.

## Cómo ejecutarlo en local

Necesitas XAMPP con PHP 8.1 o superior y Composer.

1. Copia la carpeta del proyecto en `htdocs` (por ejemplo `C:\xampp\htdocs\misterplan`).
2. Arranca Apache y MySQL desde XAMPP.
3. Abre `http://localhost/phpmyadmin`, pulsa *Importar* y elige `database.sql`. Se crea la base `reservas_db` con 8 reservas y 15 eventos de ejemplo. Se puede importar otra vez para restaurar los datos.
4. Si tu MySQL no usa `root` sin contraseña, cambia los datos en `config.php`.
5. En la carpeta del proyecto ejecuta `composer install`.
6. Abre `http://localhost/misterplan/public/`.

## Estructura

- `public/`: lo único accesible desde el navegador (`index.php`, `.htaccess`, `css`, `js`, `img`).
- `src/`: las clases PHP (`Database`, `Reserva`, `ReservaRepository`).
- `templates/`: plantillas Twig (`base`, `lista`, `detalle`).
- `config.php` y `database.sql`.

## API

| Método | Ruta | Qué hace          | Códigos                               |
|---     |---   |---                |---                                    |
| GET    | `/reservas`              | Lista con filtros     | 200, 400      |
| GET    | `/reservas/{id}`         | Detalle con historial | 200, 404      |
| POST   | `/reservas`              | Crea una reserva      | 201, 400      |
| PUT    | `/reservas/{id}/estado`  | Cambia el estado      | 200, 400, 404 |

Filtros de la lista (se pueden combinar): `estado`, `nombre`, `fecha_desde`, `fecha_hasta`. Si ocurre un error inesperado se devuelve 500 con un mensaje genérico.

## Decisiones

**Clases.** Cada una hace una sola cosa:
- `Database` abre la conexión.
- `ReservaRepository` es la única que escribe SQL.
- `Reserva` valida los datos.
- `index.php` recibe la petición y responde.

No usé ORM ni inyección de dependencias porque para un proyecto pequeño solo añaden complejidad.

**Historial.** Al crear una reserva o cambiar su estado se guarda el cambio y su evento dentro de una transacción, para que nunca quede una cosa sin la otra.

**MySQL.** Es lo que usa la empresa y es lo que conozco. Los datos son relacionales (una reserva tiene muchos eventos), y me permite usar clave foránea, `ENUM` para los estados y `DECIMAL` para el dinero.

**Seguridad.**
- Consultas preparadas en todo lo que viene del usuario.
- Validación en el servidor, aunque también valide JavaScript.
- Una reserva nueva siempre empieza en `pendiente`, el cliente no lo decide.
- `htmlspecialchars` al guardar, autoescape de Twig al mostrar, y en JavaScript `textContent` y una función `esc()`.
- Los errores reales van al log y al usuario solo le llega un mensaje genérico.

**Uso de `|raw`.** Solo lo uso en `base.html.twig` para pasar la ruta base a JavaScript:

```twig
<script>window.BASE_URL = {{ base_url|json_encode|raw }};</script>
```

Es seguro porque `json_encode` ya escapa el valor y lo genera el servidor, no el usuario.

**Twig y PHP.** Las versiones actuales de Twig necesitan PHP 8.1 o superior, por eso uso PHP 8.2.

## Qué haría con más tiempo

1. Login para empleados y roles. Ahora cualquiera con la URL ve nombres y emails de huéspedes.
2. Tests con PHPUnit.
3. Arreglar el doble escapado: guardo con `htmlspecialchars` y Twig vuelve a escapar, así que un nombre como `O'Brien` se vería mal. Lo correcto es guardar el dato original y escapar solo al mostrar.
4. Paginación del listado.
5. Protección CSRF y límite de peticiones.
6. Más reglas de negocio (por ejemplo, no confirmar una reserva cancelada, ni fechas pasadas).
7. Usuario de MySQL con pocos permisos y credenciales en variables de entorno.

## Escalabilidad (1000 reservas/hora)

Son menos de 2 reservas por minuto de media, y MySQL lo soporta sin problema. Aun así cambiaría:

1. Paginación y índices en `estado`, `fecha_entrada` y `fecha_salida`. El `LIKE '%texto%'` no usa índice, así que usaría `FULLTEXT`.
2. Pensar en los picos de tráfico y no en la media: transacciones cortas y PHP-FPM bien dimensionado.
3. Caché (Redis o APCu) para listados y contadores.
4. Varias instancias de la app detrás de un balanceador (no guarda estado) y una réplica de lectura de la base de datos.
5. Si se añadieran emails u otras tareas lentas, hacerlas en segundo plano con una cola.
6. Logs, métricas y copias de seguridad automáticas.