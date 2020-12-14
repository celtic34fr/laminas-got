<?php

namespace LaminasGOT\Controller;

use Exception;
use GraphicObjectTemplating\OObjects\ODContained;
use GraphicObjectTemplating\OObjects\ODContained\ODDragNDrop;
use GraphicObjectTemplating\OObjects\OSContainer\OSForm;

use GraphicObjectTemplating\OObjects\OObject;
use UnexpectedValueException;
use LaminasGOT\Service\ObjectsManager;
use LaminasGOT\Service\LF3GotServices;

use Laminas\Http\Headers;
use Laminas\Http\Request;
use Laminas\Http\Response;
use Laminas\Http\Response\Stream;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\ServiceManager\ServiceManager;
use Laminas\Session\Container;

class MainController extends AbstractActionController
{
    const MODE_GEN_HTML = ['append', 'appendAfter', 'appendBefore', 'update', 'innerUpdate'];
    const MODE_EXEC_JS = ['exec', 'execID'];
    const MODE_REDIRECT = ['redirect'];
    const MODE_NO_OPERATE = ['nop'];
    const MODE_NO_UPDATE = ['noUpdate'];

    const ERR_UNEXPECTED_VALUE_MSG = 'Unexpected value';

    /** @var ServiceManager $serviceManager */
    private $serviceManager;
    /** @var LF3GotServices $gotServices */
    private $gotServices;
    /** @var ObjectsManager $objectsManager */
    private $objectsManager;

    /**
     * MainController constructor.
     * @param ServiceManager $serviceManager
     * @param LF3GotServices $gotServices
     * @param ObjectsManager $objectsManager <
     */
    public function __construct(ServiceManager $serviceManager, LF3GotServices $gotServices, ObjectsManager $objectsManager)
    {
        $this->serviceManager = $serviceManager;
        $this->gotServices = $gotServices;
        $this->objectsManager = $objectsManager;
    }

