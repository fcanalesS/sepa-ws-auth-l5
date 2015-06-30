# sepa-ws-auth-l5

Proyecto para dar soporte de autenticación usando el [autenticador dirdoc](https://sepa.utem.cl/autenticador-dirdoc-portal) en Laravel 5.

## Instalación

La libreria esta en [packagist](https://packagist.org/packages/utem/dirdoc-auth), usamos composer para instalarla:

~~~bash
composer require utem/dirdoc-auth
~~~

## Registrarlo en los providers

Se necesita registrar el paquete en laravel, para esto agregamos lo siguiente al fichero `config/app.php`:

~~~php
    'providers' => [
    ...
    'UTEM\Dirdoc\Auth\DirdocAuthServiceProvider',
    ...
~~~

## Crear la tabla de usuarios

El paquete utiliza una tabla de usuarios para mantener registro de los usuarios loggeados con el servicio REST.
Se incluye una migración para esto (La tabla debe tener `rut` como llave primaria).

~~~bash
php artisan vendor:publish --provider="UTEM\Dirdoc\Auth\DirdocAuthServiceProvider"
php artisan migrate
~~~

## Crear el modelo

Se usa un modelo Eloquent, este es mapeado a la tabla creada en el paso anterior, por supuesto que se incluye un modelo.

~~~bash
php artisan make:model --no-migration Models/Usuario
~~~

Ahora modificamos el modelo creado, para heredar de `\UTEM\Dirdoc\Auth\Models\DirdocWSUser`:

~~~php
<?php namespace App\Models;


class Usuario extends \UTEM\Dirdoc\Auth\Models\DirdocWSUser {
}

~~~

## Cambiar el driver de autenticación

Con el paquete registrado, procedemos a cambiar el driver de autenticacion y el modelo a usar, cambiamos `config/auth.php`:

~~~php
...
'driver' => 'dirdoc',
...
'model' => 'App\Models/Usuario',
...
~~~

## Configurar las credenciales del servicio

Debemos ingresar las credenciales del servicio, para esto agregamos lo siguiente a nuestro `.env`:

~~~bash
DIRDOC_REST_USERNAME=USUARIOENAUTENTICADOR
DIRDOC_REST_PASSWORD=PASSWORDENAUTENTICADOR
~~~

## Probando

Para hacer una prueba, usamos tinker:

~~~php
>>> Auth::attempt(['rut' => '12345678-5', 'password' => 'passworddirdoc])
~~~

Voila!.
