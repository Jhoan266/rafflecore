# RaffleCore

### El plugin de rifas más completo, seguro y escalable para WordPress + WooCommerce

---

## ¿Qué es RaffleCore?

RaffleCore es un **sistema profesional de rifas digitales** para WordPress que convierte tu sitio web en una plataforma de sorteos automatizada, segura y lista para escalar.

No es otro plugin básico de rifas. Es una **solución enterprise** construida con arquitectura de software profesional, seguridad bancaria y preparada para crecer desde un emprendimiento local hasta un modelo SaaS multi-cliente.

---

## El Problema que Resuelve

| Sin RaffleCore                                  | Con RaffleCore                                      |
| ----------------------------------------------- | --------------------------------------------------- |
| Vendes boletos manualmente por WhatsApp o redes | Tienda automatizada con pasarela de pago real       |
| No sabes cuántos boletos quedan en tiempo real  | Contador en vivo con barra de progreso animada      |
| Riesgo de vender el mismo boleto dos veces      | Imposible: protección anti-colisión criptográfica   |
| No puedes ofrecer descuentos por cantidad       | Paquetes de boletos + cupones de descuento          |
| Sorteos cuestionables, sin transparencia        | Sorteo con algoritmo criptográfico verificable      |
| Sin registro de quién compró qué                | Dashboard analítico con historial completo          |
| Escalas a mano: una rifa a la vez               | Múltiples rifas simultáneas, cada una independiente |

---

## Funcionalidades Principales

### 🛒 Integración Nativa con WooCommerce

Tu cliente elige un paquete de boletos, paga con **cualquier pasarela de WooCommerce** (tarjeta, PayPal, transferencia, crypto) y recibe sus boletos automáticamente por email.

**Flujo completo automatizado:**

```
Elige paquete → Aplica cupón → Paga → Recibe boletos por email
        ↓                                      ↓
   Reserva instantánea              Notificación al admin
   (nadie más toma esos boletos)    + log + webhook
```

- Compatible con **todas** las pasarelas de pago de WooCommerce
- Productos virtuales creados automáticamente (sin configuración manual)
- Si el pago falla, los boletos se liberan automáticamente
- Protección contra doble-cobro (idempotencia verificada)

### 🎟️ Gestión Inteligente de Boletos

- **Paquetes personalizables** — define cuántos boletos incluye cada paquete y su precio
- **Generación criptográfica** — algoritmo Fisher-Yates + CSPRNG garantiza aleatoriedad real
- **Anti-colisión garantizada** — imposible generar un boleto duplicado (protección en BD + código)
- **Reserva pre-pago** — los boletos se reservan al iniciar la compra, no al pagar
- **Liberación automática** — si el cliente no paga en 30 minutos, los boletos vuelven a estar disponibles

### 🏆 Sorteos Transparentes y Verificables

- Algoritmo **`random_int()`** — el generador criptográficamente seguro de PHP
- Transacción atómica — el ganador se registra de forma inmutable
- Log con hash chain SHA-256 — cada acción queda registrada y es **imposible de alterar**
- Notificación automática por email y webhook al ejecutar el sorteo

### 📊 Dashboard Analítico Profesional

Panel de control con **15+ métricas en tiempo real**:

- **Ingresos totales** y ganancia neta (revenue - valor del premio)
- **Tasa de venta** por rifa (% de boletos vendidos)
- **Top 10 compradores** y últimas transacciones
- **4 gráficas interactivas** con Chart.js (tendencias, comparativas, ranking)
- Filtros por periodo: diario, mensual, anual
- Tema oscuro profesional con glassmorphism

### 🎟️ Sistema de Cupones Completo

Impulsa tus ventas con descuentos inteligentes:

- Descuento por **porcentaje** o **monto fijo**
- Límite de usos por cupón
- Restricción por rifa específica
- Mínimo de boletos requerido para aplicar
- Fecha de expiración
- Validación en tiempo real desde el frontend (AJAX)

### 🔔 Webhooks para Integraciones Externas

Conecta RaffleCore con cualquier sistema externo:

- **5 eventos**: compra completada, rifa creada, rifa agotada, sorteo ejecutado, cupón usado
- Firma HMAC SHA-256 para verificar autenticidad
- Ideal para conectar con Zapier, Make, CRMs, Telegram bots, Discord, etc.

### 📧 Notificaciones Automáticas por Email

4 emails automáticos con diseño HTML profesional:

| Email                                  | Cuándo se envía                  |
| -------------------------------------- | -------------------------------- |
| Confirmación de compra con sus números | Al comprador tras pagar          |
| Notificación de nueva venta            | Al admin tras cada venta         |
| Alerta de rifa agotada                 | Al admin cuando se venden todos  |
| Resultado del sorteo                   | Al admin tras ejecutar el sorteo |