    /* méthode appelé pour l'exécution des demandes Ajax */
    /**
     * @return bool|Response
     * @throws Exception
     */
    public function gotDispatchAction()
    {
        /** @var Request $request */
        $request = $this->getRequest();
        /** @var Response $response */
        $response = $this->getResponse();
        $dataZC = null;

        if ($request->isPost()) { // récupération des paramètres d'appel
            $params = $request->getPost()->toArray();


            if (!empty($params['id']) && (null !== $params['id'])) {
                /** @var OObject $callingObj */
                try {
                    $callingObj = $this->objectsManager->restore_object($params['id']);
                } catch (Exception $e) {
                    $callingObj = false;
                }
                $object = $callingObj;

                if (!$callingObj) {
                    // si retour false de la création d'objet => redirection sur la route home de l'application
                    $results = [$this->gotServices->formatRetour($params['id'], $params['id'], 'redirect', '/')];
                } else {
//                    if (array_key_exists('zoneComm', $params)) {
//                        OObject::setZoneComm($params['zoneCommName'], $params['zoneCommData']);
//                        unset($params['zoneCommName']);
//                        unset($params['zoneCommData']);
//                    }

                    if (isset($callingObj->dispatchEvents)) {
                        $results = call_user_func_array([$callingObj, 'dispatchEvents'], [$this->serviceManager, $params]);
                    } else {
                        if (method_exists($callingObj, 'dispatchEvents')) {
                            $results = call_user_func_array([$callingObj, 'dispatchEvents'], [$this->serviceManager, $params]);
                        } else {
                            $event = $callingObj->getEvent($params['event']);
                            if ($event['class'] != $callingObj->className) {
                                $object = new $event['class']();
                            }
                            $objMethod = $event['method'];

                            // traitement en cas de formulaire
                            if (array_key_exists('form', $params)) {
                                $params['form'] = $this->stringToArray($params['form']);
                                if ($callingObj instanceof ODContained && !empty($callingObj->form)) {
                                    /** @var OSForm $form */
                                    $form = $this->objectsManager->restore_object($callingObj->form);
                                    $hiddens = $form->hidden;
                                    if (is_array($params["form"])) {
                                        $params['form'] = array_merge($params['form'], $hiddens);
                                    }
                                }
                            }
                            // appel de la méthode de l'objet passée en paramètre
                            $results = call_user_func_array([$object, $objMethod], [$this->serviceManager, $params]);
                        }
                    }
                }
                /** acquisition information zone de communication */
//                $zoneComm   = OObject::getZoneComm();
//                $nameZC     = $zoneComm['name'];
//                $dataZC     = $zoneComm['data'];

                $result = [];
                $rscs = [];
                $updDatas = [];
                $operate = true;
                $update = true;
                $insert = true;
                foreach ($results as $rlst) {
                    $html = "";
                    switch (true) {
                        case (in_array($rlst['mode'], self::MODE_NO_OPERATE)):
                            $operate = false;
                            $insert = false;
                            break;
                        case (in_array($rlst['mode'], self::MODE_GEN_HTML)):
                            $objet = $this->objectsManager->restore_object($rlst['idSource']);
                            $html = !empty($rlst['code']) ? $rlst['code'] : $this->gotServices->render_html($objet);
                            $rscs = $this->gotServices->search_rscs($objet);
                            $insert = true;
                            break;
                        case (in_array($rlst['mode'], self::MODE_EXEC_JS)):
                            $html = !empty($rlst['code']) ? $rlst['code'] : '';
                            $insert = true;
                            break;
                        case (in_array($rlst['mode'], self::MODE_REDIRECT)):
                            $html = !empty($rlst['code']) ? $rlst['code'] : '';
                            $insert = true;
                            $update = false;
                            // Stockage temporaire en session de la zone de communication si existe
                            if (!empty($dataZC)) {
                                $sessionStorageSession = new Container('sessionStorage');
                                $datas = [];
                                $datas['data'] = $dataZC;
                                $datas['lastAccess'] = (new \DateTime('now'))->format("YmdHis");
                                $sessionStorageSession->offsetSet($nameZC, $datas);
                            }
                            break;
                        case (in_array($rlst['mode'], self::MODE_NO_UPDATE)):
                            $html = !empty($rlst['code']) ? $rlst['code'] : '';
                            $update = false;
                            $insert = false;
                            break;
                        default:
                            $html = !empty($rlst['code']) ? $rlst['code'] : '';
                            $insert = true;
                            break;
                    }
                    if ($insert) {
                        $updDatas[] = ['id' => $rlst['idCible'], 'mode' => $rlst['mode'], 'code' => $html];
                    }
                }
                if ($update) {
                    $updDatas[] = ['id' => '', 'mode' => 'execID', 'code' => 'layoutScripts'];
                }

                if ($operate) {
                    // traitement des ressources pour injection de fichiers sans doublons
                    $rscsObjs = [];
                    foreach ($rscs as $key => $files) {
                        foreach ($files as $file) {
                            if (!array_key_exists($file, $rscsObjs)) {
                                $rscsObjs[$file] = $key . '|' . $file;
                            }
                        }
                    }
                    foreach ($rscsObjs as $item) {
                        $key = substr($item, 0, strpos($item, '|'));
                        $result[] = ['id' => $key, 'mode' => 'rscs', 'code' => substr($item, strpos($item, '|') + 1)];
                    }

                    $result = array_merge($result, $updDatas);
                } else {
                    $i = $callingObj->getObject();
                    if ($i == 'oddragndrop') {
                        $updDatas = $updDatas[0];
                        $updDatas = $updDatas['code'];
                    } else {
                        throw new UnexpectedValueException(ERR_UNEXPECTED_VALUE_MSG);
                    }
                    $result = $updDatas;
                }

                // traitement et ajout de la zone de communication aux données à retourner
                if ($dataZC !== null) {
                    switch (true) {
                        case (is_array($dataZC) && count($dataZC) == 0):
                            $item = ['id' => $nameZC, 'mode' => 'updZoneComm', 'code' => ''];
                            break;
                        case (is_array($dataZC) && count($dataZC) > 0):
                            $dataZC = json_encode($dataZC);
                            $item = ['id' => $nameZC, 'mode' => 'updZoneComm', 'code' => $dataZC];
                            break;
                        case (!is_array($dataZC) && strlen($dataZC) > 0):
                            $item = ['id' => $nameZC, 'mode' => 'updZoneComm', 'code' => $dataZC];
                            break;
                        default:
                            throw new UnexpectedValueException(ERR_UNEXPECTED_VALUE_MSG);
                    }
                    array_unshift($result, $item);
                }

                $response->getHeaders()->addHeaders([
                    'Content-Type' => 'application/json'
                ]);
                $response->setContent(json_encode($result));
                return $response;
            }
        }
        return false;
    }

    /**
     * @return Stream
     * @throws Exception
     */
    public function gotDownloadAction()
    {
        $sessionObjects = OObject::validateSession();
        $idDND = (int)$this->params()->fromRoute('idDND', 0);
        $LoadedFileID = (int)$this->params()->fromRoute('loadedFileID', 0);

        /** @var ODDragNDrop $objet */
        $objet = $this->buildObject($idDND, $sessionObjects);
        $loadedFiles = $objet->getLoadedFiles();

        $file = $loadedFiles[$LoadedFileID]['pathFile'];
        $response = new Stream();
        $response->setStream(fopen($file, 'r'));
        $response->setStatusCode(200);
        $response->setStreamName(basename($file));
        $headers = new Headers();
        $headers->addHeaders(array(
            'Content-Disposition' => 'attachment; filename="' . basename($file) . '"',
            'Content-Type' => 'application/octet-stream',
            'Content-Length' => filesize($file),
            'Expires' => '@0', // @0, because zf2 parses date as string to \DateTime() object
            'Cache-Control' => 'must-revalidate',
            'Pragma' => 'public'
        ));
        $response->setHeaders($headers);
        return $response;
    }


    /**
     * méthodes privées de la classe
     */

