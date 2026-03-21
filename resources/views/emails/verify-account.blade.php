<x-mail::message>
# ¡Hola, {{ $user->username }}! 🏎️

Estamos muy emocionados de que te unas a **TicoAutos**. Para empezar a publicar tus anuncios o preguntar por vehículos, necesitamos que actives tu cuenta.

Es súper fácil, solo dale clic al botón de abajo:

<x-mail::button :url="$url">
Activar mi cuenta de TicoAutos
</x-mail::button>

*Nota: Si tú no creaste esta cuenta, simplemente ignora este correo.*

Gracias,<br>
**El equipo de TicoAutos**
</x-mail::message>