### 📤 Exportación de Datos

Descarga toda la información en archivos CSV compatibles con Excel:

- Lista de compradores con detalles de compra
- Todos los boletos generados por rifa
- Historial completo de transacciones

### 🧱 Bloque Gutenberg

Inserta cualquier rifa en páginas o posts con el bloque nativo de Gutenberg. Selecciona la rifa desde el panel lateral y previsualízala directamente en el editor.

### 🌐 Multi-idioma Listo

- **200+ textos traducibles** en toda la interfaz (admin y público)
- Archivo `.pot` incluido — compatible con WPML, Polylang, Loco Translate
- Funciona en cualquier idioma sin tocar código

### ♿ Accesibilidad (WCAG)

- Navegación completa por teclado
- Modales con trap de foco y atributos ARIA
- Compatible con lectores de pantalla
- Cumple estándares de accesibilidad web

---

## 🎨 Experiencia del Comprador

Una interfaz moderna que genera confianza y convierte:

```
┌─────────────────────────────────────────────┐
│                                             │
│         🏆 [Imagen del Premio]              │
│                                             │
│     Countdown Timer en Vivo                 │
│     ██████████████░░░░  72% vendido         │
│                                             │
│  ┌──────┐  ┌──────────┐  ┌──────────────┐  │
│  │ 15   │  │  30      │  │  90          │  │
│  │ $150 │  │  $270 ⭐ │  │  $720        │  │
│  │      │  │ -10%     │  │  -20%        │  │
│  └──────┘  └──────────┘  └──────────────┘  │
│                                             │
│  🏅 Galería de Premios (scroll horizontal) │
│                                             │
│  🔒 Pago Seguro  ✅ Confirmación  🎲 Justo │
│                                             │
│         [ Proceder al Pago ]                │
│                                             │
└─────────────────────────────────────────────┘
```

- **Countdown timer** en vivo que genera urgencia
- **Barra de progreso** animada con shimmer
- **Paquetes como tarjetas** con ribbon "Mejor Valor"
- **Trust badges** que generan confianza
- **50+ Google Fonts** + subida de fuentes custom
- **Galería de premios** horizontal
- **Cupón con validación instantánea** (sin recargar página)
- **Página "Mis Boletos"** — el comprador consulta sus números con su email
- **100% responsive** — perfecto en móvil, tablet y desktop

---

## 🔒 Seguridad de Nivel Bancario

RaffleCore implementa **17 capas de seguridad** — más que la mayoría de plugins premium del mercado:

| Protección                                 | Qué previene                       |
| ------------------------------------------ | ---------------------------------- |
| Prepared Statements (100%)                 | Inyección SQL — imposible          |
| Nonces CSRF en todo formulario             | Falsificación de solicitudes       |
| Capability checks en toda ruta admin       | Acceso no autorizado               |
| Sanitización + escape en todo input/output | Inyección XSS                      |
| CSPRNG (`random_int()`)                    | Sorteos predecibles o manipulados  |
| `FOR UPDATE` + transacciones               | Doble-venta por concurrencia       |
| Anti-colisión Fisher-Yates                 | Boletos duplicados                 |
| Rate limiting con backoff progresivo       | Abuso y ataques de fuerza bruta    |
| Honeypot anti-bot                          | Compras automatizadas por bots     |
| HMAC SHA-256 en webhooks                   | Webhooks falsificados              |
| Hash chain en logs de auditoría            | Alteración de registros            |
| POST + nonce en acciones destructivas      | Eliminaciones accidentales vía URL |
| WooCommerce guard clause                   | Operaciones sin WC activo          |
| Bearer token en API remota                 | Acceso no autorizado a API         |
| REST API con permission callbacks          | Datos expuestos sin permisos       |
| CSV export con auth + nonce                | Descarga de datos sin permiso      |
| Cache-compatible AJAX hydration            | Datos obsoletos con page cache     |

**Ningún plugin de rifas en el mercado ofrece este nivel de seguridad.**

---

## ¿Por Qué RaffleCore es Mejor que la Competencia?

### vs. Plugins gratuitos (WP Starter Raffle, Simple Raffles, etc.)

