<?php


namespace LaminasGOT\Service;


use DateTime;
use Exception;
use GraphicObjectTemplating\OObjects\ODContained;
use GraphicObjectTemplating\OObjects\OObject;
use GraphicObjectTemplating\OObjects\OSContainer;
use http\Exception\InvalidArgumentException;
use JsonException;
use Laminas\ServiceManager\ServiceManager;
use Laminas\Session\Container;
use MongoDB\Driver\Exception\RuntimeException;
use UnexpectedValueException;

/**
 * class ObjectsManager
 * gestionnaire des objets manipulé par G.O.T
 *
 * attributs
 * ---------
 *
 * serviceManager   ServiceManager de Laminas
 * config           tableau des valeurs de configuration de l'application
 * session          Container de Session Laminas nommé 'GOT'
 * lastAccess       date et heure dernière opération faite
 *
 * méthodes
 * --------
 * __construct($sm, $cfg, $sc)
 * validate_objs_manager(): bool
 * exist_object(string $id): bool
 * add_object(OObject $obj): bool
 * save_object(OObject $obj): bool
 * restore_object(string $id): OObject
 * remove_object(OObject $obj): bool
 * clear_objects(): bool
 * build_obj_from_json(string $json): OObject
 * obj_to_json(OObject $obj): string
 */

class ObjectsManager
{
    const FORMAT_YMDHIS = "Y-m-d H:i:s";

    /** @var ServiceManager $serviceManager */
    private $serviceManager;
    /** @var array $config */
    private $config;
    /** @var Container $session */
    private $session;

    /**
     * ObjectsManager constructor.
     * @param $sm   ServiceManager
     * @param $cfg  array Configuration values
     * @param $sc   Container of Session name GOT
     */
    public function __construct($sm, $cfg, $sc)
    {
        $this->serviceManager = $sm;
        $this->config = $cfg;
        $this->session = $sc;
        $this->lastAccess = (new DateTime())->format(self::FORMAT_YMDHIS);
    }

    /**
     * @return bool         true if objects are removed, else false
     * @throws Exception    if error has occurred
     */
    public function validate_objs_manager(): bool
    {
        $now        = new DateTime();
        $lastAccess = new DateTime($this->session->lastAccess);
        $retour     = true;

        if ($lastAccess) {
            $interval = $lastAccess->diff($now);
            # suppression de tous les objets si dernier accès date de plus de 2h
            if ((int)$interval->format('%h') > 2) {
                $this->session->objects = [];
                $retour = false;
            }
        }
        $this->session->lastAccess = (new DateTime())->format(self::FORMAT_YMDHIS);
        return $retour;
    }

    /**
     * @param string $id    ident of object to search
     * @return bool         return result of array_key_exists $id as key in list of objects
     */
    public function exist_object(string $id): bool
    {
        $this->validate_objs_manager();
        $objects = $this->session->objects ?? [];
        $this->session->lastAccess = (new DateTime())->format(self::FORMAT_YMDHIS);
        return array_key_exists($id, $objects);
    }

    /**
     * @param OObject $obj  object to add
     * @return bool         true if add can be done
     * @throws Exception    object already exist
     */
    public function add_object(OObject $obj): bool
    {
        $this->validate_objs_manager();
        $objects = $this->session->objects;
        $tmpObj = null;
        if (!$this->exist_object($obj->id)) {
            if ($obj instanceof OSContainer) {
                $tmpObj = clone $obj;
                if (count($tmpObj->children)) {
                    $children = $tmpObj->children;
                    foreach ($children as $idChild => $child) {
                        if (!$this->exist_object($idChild)) {
                            $this->add_object($child);
                        } else {
                            $this->save_object($child);
                        }

                        $children[$idChild] = $idChild;
                    }
                    $properties = $tmpObj->properties;
                    $properties['children'] = $children;
                    $tmpObj->properties = $properties;
                }
            } elseif (!($obj instanceof ODContained)) {
                throw new UnexpectedValueException("Objet ".$obj->id." de type inconu");
            }
            $objects[$obj->id] = ($tmpObj) ? serialize($tmpObj->properties) : serialize($obj->properties);
            $this->session->objects = $objects;
            $this->session->lastAccess = (new DateTime())->format(self::FORMAT_YMDHIS);
            return true;
        }
        throw new UnexpectedValueException("Objet ".$obj->id." déjà sauvegardé");
    }

