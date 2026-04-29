# 🏎️ TicoAutos - Backend (Laravel 10 + MongoDB)

## 📌 Descripción del Proyecto
El núcleo y API REST de **TicoAutos**. Proporciona toda la lógica de negocio, seguridad, autenticación, comunicación y persistencia de datos para la plataforma de compra y venta de vehículos.

## ⚙️ Tecnologías Utilizadas
- **Framework:** Laravel 10 (PHP)
- **Base de Datos:** MongoDB (utilizando el paquete `jenssegers/mongodb`)
- **Autenticación:** JWT (JSON Web Tokens) mediante `tymon/jwt-auth`
- **Integraciones de Terceros:** Twilio (SMS 2FA), Google OAuth (Inicio de sesión), OpenRouter/OpenAI (Moderación de chats con Inteligencia Artificial)

## 🔄 Flujo de Trabajo y Arquitectura
El backend expone endpoints REST consumidos principalmente por el Frontend en Vue.js.

1.  **Modelos (`app/Models/`)**: Mapean colecciones en MongoDB (`User`, `Vehicle`, `Message`, `Conversation`).
2.  **Controladores (`app/Http/Controllers/`)**: Contienen el corazón lógico del sistema:
    - `AuthController`: Orquesta todo el flujo de registro e inicio de sesión. Maneja la creación de URLs para Google OAuth, activación de correos a través de un mailable (`VerifyUserAccount`) y controla la generación/validación de códigos SMS 2FA.
    - `MessageController`: Maneja la creación de conversaciones de chat entre compradores y vendedores.
    - `VehicleController`: Encargado del CRUD de los vehículos.
3.  **Rutas (`routes/api.php`)**: Expone la interfaz de comunicación. Rutas privadas son protegidas eficientemente por el middleware `auth:api`.

### 🛡️ Seguridad y Moderación (IA)
- **Doble Factor de Autenticación (2FA):** Diseñado con un flujo en dos fases para registros manuales. Tras verificar el correo, el servidor dispara de forma autónoma una petición a la API HTTP de Twilio para enviar un código de 6 dígitos que el usuario debe ingresar en el cliente.
- **Moderación Inteligente (OpenRouter):** Un interceptor de seguridad integrado en `MessageController`. Antes de guardar un mensaje de chat en la base de datos, el backend lo envía al modelo de IA `gpt-4o-mini`. Si la IA detecta intentos de proporcionar información de contacto externa (correos, teléfonos), instruye al backend para abortar el envío con un código 403 HTTP.

## 🚀 Configuración e Instalación

### Requisitos previos
- PHP 8.1+
- Composer
- MongoDB en ejecución (puerto 27017)

### 1. Instalar dependencias
```bash
composer install
```

### 2. Variables de entorno
Copia `.env.example` a `.env` y configura tus variables críticas:
- Base de datos (`DB_CONNECTION=mongodb`, `MONGODB_URI`).
- Llave secreta (`JWT_SECRET`).
- Twilio API (`TWILIO_SID`, `TWILIO_AUTH_TOKEN`, `TWILIO_SERVICE_SID`).
- Google OAuth (`GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`).
- OpenAI/OpenRouter (`OPENROUTER_API_KEY`).

### 3. Iniciar el servidor
```bash
php artisan serve
```
El servidor escuchará en `http://localhost:8000`.
