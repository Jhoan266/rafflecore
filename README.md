<p align="center">
  <h1 align="center">⚡ RaffleCore</h1>
  <p align="center">
    <strong>Sistema profesional de rifas para WordPress + WooCommerce</strong><br>
    Arquitectura SaaS-ready · Anti-colisión · Sorteo criptográficamente seguro
  </p>
  <p align="center">
    <img src="https://img.shields.io/badge/version-2.0.0-blue?style=flat-square" alt="Version">
    <img src="https://img.shields.io/badge/tests-94%2F94_passing-brightgreen?style=flat-square" alt="Tests">
    <img src="https://img.shields.io/badge/WordPress-5.8%2B-21759b?style=flat-square&logo=wordpress" alt="WordPress">
    <img src="https://img.shields.io/badge/PHP-7.4%2B-777BB4?style=flat-square&logo=php&logoColor=white" alt="PHP">
    <img src="https://img.shields.io/badge/WooCommerce-7.0%2B-96588A?style=flat-square&logo=woocommerce&logoColor=white" alt="WooCommerce">
    <img src="https://img.shields.io/badge/license-GPL--2.0-green?style=flat-square" alt="License">
  </p>
</p>

---

## ¿Qué es RaffleCore?

**RaffleCore** es un plugin de WordPress de nivel empresarial para crear, gestionar y monetizar rifas digitales con integración nativa en WooCommerce. Diseñado con arquitectura modular que puede operar como plugin local o conectarse a un backend SaaS externo sin modificar código.

### ¿Por qué RaffleCore?

| Problema                                                              | Solución                                                                               |
| --------------------------------------------------------------------- | -------------------------------------------------------------------------------------- |
| Sobreventa de boletos cuando múltiples usuarios pagan al mismo tiempo | **Sistema de reservas pre-pago** con `FOR UPDATE` + transacciones atómicas             |
| Boletos duplicados en rifas con alta demanda                          | **Algoritmo pool-based** con Fisher-Yates shuffle — cero colisiones garantizadas       |
| Sorteos manipulables                                                  | **`random_int()` CSPRNG** — generación criptográficamente segura                       |
| Plugins de rifas atados a una sola pasarela de pago                   | **WooCommerce nativo** — funciona con Stripe, PayPal, MercadoPago o cualquier pasarela |
| Código monolítico difícil de escalar                                  | **8 módulos independientes** con capa API Service intercambiable                       |

---

## Características

### 🎯 Gestión de Rifas

- Crear rifas con imagen, premio, boletos, paquetes de descuento y fecha de sorteo
- Paquetes flexibles: `5 boletos por $20.000`, `10 por $35.000`, `25 por $75.000`
- Estados: activa, pausada, finalizada, cancelada
- Shortcode `[rafflecore id="X"]` para cualquier página

### 🔒 Anti-Colisión (Zero Duplicates)

- Pool de números disponibles → Fisher-Yates shuffle → slice
- `random_int()` (CSPRNG) en lugar de `rand()` o `mt_rand()`
- Constraint `UNIQUE KEY (raffle_id, ticket_number)` como respaldo en BD
- Rendimiento O(n) garantizado — funciona igual al 1% o 99% de ocupación

### 🛒 WooCommerce Profesional

- Producto virtual oculto creado automáticamente por rifa
- Precio del paquete como metadata del carrito (no del producto)
- Flujo: seleccionar paquete → checkout WooCommerce → pago → boletos generados
- Datos de rifa visibles en la orden de WooCommerce para el admin

### ⚡ Reservas Pre-Pago

- Reserva atómica de boletos antes del pago — elimina la sobreventa
- Liberación automática por WP-Cron si el pago no se completa en 30 minutos
- Cancelación inmediata al fallar o cancelar una orden

### 🎲 Sorteo Seguro

- `random_int()` con respaldo criptográfico del sistema operativo
- Transacción atómica con `FOR UPDATE` — imposible ejecutar dos sorteos a la vez
- Botón de sorteo en el panel admin con verificación de permisos + nonce

### 📧 Emails

