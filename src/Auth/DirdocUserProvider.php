<?php namespace UTEM\Dirdoc\Auth;

use Illuminate\Contracts\Auth\User as UserContract;
use Illuminate\Contracts\Auth\UserProvider as UserProviderInterface;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Auth\GenericUser;
use GuzzleHttp;

class DirdocUserProvider implements UserProviderInterface {

    protected $model; // Un string de perros indicando cual es el modelo usado. ej: App\User
    protected $ws_base_uri;
    protected $ws_credentials;


    public function __construct($model)
    {
        $this->model = $model;
        $this->ws_base_uri = env('DIRDOC_WS_BASE_URI', 'https://sepa.utem.cl/saap-rest/api/');
        $this->ws_credentials = array(
            env('DIRDOC_WS_USERNAME', '1111-1'),
            env('DIRDOC_WS_PASSWORD', '1111-1')
        );
    }

    public function retrieveByCredentials(array $credentials)
    {
        $query = $this->createModel()->newQuery();

        foreach ($credentials as $key => $value)
        {
	    if ( ! str_contains($key, 'password'))
	    {
                if (str_contains($key, 'rut')) $value = \UTEM\Utils\Rut::rut($value);
	        $query->where($key, $value);
            }
        }
        $user = $query->first();
        if(!$user) $user = $this->getGenericUser(['id' => $credentials['rut']]);
        return $user;
    }

    public function validateCredentials(Authenticatable $user, array $credentials)
    {
        $client = new GuzzleHttp\Client(['base_uri' => $this->ws_base_uri, 'auth' => $this->ws_credentials, 'verify' => false]); // Tenemos https, pero no hay certificados "validos"

        // Obtenemos las credenciales del usuario
        $rut = $credentials['rut']; // TODO: Una mejor forma de obtener los identificadores?
        $password = hash('sha256', strtoupper($credentials['password'])); // TODO: Para esto tambien ...

        $req = $client->get(sprintf('autenticar/%s/%s', $rut, $password)); // Hacemos la peticion al WS
        $data = json_decode($req->getBody(), true);
        $respuesta = $data['respuesta'];
        
        if(is_a($user, '\Illuminate\Auth\GenericUser')) // Nos llego un GenericUser, persistamos al usuario en DB
        {
            $user = $this->createModel(); // Nueva instancia del modelo
            $req = $client->get(sprintf('fichaEstudiante/%s', $rut)); // Obtenemos los datos desde la fichaEstudiante // TODO: pq fichaEstudiante tiene datos de docentes?
            $data = json_decode($req->getBody(), true);
            $rut = \UTEM\Utils\Rut::rut($rut); // Pasa el rut a integer
            $user->rut = $rut;
            $user->nombres = $data['nombres'];
            $user->apellidos = $data['apellidos'];
            $user->email = $data['email'];
            $user->save();
        } // Si no es un GenericUser, implica que ya estaba en DB, continuamos ...

        return (bool) $respuesta;
    }

    public function retrieveById($identifier)
    {

    }

    public function retrieveByToken($identifier, $token)
    {

    }

    public function updateRememberToken(Authenticatable $user, $token)
    {

    }

    public function createModel()
    {
        $class = '\\'.ltrim($this->model, '\\');
        return new $class;
    }

    public function getGenericUser($user)
    {
        if ($user !== null)
        {
            return new GenericUser((array) $user);
        }
    }
}
