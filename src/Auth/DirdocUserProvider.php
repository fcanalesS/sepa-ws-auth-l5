<?php namespace UTEM\Dirdoc\Auth;

use Illuminate\Contracts\Auth\User as UserContract;
use Illuminate\Contracts\Auth\UserProvider as UserProviderInterface;
use Illuminate\Contracts\Auth\Authenticatable;
use GuzzleHttp;

class DirdocUserProvider implements UserProviderInterface
{

    /**
     * El modelo Eloquent
     *
     * @var string
     */
    protected $model;

    /**
     * La uri base del servicio REST
     *
     * @var string
     */
    protected $rest_base_uri;

    /**
     * Las credenciales del servicio REST
     *
     * @var array
     */
    protected $rest_credentials;

    /**
     * Crea el proveedor de usuario
     *
     * @param string $model
     * @return void
     */
    public function __construct($model)
    {
        $this->model = $model;
        $this->rest_base_uri = env('DIRDOC_REST_BASE_URI', 'https://sepa.utem.cl/autenticador-dirdoc-ws/api/rest/');
        $this->rest_credentials = array(
            env('DIRDOC_REST_USERNAME', '1111-1'),
            env('DIRDOC_REST_PASSWORD', '1111-1')
        );
    }

    /**
     * Trae un usuario por credenciales
     *
     * @param array $credentials
     * @return \Illuminate\Contracts\Auth\Authenticatable
     */
    public function retrieveByCredentials(array $credentials)
    {
        if (!\UTEM\Utils\Rut::isRut($credentials['rut'])) {
            return null; // Si el rut es invalido nos negamos a autenticar
        }
        $rut = \UTEM\Utils\Rut::rut($credentials['rut']);

        return $this->createModel()->firstOrCreate(['rut' => $rut]);
    }

    /**
     * Checkea la validez de las credenciales del usuario
     *
     * @param \Illuminate\Contracts\Auth\Authenticatable $user
     * @param array $credentials
     * @return bool
     */
    public function validateCredentials(Authenticatable $user, array $credentials)
    {
        $client = new GuzzleHttp\Client(['base_uri' => $this->rest_base_uri, 'auth' => $this->rest_credentials, 'verify' => false]); // Tenemos https, pero no hay certificados "validos"

        // Obtenemos las credenciales del usuario
        $rut = $credentials['rut']; // TODO: Una mejor forma de obtener los identificadores?
        $password = hash('sha256', strtoupper($credentials['password'])); // TODO: Para esto tambien ...

        try {
            $req = $client->get(sprintf('autenticar/%s/%s', $rut, $password)); // Hacemos la peticion al WS
        } catch (GuzzleHttp\Exception\ClientException $e) { // Si los errores son del nivel 400, se lanza esta excepcion
            $msg = 'Error al consultar el servicio: %d(%s)';
            \Log::error(sprintf($msg, $e->getResponse()->getStatusCode(), $e->getResponse()->getReasonPhrase()));
            return false;
        }

        $data = json_decode($req->getBody(), true);
        $respuesta = $data['resultado'];
        if ($respuesta) \Log::info(sprintf('Auth: Login exitoso (%s)', $rut));
        else \Log::info(sprintf('Auth: Login fallido (%s)', $rut));

        return (bool)$respuesta;
    }

    /**
     * Trae un usuario por id (rut)
     *
     * @param string $identifier
     * @return \Illuminate\Contracts\Auth\Authenticatable
     */
    public function retrieveById($identifier)
    {
        $rut = \UTEM\Utils\Rut::isRut($identifier) ? \UTEM\Utils\Rut::rut($identifier) : $identifier; // Solo queremos el rut
        \Log::debug(sprintf('Auth: Logeando al rut "%s" mediante id', $rut));
        return $this->createModel()->firstOrCreate(['rut' => $rut]); // Si o si creamos un usuario, ya que no hay forma de saber si el user esta en dirdoc
    }

    /**
     * Trae a un usuario usando su token de "remember me" e identificador
     *
     * @param string $identifier
     * @param string $token
     * @return \Illuminate\Contracts\Auth\Authenticatable|null
     */
    public function retrieveByToken($identifier, $token)
    {
        $model = $this->createModel();
        $rut = \UTEM\Utils\Rut::isRut($identifier) ? \UTEM\Utils\Rut::rut($identifier) : $identifier;
        \Log::info(sprintf('Auth: Intentando login por token (%s: %s)', $rut, $token));
        return $model->newQuery()
            ->where($model->getKeyName(), $rut)
            ->where($model->getRememberTokenName(), $token)
            ->first();
    }

    /**
     * Actualiza el token "remember me"
     *
     * @param Authenticatable $user
     * @param string $token
     * @return void
     */
    public function updateRememberToken(Authenticatable $user, $token)
    {
        $user->setRememberToken($token);
        $user->save();
    }

    /**
     * Crea una nueva instancia del modelo
     *
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function createModel()
    {
        $class = '\\' . ltrim($this->model, '\\');
        return new $class;
    }
}