- Template HTML profesional con gradient header
- Confirmación automática con tabla de resumen y badges de boletos
- Compatible con todos los clientes de email (estilos inline)

### 📊 Panel Admin

- **Dashboard**: 6 stats cards, compras recientes, acciones rápidas
- **Rifas**: Lista con progreso, crear/editar con media uploader
- **Detalle**: Sorteo en vivo, tabla de compradores con boletos
- **Compradores**: Búsqueda y filtros con paginación
- **Configuración**: API URL/Key para modo SaaS

### 🎨 Diseño

- Glassmorphism con `backdrop-filter: blur`
- Countdown con animación en vivo
- Barra de progreso con efecto shimmer
- Responsive (breakpoint 600px para móviles)

---

## Arquitectura

```
rafflecore/
├── rafflecore.php                         # Entrada + constantes + autoload
├── includes/
│   ├── class-rafflecore.php               # Orquestador de hooks
│   ├── class-rafflecore-activator.php     # Esquema BD + migraciones
│   └── class-rafflecore-loader.php        # Registro de actions/filters
├── api/
│   └── class-api-service.php              # Capa SaaS (local ↔ API HTTP)
├── modules/
│   ├── raffle/                            # CRUD + progreso + paquetes
│   ├── ticket/                            # Pool-based + Fisher-Yates
│   ├── purchase/                          # Validación + reservas
│   ├── draw/                              # Sorteo atómico
│   ├── email/                             # Template HTML
│   └── woocommerce/                       # Integración + Product Manager
├── admin/
│   ├── class-rafflecore-admin.php         # Controlador (6 vistas)
│   └── views/                             # Dashboard, listas, formularios
├── public/
│   ├── class-rafflecore-public.php        # Shortcode
│   └── views/raffle-display.php           # Vista pública
├── assets/
│   ├── css/                               # admin.css + public.css
│   └── js/                                # admin.js + public.js
└── tests/
    └── test-rafflecore.php                # 94 tests funcionales
```

### API Service Layer

El plugin opera en dos modos intercambiables cambiando una constante:

```php
define('RAFFLECORE_MODE', 'local'); // Consultas directas a WordPress BD
define('RAFFLECORE_MODE', 'api');   // HTTP requests a API REST externa
```

Esto permite migrar a un modelo SaaS (backend centralizado, múltiples sitios WordPress como frontend) sin tocar el código de los módulos.

---

## Instalación

1. Clona el repositorio:
   ```bash
   git clone https://github.com/Jhoan266/rafflecore.git
   ```
2. Copia `rafflecore/` a `wp-content/plugins/`
3. Activa el plugin en **WordPress → Plugins**
4. Requiere **WooCommerce** instalado y activo

### Con Docker (desarrollo)

```yaml
# docker-compose.yml
services:
  app:
    image: wordpress:latest
    ports: ["8080:80"]
    volumes:
      - ./rafflecore:/var/www/html/wp-content/plugins/rafflecore
  db:
    image: mysql:8.0
```

## Tests

```bash
# Ejecutar suite completa (94 tests)
docker exec wp_rifas_app bash -c "
  cd /var/www/html &&
  php -r \"require 'wp-load.php'; require 'wp-content/plugins/rafflecore/tests/test-rafflecore.php';\"
"
```

```
94 pasaron, 0 fallaron de 94 tests
```

## Requisitos

| Componente  | Versión mínima |
| ----------- | -------------- |
| WordPress   | 5.8+           |
| PHP         | 7.4+           |
| WooCommerce | 7.0+           |
| MySQL       | 8.0+           |

## Métricas

| Métrica            | Valor          |
| ------------------ | -------------- |
| Archivos           | 30             |
| Líneas de código   | ~4,673         |
| Módulos            | 8              |
| Tests              | 94/94 (100%)   |
| Cobertura de áreas | 20 test groups |

## Licencia

[GPL-2.0+](https://www.gnu.org/licenses/gpl-2.0.html)

---

<p align="center">
  <strong>RaffleCore v2.0.0</strong> — 30 archivos · 4,673 líneas · 94 tests · 0 fallos
</p>
