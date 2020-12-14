<?php

namespace LaminasGOT\Service;


use GraphicObjectTemplating\OObjects\ODContained\ODTable;
use GraphicObjectTemplating\OObjects\OTInfoBulle;
use GraphicObjectTemplating\OObjects\OObject;
use GraphicObjectTemplating\OObjects\OSContainer\OSForm;
use http\Exception\UnexpectedValueException;
use Laminas\ServiceManager\ServiceManager;
use Laminas\Session\Container;
use Laminas\View\Model\ViewModel;
use Laminas\View\Renderer\PhpRenderer;

/**
 * Class LF3GotServices
 * @package GraphicObjectTemplating\Service
 *
 * méthodes
 * --------
 * __construct($sm, $tr, $cfg)
 * bootstrap($widthBT) : string
 * render_html(OObject $object) : string
 * render_objects(ViewModel $viewModel)
 * render_header($rscs) : string
 * search_rscs(OObject $var)
 * static merge_rscs_unique($array1, $array2) : array
 * format_rscs(array $rscs, OObject $var) : array
 * formatRetour($idSource, $idCible, $mode, $code = null)
 */
class LF3GotServices
{
    const ERR_UNEXPECTED_VALUE_MSG = 'Unexpected value';

    /** @var ServiceManager $_serviceManager */
    private $_serviceManager;
    /** @var PhpRenderer $_render */
    private $_render;
    private $_config;

    /**
     * LF3GotServices constructor.
     * @param $sm
     * @param $tr
     * @param $cfg
     */
    public function __construct($sm, $pr, $cfg)
    {
        $this->_serviceManager = $sm;
        $this->_render = $pr;
        $this->_config = $cfg;
        $this->_manager = $this->_serviceManager->get('objects.manager');
    }

    /**
     * @param $widthBT
     * @return string
     */
    public function bootstrap($widthBT): string
    {
        $classesObj = '';
        if (!empty($widthBT)) {
            $widthBT = explode(':', $widthBT);
            foreach ($widthBT as $item) {
                switch (strtoupper(substr($item, 0, 2))) {
                    case 'WL' :
                        $classesObj .= ' col-lg-' . substr($item, 2);
                        break;
                    case 'WM' :
                        $classesObj .= ' col-md-' . substr($item, 2);
                        break;
                    case 'WS' :
                        $classesObj .= ' col-sm-' . substr($item, 2);
                        break;
                    case 'WX' :
                        $classesObj .= ' col-xs-' . substr($item, 2);
                        break;
                    case 'OL' :
                        $classesObj .= ' col-lg-offset-' . substr($item, 2);
                        break;
                    case 'OM' :
                        $classesObj .= ' col-md-offset-' . substr($item, 2);
                        break;
                    case 'OS' :
                        $classesObj .= ' col-sm-offset-' . substr($item, 2);
                        break;
                    case 'OX' :
                        $classesObj .= ' col-xs-offset-' . substr($item, 2);
                        break;
                    default:
                        throw new UnexpectedValueException(ERR_UNEXPECTED_VALUE_MSG);
                }
            }
        }
        $classesObj .= ' ';
        return $classesObj;
    }

    /**
     * @param OObject $object
     * @return string
     */
    public function render_html(OObject $object): string
    {
        $properties = $object->properties;
        $html = new ViewModel();
        $template = $properties['template'] . '.' . $this->_config['gotParameters']['template_ext'];
        $renduHtml = '';

//        if (isset($object->infoBulle) && $object->infoBulle instanceof OTInfoBulle) {
//            $object->infoBulle = $object->infoBulle->properties;
//        }

        switch ($properties['typeObj']) {
            case 'odcontained' :
            case 'oedcontained':
                if ($object instanceof ODTable) {
                    /** traitement des boutons en de formulaire */
                    if ($properties['btnsActions']->haschild()) {
                        $properties['btnsActions'] = $this->render_html($properties['btnsActions']);
                    }
                } else {
                    throw new \Exception('Unexpected value');
                }
                $html->setTemplate($template);
                $html->setVariable('objet', $properties);
                $html->setVariable('id', $properties['id']);
                break;
            case 'oscontainer':
            case 'oescontainer':
                $content = "";
                /** @var array $children */
                $children = $properties['children'];
                if (!empty($children)) {
                    /** @var OObject $child */
                    foreach ($children as $child) {
                        $rendu = $this->render_html($child);
                        $content .= $rendu;
                    }
                }
            if ($object instanceof OSForm) {
                /** traitement des boutons en de formulaire */
                if ($properties['btnsControls']->haschild()) {
                    $properties['btnsControls'] = $this->render_html($properties['btnsControls']);
                }
            } else {
                throw new \Exception('Unexpected value');
            }
                $html->setTemplate($template);
                $html->setVariable('objet', $properties);
                $html->setVariable('content', $content);
                $html->setVariable('id', $properties['id']);
                break;
            default:
                throw new \UnexpectedValueException(self::ERR_UNEXPECTED_VALUE_MSG);
        }
        $renduHtml .= $this->_render->render($html);
        $renduHtml = preg_replace('/(\s)\s+/', '$1', $renduHtml);
        $renduHtml = str_replace(array("\r\n", "\r", "\n"), "", $renduHtml);
        return $renduHtml;
    }

