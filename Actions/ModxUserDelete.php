<?php
namespace exface\ModxCmsConnector\Actions;

use exface\Core\CommonLogic\AbstractAction;
use exface\Core\Exceptions\Actions\ActionInputMissingError;
use exface\Core\Exceptions\Actions\ActionInputInvalidObjectError;
use exface\Core\Factories\DataSheetFactory;
use exface\ModxCmsConnector\CmsConnectors\Modx;

/**
 * Deletes an modx web- or manager user with the given username.
 * 
 * This Action can be called with an InputDataSheet 'exface.Core.USER' containing column
 *     'USERNAME'
 * 
 * @author SFL
 *
 */
class ModxUserDelete extends AbstractAction
{

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AbstractAction::perform()
     */
    protected function perform()
    {
        if (! $this->getInputDataSheet()->getMetaObject()->isExactly('exface.Core.USER')) {
            throw new ActionInputInvalidObjectError($this, 'InputDataSheet with "exface.Core.USER" required, "' . $this->getInputDataSheet()->getMetaObject()->getAliasWithNamespace() . '" given instead.');
        }
        
        require_once MODX_BASE_PATH . 'assets' . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'MODxAPI' . DIRECTORY_SEPARATOR . 'modUsers.php';
        $modx = $this->getWorkbench()->getApp('exface.ModxCmsConnector')->getModx();
        /** @var Modx $modxCmsConnector */
        $modxCmsConnector = $this->getWorkbench()->getCMS();
        $modUser = new \modUsers($modx);
        $exfUserObj = $this->getWorkbench()->model()->getObject('exface.Core.USER');
        $exfUserSheet = DataSheetFactory::createFromObject($exfUserObj);
        $exfUserSheet->getColumns()->addFromAttribute($exfUserObj->getAttribute('USERNAME'));
        $exfUserSheet->setFilters($this->getInputDataSheet()->getFilters());
        $exfUserSheet->dataRead();
        
        foreach ($exfUserSheet->getRows() as $row) {
            if (! $row['USERNAME']) {
                throw new ActionInputMissingError($this, 'Mandatory username is missing.');
            }
            
            if ($modxCmsConnector->isModxWebUser($row['USERNAME'])) {
                // Delete Webuser.
                $modUser->delete($modxCmsConnector->getModxWebUserId($row['USERNAME']));
            } elseif ($modxCmsConnector->isModxMgrUser($row['USERNAME'])) {
                // Delete Manageruser.
                // TODO
            }
        }
    }
}