    /**
     * @param OObject $obj  object to update
     * @return bool         true if update can be done
     * @throws Exception    object doesn't exist
     */
    public function save_object(OObject $obj): bool
    {
        $this->validate_objs_manager();
        $objects = $this->session->objects;
        $tmpObj = null;
        if ($this->exist_object($obj->id)) {
            if ($obj instanceof OSContainer) {
                $tmpObj = clone $obj;
                if (count($tmpObj->children)) {
                    $children = $tmpObj->children;
                    foreach ($children as $idChild => $child) {
                        $this->save_object($child);
                        $children[$idChild] = $idChild;
                    }
                    $tmpObj->children = $children;
                }
            } elseif (!($obj instanceof ODContained)) {
                throw new InvalidArgumentException("Objet ".$obj->id." de type inconu");
            }
        } else {
            throw new UnexpectedValueException("Objet ".$obj->id." inconnu en session");
        }
        $objects[$obj->id] = ($tmpObj) ? serialize($tmpObj->properties) : serialize($obj->properties);
        $this->session->objects = $objects;
        $this->session->lastAccess = (new DateTime())->format(FORMAT_YMDHIS);

        return true;
    }

    /**
     * @param string $id    ident of object to return
     * @return OObject      object return
     * @throws Exception    object doesn't exist
     */
    public function restore_object(string $id): OObject
    {
        $this->validate_objs_manager();
        $objects = $this->session->objects;
        if (!$this->exist_object($id)) {
            throw new UnexpectedValueException("Objet ".$id." inconnu");
        }
        $properties = unserialize($objects[$id],  ['allowed_classes' => false]);
        $obj = new $properties['className']($id);
        $obj->properties = $properties;

        if ($obj instanceof OSContainer) {
            $children = $obj->children;
            foreach ($children as $idChild => $child) {
                $children[$idChild] = $this->restore_object($idChild);
            }
            $obj->children  = $children;
        }
        $this->session->lastAccess = (new DateTime())->format(FORMAT_YMDHIS);
        return $obj;
    }

    /**
     * @param OObject|string $id  object to remove (by id or objet itself)
     * @return bool         true if remove can be donne
     * @throws Exception    object doesn't exist
     */
    public function remove_object($id): bool
    {
        $this->validate_objs_manager();
        if ($id instanceof OObject) {
            $obj = $id;
            $id = $id->id;
        } else {
            $obj = $this->restore_object($id);
        }
        $objects = $this->session->objects;
        if (!$this->exist_object($id)) {
            throw new UnexpectedValueException("Objet ".$id." inconnu en session");
        }

        if ($obj instanceof OSContainer) {
            foreach ($obj->children as $idChild => $child) {
                $this->remove_object($idChild);
            }
        }

        unset($objects[$id]);
        $this->session->objects = $objects;
        $this->session->lastAccess = (new DateTime())->format(FORMAT_YMDHIS);
        return true;
    }

    /**
     * @return bool true if all objects are removed
     */
    public function clear_objects(): bool
    {
        $this->session->objects = [];
        $this->session->lastAccess = (new DateTime())->format(FORMAT_YMDHIS);
        return true;
    }

    /**
     * @param string $json  JSON representation of an object
     * @return OObject      returned object
     * @throws Exception    if JSON doesn't have 'id' key or JSON_THROW_ON_ERROR
     */
//    public function build_obj_from_json(string $json): OObject
//    {
//        $properties = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
//        if (array_key_exists('id', $properties)) {
//            return new OObject($properties['id'], $properties);
//        }
//        throw new Exception("éfinition JSON d'objet dans identifiant");
//    }

    /**
     * @param OObject $obj      object to transform
     * @return false|string     false if error has occured, else string
     * @throws JsonException    if error has occured
     */
//    public function obj_to_json(OObject $obj): string
//    {
//        return json_encode($obj, JSON_THROW_ON_ERROR);
//    }

//    public function get_objects()
//    {
//        $this->validate_objs_manager();
//        return $this->session->objects;
//    }
}