<?php
/**
 * @file
 * @package xcck
 * @version $Id$
**/

if(!defined('XOOPS_ROOT_PATH'))
{
    exit;
}

require_once XCCK_TRUST_PATH . '/class/AbstractEditAction.class.php';

/**
 * Xcck_PageEditAction
**/
class Xcck_SubtableEditAction extends Xcck_AbstractEditAction
{
    public $mField = array();

    /**
     * _getId
     * 
     * @param   void
     * 
     * @return  int
    **/
    protected function _getId()
    {
        $id = parent::_getId();
        if(! $id && $this->mRoot->mContext->mModuleConfig['singlepost']){
        	$cri = new CriteriaCompo();
        	$cri->add(new Criteria('uid', Legacy_Utils::getUid()));
        	$cri->add(new Criteria('maintable_id', $this->_getMaintableId()));
			$objs = $this->_getHandler()->getObjects($cri);
			if(count($objs)>0){
				$id = array_shift($objs)->get('page_id');
			}
        }
        return $id;
    }

    /**
     * _getMaintableId
     * 
     * @param   void
     * 
     * @return  int
    **/
    protected function _getMaintableId()
    {
        return intval($this->mRoot->mContext->mRequest->getRequest('maintable_id'));
    }

    /**
     * _getCatId
     * 
     * @param   void
     * 
     * @return  int
    **/
    protected function _getCatId()
    {
        $req = $this->mRoot->mContext->mRequest;
        if($catId = intval($req->getRequest('category_id')) > 0){
            return $catId;
        }
        else{
            $this->mObject->loadMaintable();
            return $this->mObject->mMaintable->get('category_id');
        }
    }

    /**
     * _getParentId
     * 
     * @param   void
     * 
     * @return  int
    **/
    protected function _getParentId()
    {
        return intval($this->mRoot->mContext->mRequest->getRequest('p_id'));
    }

    /**
     * &_getHandler
     * 
     * @param   void
     * 
     * @return  Xcck_PageHandler
    **/
    protected function &_getHandler()
    {
        $handler =& $this->mAsset->getObject('handler', 'Page');
        return $handler;
    }

    /**
     * _getPageTitle
     * 
     * @param   void
     * 
     * @return  string
    **/
    protected function _getPagetitle()
    {
        return $this->mObject->getShow('title');
    }

    /**
     * _getXcckAuthType
     * 
     * @param   void
     * 
     * @return  string
    **/
    public function _getXcckAuthType()
    {
        return Xcck_AuthType::POST;
    }

    /**
     * _getConfigForCategory
     * 
     * @param   void
     * 
     * @return  string
    **/
    protected function _getConfigForCategory()
    {
        $handler= xoops_gethandler('config'); 
        return $handler->getConfigsByDirname($this->mObject->mMaintable->getDirname());
    }

    /**
     * hasPermission
     * 
     * @param   void
     * 
     * @return  bool
    **/
    public function hasPermission()
    {
        $catId = $this->_getCatId();
        $auth = $this->mRoot->mContext->mModuleConfig['subtable_auth'];
    
        if($this->_getMaintableId()===0 && $this->mObject->get('maintable_id')===0){
            $this->mRoot->mController->executeRedirect($this->_getNextUri('page', 'list'), 1, _MD_XCCK_ERROR_MAINTABLE_REQUIRED);
        }
    
        //is Manager ?
        if($this->mCategoryManager->check($catId, Xcck_AuthType::MANAGE, 'page')==true){
            return true;
        }
        //is new post and has post permission ?
        $check = $this->mCategoryManager->check($catId, $auth, 'page');
        if($check==true && $this->mObject->isNew()){
            return true;
        }
        //is old post and your post ?
        if($check==true && ! $this->mObject->isNew() && $this->mObject->get('uid')==Legacy_Utils::getUid()){
                return true;
        }
    
        return false;
    }

    /**
     * _setupActionForm
     * 
     * @param   void
     * 
     * @return  void
    **/
    protected function _setupActionForm()
    {
        $this->mActionForm =& $this->mAsset->getObject('form', 'Page',false,'edit');
        $this->mActionForm->prepare($this->mAsset->mDirname);
    }