    /**
     * @param ViewModel $viewModel
     * @return array
     */
    public function render_objects(ViewModel $viewModel): array
    {
        $rscs = [];

        if ($viewModel->hasChildren()) {
            $children = $viewModel->getChildren();
            $viewModel->clearChildren();
            /**
             * @var int $ind
             * @var ViewModel $child
             */
            foreach ($children as $ind => $child) {
                list($child, $tmp_rscs) = $this->render_objects($child);
                $rscs = self::merge_rscs_unique($rscs, $tmp_rscs);
                $viewModel->addChild($child);
            }
        }

        $variables = $viewModel->getVariables();
        foreach ($variables as $ind => $var) {
            if ($var instanceof OObject) {
                $tmp_rscs = $this->search_rscs($var);
                $rscs = self::merge_rscs_unique($rscs, $tmp_rscs);
                $variables[$ind] = $this->render_html($var);
            }
        }
        $viewModel->setVariables($variables);

        return [$viewModel, $rscs];
    }

    /**
     * méthode de rendu des entêtes header de chargement de resources internes à GOT
     * @param $rscs
     * @return string
     */
    public function render_header($rscs): string
    {
        $view = new ViewModel();
        $ext = $this->_config['gotParameters']['template_ext'];
        $view->setTemplate(
            'graphicobjecttemplating/main/got-header.'.$ext);
        $view->setVariable('scripts',
            ['css' => $rscs['css'] ?? [], 'js' => $rscs['js'] ?? [], 'font' => $rscs['fonts'] ?? []]);

        return $rscs ? $this->_render->render($view) : '';
    }

    /**
     * @param OObject $var
     * @return array|mixed
     */
    public function search_rscs(OObject $var)
    {
        $pathRscs = __DIR__ . '/../../';
        $pathRscs .= 'view/graphicobjecttemplating/oobjects/';
        $pathRscs .= $var->typeObj . '/' . $var->object . '/' . $var->object . '.rscs.php';

        $rscs = is_file($pathRscs) ? include $pathRscs : [];
        $rscs = $this->format_rscs($rscs, $var);
        if ($var->children) {
            $tmp_rscs = [];
            foreach ($var->children as $child) {
                $child_rscs = $this->format_rscs($this->search_rscs($child), $child);
                $tmp_rscs = self::merge_rscs_unique($tmp_rscs, $child_rscs);
            }
            $rscs = self::merge_rscs_unique($rscs, $tmp_rscs);
        }

        return $rscs;
    }

    /**
     * @param $array1
     * @param $array2
     * @return array[]
     */
    private static function merge_rscs_unique($array1, $array2): array
    {
        $r_array = ['css' => [], 'js' => [], 'fonts' => []];
        if (array_key_exists('css', $array1)) {
            foreach ($array1['css'] as $key => $css) {
                if (!array_key_exists($key, $r_array['css'])) {
                    $r_array['css'][$key] = $css;
                }
            }
        }
        if (array_key_exists('css', $array2)) {
            foreach ($array2['css'] as $key => $css) {
                if (!array_key_exists($key, $r_array['css'])) {
                    $r_array['css'][$key] = $css;
                }
            }
        }
        if (array_key_exists('js', $array1)) {
            foreach ($array1['js'] as $key => $css) {
                if (!array_key_exists($key, $r_array['js'])) {
                    $r_array['js'][$key] = $css;
                }
            }
        }
        if (array_key_exists('js', $array2)) {
            foreach ($array2['js'] as $key => $css) {
                if (!array_key_exists($key, $r_array['js'])) {
                    $r_array['js'][$key] = $css;
                }
            }
        }
        if (array_key_exists('fonts', $array1)) {
            foreach ($array1['fonts'] as $key => $css) {
                if (!array_key_exists($key, $r_array['fonts'])) {
                    $r_array['fonts'][$key] = $css;
                }
            }
        }
        if (array_key_exists('fonts', $array2)) {
            foreach ($array2['fonts'] as $key => $css) {
                if (!array_key_exists($key, $r_array['fonts'])) {
                    $r_array['fonts'][$key] = $css;
                }
            }
        }
        return $r_array;
    }

    /**
     * @param array $rscs
     * @param OObject $var
     */
    private function format_rscs(array $rscs, OObject $var): array
    {
        foreach ($rscs as $type => $array_link) {
            foreach ($array_link as $key => $link) {
                switch (true) {
                    case (str_starts_with($link, 'graphicobjecttemplating')):
                        break;
                    case (str_contains($link, 'odcontained') && str_starts_with($link, 'odcontained')):
                    case (str_contains($link, 'oscontainer') && str_starts_with($link, 'oscontainer')):
                        $rscs[$type][$key] = "graphicobjecttemplating/oobjects/{$link}";
                        break;
                    case (str_starts_with($link, "lib:")):
                        // ajout de librairie externe
                        $rscs[$type][$key] = substr($link, 4);
                    default:
                        $rscs[$type][$key] = "graphicobjecttemplating/oobjects/{$var->typeObj}/{$var->object}/{$link}";
                        break;
                }
            }
        }
        return $rscs;
    }

    /**
     * @param $idSource
     * @param $idCible
     * @param $mode
     * @param null $code
     * @return array
     */
    public function formatRetour($idSource, $idCible, $mode, $code = null): array
    {
        if (empty($idCible)) { $idCible = $idSource; }
        $occurence = ['idSource' => $idSource, 'idCible' => $idCible, 'mode' => $mode, 'code' => $code];
        return [$occurence];
    }
}