| Característica             | Plugins gratuitos         | RaffleCore                               |
| -------------------------- | ------------------------- | ---------------------------------------- |
| Pasarela de pago integrada | ❌ Manual / PayPal básico | ✅ Todas las de WooCommerce              |
| Anti-fraude y seguridad    | ❌ Básica o nula          | ✅ 17 capas de seguridad                 |
| Cupones de descuento       | ❌                        | ✅ Porcentaje, fijo, límites, expiración |
| Dashboard analítico        | ❌                        | ✅ 15+ KPIs + 4 gráficas                 |
| Webhooks                   | ❌                        | ✅ 5 eventos + HMAC                      |
| Sorteo criptográfico       | ❌ `mt_rand()` predecible | ✅ `random_int()` imposible de predecir  |
| Reserva pre-pago           | ❌                        | ✅ Con liberación automática             |
| REST API                   | ❌                        | ✅ 6 endpoints                           |
| Tests automatizados        | ❌                        | ✅ 141 assertions                        |
| Escalable a SaaS           | ❌                        | ✅ Interface/Contract ready              |

### vs. Plugins premium ($49-$99) (Starter Lottery, Starter Lottery for WC, etc.)

| Característica             | Plugins premium típicos       | RaffleCore                             |
| -------------------------- | ----------------------------- | -------------------------------------- |
| Arquitectura               | Monolítica, un archivo grande | Modular MVC (8 módulos independientes) |
| Seguridad                  | Nonces + sanitización básica  | 17 capas enterprise-grade              |
| Logs de auditoría          | ❌ o básico sin integridad    | ✅ Hash chain SHA-256 inmutable        |
| Rate limiting              | ❌                            | ✅ Sliding window + backoff progresivo |
| CI/CD                      | ❌                            | ✅ GitHub Actions, PHP 7.4-8.2         |
| Tests                      | ❌ o mínimos                  | ✅ 141 assertions (unit + integración) |
| Preparado para SaaS        | ❌                            | ✅ Interface + Local/Remote providers  |
| Multi-site                 | ❌ o parcial                  | ✅ Nativo con activación per-site      |
| Código abierto y auditable | ❌ Encoded/obfuscado          | ✅ Limpio, documentado, 9,584 LOC      |
| Webhooks con firma         | ❌                            | ✅ HMAC SHA-256                        |
| Soporte i18n completo      | Parcial                       | ✅ 200+ strings, .pot incluido         |

### La Diferencia Real: Arquitectura Profesional

La mayoría de plugins de rifas son archivos PHP monolíticos con queries directas y sin tests. RaffleCore está construido con **patrones de diseño de software profesional**:

```
Plugins típicos:                    RaffleCore:

  raffle-plugin.php (2,000 LOC)      8 módulos independientes
  ├─ queries directas al DB          ├─ Model → Service → Controller
  ├─ sin sanitización consistente    ├─ API Service Layer (SaaS-ready)
  ├─ sin tests                       ├─ 19 índices optimizados
  ├─ mt_rand() para sorteos          ├─ 141 tests automatizados
  └─ sin logs ni auditoría           └─ Hash chain inmutable + webhooks
```

---

## 🚀 Escalabilidad: Crece con Tu Negocio

### Nivel 1 — Emprendimiento Local

> Una persona vendiendo rifas desde su sitio WordPress

RaffleCore funciona **out of the box**. Instala, crea tu rifa y empieza a vender. Sin configuración técnica.

### Nivel 2 — Negocio Establecido

> Múltiples rifas simultáneas, cientos de compradores

- Dashboard analítico para tomar decisiones basadas en datos
- Cupones para campañas de marketing
- Webhooks para conectar con tu CRM, Telegram o Discord
- Exportación CSV para reportes fiscales
- 19 índices de BD optimizados para consultas rápidas con alto volumen

### Nivel 3 — Agencia o Multi-Sitio

> Varias marcas, cada una con sus propias rifas

- **WordPress Multisite** nativo — cada sitio tiene su BD independiente
- Un solo plugin, múltiples sitios, datos aislados
- Panel de administración independiente por sitio

### Nivel 4 — Plataforma SaaS

> Ofrecer rifas como servicio a terceros

RaffleCore es el **único plugin de rifas** con arquitectura SaaS-ready:

```
┌─────────────────────────────────────────────────────┐
│          Hoy (Nivel 1-3)                            │
│                                                     │
│   WordPress + RaffleCore (modo local)               │
│   Todo funciona con la BD de WordPress              │
│                                                     │
├─────────────────────────────────────────────────────┤
│          Mañana (Nivel 4 — SaaS)                    │
│                                                     │
│   Cambias UNA constante: RAFFLECORE_MODE = 'api'    │
│                                                     │
│   WordPress Sites ──→ RaffleCore API ──→ BD Central │
│   (frontend)           (tu servidor)     (todos los │
│                        (Laravel/Node)     clientes) │
│                                                     │
│   Tu módulos NO cambian. Solo cambia el provider.   │
└─────────────────────────────────────────────────────┘
```