    /**
     * prepare
     * 
     * @param   void
     * 
     * @return  bool
    **/
    public function prepare()
    {
        $result = parent::prepare();
        $this->mDefinitions = Legacy_Utils::getModuleHandler('definition', $this->mAsset->mDirname)->getFields();
        if($this->mObject->isNew()){
            $this->mObject->set('uid', Legacy_Utils::getUid());
            $this->mObject->set('p_id', $this->_getParentId());
            $this->mObject->set('status', Lenum_Status::PUBLISHED);
            $this->mObject->set('maintable_id', $this->_getMaintableId());
            $this->mObject->set('category_id', $this->_getCatId());
            foreach($this->mDefinitions as $field){
                if(in_array($field->get('field_type'), array('string', 'int'))){
                    $this->mObject->set($field->get('field_name'), $field->get('options'));
                }
            }
        }
        $this->mObject->loadMaintable();
        if(count($this->mObject->mMaintable)!=1){
            $this->mRoot->mController->executeRedirect($this->_getNextUri('page', 'list'), 1, _MD_XCCK_ERROR_INVALID_MAINTABLE);
        }
        $this->_setupCategoryManager('page');
    
        //setup tags
        $this->mObject->loadTag();
        return $result;
    }

    /**
     * executeViewInput
     * 
     * @param   XCube_RenderTarget  &$render
     * 
     * @return  void
    **/
    public function executeViewInput(/*** XCube_RenderTarget ***/ &$render)
    {
        $render->setTemplateName($this->mAsset->mDirname . '_page_edit.html');
        $render->setAttribute('actionForm', $this->mActionForm);
        $render->setAttribute('object', $this->mObject);
        $render->setAttribute('dirname', $this->mAsset->mDirname);
        $dataname = $this->_isSubtable() ? 'subtable' : 'page';
        $render->setAttribute('dataname', $dataname);
        $render->setAttribute('fields',$this->mDefinitions);
    
        $render->setAttribute('accessController', $this->mCategoryManager);
        $render->setAttribute('isSubtable', $this->_isSubtable());
        $render->setAttribute('defaultOrder', $this->mRoot->mContext->mModuleConfig['default_order']);
    
        //date field option
        $render->setAttribute('hours', range(1,24));
        $render->setAttribute('minutes', range(0,59));
        //set tag usage
        $render->setAttribute('useTag', ($this->mRoot->mContext->mModuleConfig['tag_dirname']) ? true : false);
        $render->setAttribute('tag_dirname', $this->mRoot->mContext->mModuleConfig['tag_dirname']);
    
        //setup image
        $render->setAttribute('imageNameList', Xcck_Utils::getImageNameList($this->mAsset->mDirname));
    
    }

    /**
     * _setHeaderScript
     * 
     * @param   void
     * 
     * @return  void
    **/
    protected function _setHeaderScript()
    {
        $headerScript = $this->mRoot->mContext->getAttribute('headerScript');
        $headerScript->addScript($this->_getDatePickerScript());
    }

    /**
     * executeViewSuccess
     * 
     * @param   XCube_RenderTarget  &$render
     * 
     * @return  void
    **/
    public function executeViewSuccess(/*** XCube_RenderTarget ***/ &$render)
    {
        $this->mRoot->mController->executeForward(Legacy_Utils::renderUri($this->mRoot->mContext->mModuleConfig['maintable'], 'page', $this->mObject->getShow('maintable_id')));
    }

    /**
     * executeViewError
     * 
     * @param   XCube_RenderTarget  &$render
     * 
     * @return  void
    **/
    public function executeViewError(/*** XCube_RenderTarget ***/ &$render)
    {
        $this->mRoot->mController->executeRedirect(Legacy_Utils::renderUri($this->mRoot->mContext->mModuleConfig['maintable'], 'page', $this->mObject->getShow('maintable_id')), 1, _MD_XCCK_ERROR_DBUPDATE_FAILED);
    }

    /**
     * executeViewCancel
     * 
     * @param   XCube_RenderTarget  &$render
     * 
     * @return  void
    **/
    public function executeViewCancel(/*** XCube_RenderTarget ***/ &$render)
    {
        $this->mRoot->mController->executeForward(Legacy_Utils::renderUri($this->mRoot->mContext->mModuleConfig['maintable'], 'page', $this->mObject->getShow('maintable_id')));
    }
}

?>