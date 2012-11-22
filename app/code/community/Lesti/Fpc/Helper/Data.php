<?php
/**
 * Created by JetBrains PhpStorm.
 * User: gordon
 * Date: 24.10.12
 * Time: 12:41
 * To change this template use File | Settings | File Templates.
 */
class Lesti_Fpc_Helper_Data extends Mage_Core_Helper_Abstract
{
    const CACHEABLE_ACTIONS_XML_PATH = 'system/fpc/cache_actions';
    const XML_PATH_REBUILD_CACHE = 'system/fpc/rebuild_cache';
    const XML_PATH_SESSION_PARAMS = 'system/fpc/session_params';
    const LAYOUT_ELEMENT_CLASS = 'Mage_Core_Model_Layout_Element';

    protected $_params;

    public function getCacheableActions()
    {
        $actions = Mage::getStoreConfig(self::CACHEABLE_ACTIONS_XML_PATH);
        return array_map('trim', explode(',', $actions));
    }

    public function rebuildCache()
    {
        return Mage::getStoreConfig(self::XML_PATH_REBUILD_CACHE);
    }

    public function getKey($postfix = '_page')
    {
        return sha1($this->_getParams()) . $postfix;
    }

    protected function _getParams()
    {
        if (!$this->_params) {
            $params = array('host' => $_SERVER['HTTP_HOST'],
                'port' => $_SERVER['SERVER_PORT'],
                'uri' => $_SERVER['REQUEST_URI']);
            if (count(Mage::app()->getWebsite()->getStores())>1) {
                $cookie = Mage::getSingleton('core/cookie');
                $storeCode = $cookie->get(Mage_Core_Model_Store::COOKIE_NAME);
                if ($storeCode) {
                    $params['store'] = $storeCode;
                }
            }
            $sessionParams = $this->_getSessionParams();
            $session = Mage::getSingleton('catalog/session');
            foreach ($sessionParams as $param) {
                if ($session->getData($param)) {
                    $params[$param] = $session->getData($param);
                }
            }
            $this->_params = serialize($params);
        }
        return $this->_params;
    }

    protected function _getSessionParams()
    {
        $params = Mage::getStoreConfig(self::XML_PATH_SESSION_PARAMS);
        return array_map('trim', explode(',', $params));
    }

    public function getCacheTags()
    {
        $fullActionName = $this->getFullActionName();
        $cacheTags = array();
        $request = Mage::app()->getRequest();
        switch ($fullActionName) {
            case 'cms_index_index' :
                $cacheTags[] = 'cms';
                $pageId = Mage::getStoreConfig(Mage_Cms_Helper_Page::XML_PATH_HOME_PAGE);
                if ($pageId) {
                    $cacheTags[] = 'cms_' . $pageId;
                }
                break;
            case 'cms_page_view' :
                $cacheTags[] = 'cms';
                $pageId = $request->getParam('page_id', $request->getParam('id', false));
                if ($pageId) {
                    $cacheTags[] = 'cms_' . $pageId;
                }
                break;
            case 'catalog_product_view' :
                $cacheTags[] = 'product';
                $productId  = (int) $request->getParam('id');
                if ($productId) {
                    $cacheTags[] = 'product_' . $productId;
                    $categoryId = (int) $request->getParam('category', false);
                    if ($categoryId) {
                        $cacheTags[] = 'category';
                        $cacheTags[] = 'category_' . $categoryId;
                    }
                }
                break;
            case 'catalog_category_view' :
                $cacheTags[] = 'category';
                $categoryId = (int) $request->getParam('id', false);
                if ($categoryId) {
                    $cacheTags[] = 'category_' . $categoryId;
                }
                break;
        }
        return $cacheTags;
    }

    public function getFullActionName($delimiter = '_')
    {
        $request = Mage::app()->getRequest();
        return $request->getRequestedRouteName() . $delimiter .
            $request->getRequestedControllerName() . $delimiter .
            $request->getRequestedActionName();
    }

    public function initLayout()
    {
        $layout = Mage::getSingleton('core/layout');
        $update = $layout->getUpdate();
        $update->addHandle('default');
        if (Mage::getSingleton('customer/session')->isLoggedIn()) {
            $update->addHandle('customer_logged_in');
        } else {
            $update->addHandle('customer_logged_out');
        }
        $update->addHandle('STORE_' . Mage::app()->getStore()->getCode());
        $package = Mage::getSingleton('core/design_package');
        $update->addHandle(
            'THEME_' . $package->getArea() . '_' . $package->getPackageName() . '_' . $package->getTheme('layout')
        );
        $update->addHandle(strtolower($this->getFullActionName()));
        $update->load();
        $layout->generateXml();
        $xml = simplexml_load_string($layout->getXmlString(), self::LAYOUT_ELEMENT_CLASS);
        $cleanXml = simplexml_load_string('<layout/>', self::LAYOUT_ELEMENT_CLASS);
        $types = array('block', 'reference', 'action');
        $dynamicBlocks = Mage::helper('fpc/block')->getDynamicBlocks();
        foreach ($dynamicBlocks as $blockName) {
            foreach ($types as $type) {
                $xPath = $xml->xpath("//" . $type . "[@name='" . $blockName . "']");
                foreach ($xPath as $child) {
                    $cleanXml->appendChild($child);
                }
            }
        }
        $layout->setXml($cleanXml);
        $layout->generateBlocks();
        $layout = Mage::helper('fpc/block_messages')->initLayoutMessages($layout);
        return $layout;
    }

}