**No necesitas reescribir nada.** La arquitectura Interface/Contract ya está implementada con 18 métodos contractuales. Solo necesitas construir el backend API y cambiar una constante.

### Capacidad Técnica Probada

| Escenario            | Capacidad                                   |
| -------------------- | ------------------------------------------- |
| Rifas simultáneas    | Sin límite (índices + paginación)           |
| Boletos por rifa     | 100,000+ (generación pool-based, no loops)  |
| Compras concurrentes | Protegidas con `FOR UPDATE` + transacciones |
| Compradores en BD    | 100K+ con queries <50ms (19 índices)        |
| Reservas expiradas   | Limpieza automática por cron cada 15 min    |

---

## 📋 Ficha Técnica

| Especificación      | Detalle                                    |
| ------------------- | ------------------------------------------ |
| Versión             | 3.2.0 (DB v3.3.0)                          |
| PHP requerido       | 7.4+ (compatible hasta 8.2)                |
| WordPress requerido | 5.8+                                       |
| WooCommerce         | Requerido (cualquier versión moderna)      |
| Tablas de BD        | 6 tablas propias con 19 índices            |
| Tamaño del código   | 9,584 líneas (PHP + JS + CSS)              |
| Archivos            | 55                                         |
| Módulos             | 8 independientes                           |
| Tests automatizados | 141 assertions (0 fallos)                  |
| CI/CD               | GitHub Actions (PHP 7.4, 8.0, 8.1, 8.2)    |
| Idiomas             | Multi-idioma ready (.pot con 200+ strings) |
| Licencia            | GPL-2.0+                                   |

---

## Módulos Incluidos

| Módulo          | Descripción                                                                               |
| --------------- | ----------------------------------------------------------------------------------------- |
| **Raffle**      | Crear, editar, gestionar rifas con paquetes, precios, galería de premios y fuentes custom |
| **Ticket**      | Generación criptográfica de boletos con anti-colisión garantizada                         |
| **Purchase**    | Gestión de compras con reserva pre-pago y liberación automática                           |
| **Draw**        | Sorteo verificable con `random_int()` + transacción atómica + logs                        |
| **Email**       | 4 plantillas HTML automáticas (comprador + admin)                                         |
| **WooCommerce** | Integración completa: productos virtuales, checkout, pagos, cancelaciones                 |
| **Coupon**      | Cupones de descuento con 6 tipos de restricción                                           |
| **Webhook**     | 5 eventos con firma HMAC para integraciones externas                                      |

---

## Casos de Uso

### 🎁 Rifas de Productos

Vende boletos para sortear iPhones, autos, experiencias o cualquier producto. Muestra fotos del premio con la galería integrada y genera urgencia con el countdown timer.

### 🏠 Rifas Inmobiliarias

Sorteos de casas o apartamentos con tickets de alto valor. La seguridad anti-fraude y los logs inmutables generan la confianza que estos sorteos de alto valor necesitan.

### 🎗️ Recaudación de Fondos

ONGs y fundaciones pueden vender boletos para recaudar fondos. Los cupones permiten ofrecer descuentos a donadores recurrentes y los CSV facilitan la rendición de cuentas.

### 🏢 Empresas y Agencias

Agencias de marketing pueden ofrecer rifas como servicio a sus clientes usando Multisite o expandiendo a SaaS. Cada cliente tiene su panel independiente.

### 🎮 Comunidades Gaming / Streaming

Streamers y comunidades pueden crear rifas conectadas a Discord o Telegram vía webhooks. El sorteo criptográfico garantiza transparencia ante la comunidad.

---

## Resumen: ¿Por Qué Elegir RaffleCore?

```
 ✅ Integración WooCommerce nativa — paga con cualquier pasarela
 ✅ 17 capas de seguridad — nivel bancario
 ✅ Dashboard con 15+ KPIs — decisiones basadas en datos
 ✅ 141 tests automatizados — código confiable
 ✅ Sorteo criptográfico — transparente y verificable
 ✅ Cupones inteligentes — impulsa ventas
 ✅ Webhooks firmados — conecta con cualquier sistema
 ✅ Logs inmutables — auditoría total
 ✅ Multi-idioma — vende en cualquier país
 ✅ Multisite ready — escala a múltiples marcas
 ✅ Arquitectura SaaS — crece a plataforma
 ✅ Código limpio y auditable — 9,584 LOC profesionales
 ✅ 0 dependencias externas — solo WordPress + WooCommerce
```

**RaffleCore no es solo un plugin. Es la base tecnológica para construir un negocio de rifas profesional, seguro y escalable.**

---

_RaffleCore v3.2.0 — WordPress Raffle System — Enterprise Production-Ready_
