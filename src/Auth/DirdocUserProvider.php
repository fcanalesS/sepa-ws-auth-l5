<?php namespace UTEM\Dirdoc\Auth;

use Illuminate\Contracts\Auth\User as UserContract;
use Illuminate\Contracts\Auth\UserProvider as UserProviderInterface;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Auth\GenericUser;
use GuzzleHttp;

class DirdocUserProvider implements UserProviderInterface
{

    protected $model; // Un string de perros indicando cual es el modelo usado. ej: App\User
    protected $ws_base_uri;
    protected $ws_credentials;


    public function __construct($model)
    {
        $this->model = $model;
        $this->ws_base_uri = env('DIRDOC_WS_BASE_URI', 'https://sepa.utem.cl/autenticador-dirdoc-ws/api/rest/');
        $this->ws_credentials = array(
            env('DIRDOC_REST_USERNAME', '1111-1'),
            env('DIRDOC_REST_PASSWORD', '1111-1')
        );
    }

    public function retrieveByCredentials(array $credentials)
    {
        $query = $this->createModel()->newQuery();

        foreach ($credentials as $key => $value) {
            if (!str_contains($key, 'password')) {
                if (str_contains($key, 'rut')) $value = \UTEM\Utils\Rut::rut($value);
                $query->where($key, $value);
            }
        }
        $user = $query->first();
        if (!$user) $user = $this->getGenericUser(['id' => $credentials['rut']]);

        return false;
        return $user;
    }

    public function validateCredentials(Authenticatable $user, array $credentials)
    {
        $client = new GuzzleHttp\Client(['base_uri' => $this->ws_base_uri, 'auth' => $this->ws_credentials, 'verify' => false]); // Tenemos https, pero no hay certificados "validos"

        // Obtenemos las credenciales del usuario
        $rut = $credentials['rut']; // TODO: Una mejor forma de obtener los identificadores?
        $password = hash('sha256', strtoupper($credentials['password'])); // TODO: Para esto tambien ...

        try {
            $req = $client->get(sprintf('autenticar/%s/%s', $rut, $password)); // Hacemos la peticion al WS
        } catch (GuzzleHttp\Exception\ClientException $e) { // Si los errores son del nivel 400, se lanza esta excepcion
            $msg = 'Error al consultar el servicio: %d';
            $http_code = $e->getResponse()->getStatusCode();
            if ($http_code == 403) $msg = 'Error de autenticacion (HTTP %d), verifica tus credenciales';
            \Log::error(sprintf($msg, $http_code));
            return false;
        }

        $data = json_decode($req->getBody(), true);
        $respuesta = $data['resultado'];

        if (is_a($user, '\Illuminate\Auth\GenericUser')) // Nos llego un GenericUser, persistamos al usuario en DB
        {
            $user = $this->createModel(); // Nueva instancia del modelo
            $rut = \UTEM\Utils\Rut::rut($rut); // Pasa el rut a integer
            $user->rut = $rut;
            $user->save();
        } // Si no es un GenericUser, implica que ya estaba en DB, continuamos ...

        return (bool)$respuesta;
    }

    public function retrieveById($identifier)
    {
        $user = $this->createModel();
        $rut = \UTEM\Utils\Rut::isRut($identifier) ? \UTEM\Utils\Rut::rut($identifier) : $identifier; // Solo queremos el rut
        \Log::debug(sprintf('Auth: Logeando al rut "%s" mediante id'));
        return $user->firstOrCreate(['rut' => $rut]);
    }

    public function retrieveByToken($identifier, $token)
    {

    }

    public function updateRememberToken(Authenticatable $user, $token)
    {

    }

    public function createModel()
    {
        $class = '\\' . ltrim($this->model, '\\');
        return new $class;
    }

    public function getGenericUser($user)
    {
        if ($user !== null) {
            return new GenericUser((array)$user);
        }
    }
}