    /**
     * méthode de création d'objet
     *
     * @param string $objClass nom de classe de l'objet à créer si $params vide
     * @param Container $sessionObjects Objet Session contenant la déclarations des objets en cours de validité
     * @param array? $params            tableau des paramétres pour créatiion de l'objet à travailler
     * @return mixed                    un objet de type ZF3GraphicObjectTemplating ou à partir de $objClass
     * @throws Exception
     */
    private function buildObject($objClass, $sessionObjects, $params = null)
    {
        if (NULL === $params) {
            $object = new $objClass();
        } else {
            if (isset($params['obj']) && $params['obj'] == 'OUI') {
                $object = OObject::buildObject($params['id'], $sessionObjects);
            } else {
                $object = new $objClass($params);
            }
        }
        return $object;
    }

    /**
     * méthode de création et formatage du tableau des valeurs des champs d'un formulaire
     *
     * @param string $form chaîne de caractères transmise lors de l'appel Ajax des champs/valeurs du fortmulaire
     * @param array $objects tableau des déclaration des objets valides en sessions
     * @return array tableau formé par :
     *                      le tableau des champs (clé d'accès) et valeurs du formulaire retravaillés
     *                      le tableau des déclarations des objets valides en session mis à jours avec les traitements
     */
    private function buildFormDatas($form, $objects)
    {
        $datas = explode('|', $form);
        $formDatas = [];
        foreach ($datas as $data) {
            $data = explode('§', $data);
            $idF = '';
            $val = '';
            $object = '';
            $files = '';
            foreach ($data as $item) {
                switch (true) {
                    case (strpos($item, 'id=') !== false):
                        $idF = substr($item, 3);
                        break;
                    case (strpos($item, 'value=') !== false):
                        $val = $this->trimQuote(substr($item, 6), '*');
                        break;
                    case (strpos($item, 'object=') !== false):
                        $object = $this->trimQuote(substr($item, 7), '*');
                        break;
                    case (strpos($item, 'files=') !== false):
                        $files = $this->trimQuote(substr($item, 7), '*');
                        break;
                    default:
                        throw new UnexpectedValueException(ERR_UNEXPECTED_VALUE_MSG);
                }
            }
            // formatage en sortie en tableau idObj => valeur
            switch ($object) {
                case 'odcheckbox':
                case 'odtreeview':
                    $val = explode('$', $val);
                    if (empty($val)) {
                        $val = [];
                    }
                    if (!is_array($val)) {
                        $val[] = $val;
                    }
                    $formDatas[$idF] = $val;
                    break;
                case 'oddragndrop':
                    $formDatas[$idF] = $files;
                    break;
                default:
                    $formDatas[$idF] = $val;
                    break;
            }
            // mise à jour avec la valeur récupérée de chaque objet champ du formulaire
            $objects = $this->updateFieldObject($formDatas, $objects);
        }
        return [$formDatas, $objects];
    }

    /**
     * méthode de mise à jour des définitions des champs valides en session avec les valeurs transmises dans la zone de
     * communication de l'appel Ajax pour validation du formulaire
     *
     * @param array $datas tableau des champs/valeurs retravaillées du formulaire
     * @param array $objects tableau des déclaration des objets valides en sessions
     * @return array            tableau des déclaration des objets valides en sessions MIS À JOUR
     */
    private function updateFieldObject(array $datas, array $objects)
    {
        foreach ($datas as $id => $data) {
            if (array_key_exists($id, $objects)) {
                $properties = unserialize($objects[$id]);
                switch ($properties['object']) {
                    case 'odcheckbox':
                        if (empty($data)) {
                            $data = [];
                        }
                        if (!is_array($data)) {
                            $data[] = $data;
                        }
                        $options = $properties['options'];
                        foreach ($data as $value) {
                            if (array_key_exists($value, $options)) {
                                $options[$value]['check'] = true;
                            }
                        }
                        $properties['options'] = $options;
                        break;
                    case 'odtreeview':
                        $properties['dataSelected'] = $data;
                        break;
                    default:
                        $properties['value'] = $data;
                        break;
                }
                $objects[$id] = serialize($properties);
                return $objects;
            }
        }
    }

    private function stringToArray(string $chaine)
    {
        $chaine = substr($chaine, 1, strlen($chaine) - 2);
        $chaine = explode('},', $chaine);
        $array = [];
        foreach ($chaine as $part_chaine) {
            $part_chaine = explode(':{', $part_chaine);
            $ind = 0;
            foreach ($part_chaine as $part) {
                $ind += 1;
                if (strpos($part, ',') !== false && (($ind % 2) === 0)) {
                    $part_array = [];
                    $part2 = explode(',', $part);
                    foreach ($part2 as $item) {
                        $parts = explode(':', $item);
                        $part_array[str_replace('"', '', $parts[0])] =
                                                                        str_replace('"', '', $parts[1]);
                    }
                    $part2 = $part_array;
                } elseif (strpos($part, ',') === false && ($ind % 2) === 1) {
                    $part1 = $part;
                } else {
                    throw new UnexpectedValueException("chaine mal construite");
                }
            }

            if ($ind % 2 === 0) {
                $array[$part1] = $part2;
            }
        }
        return $array;
    }
}
