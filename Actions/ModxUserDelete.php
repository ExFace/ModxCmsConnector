<?php
namespace exface\ModxCmsConnector\Actions;

use exface\Core\CommonLogic\AbstractAction;
use exface\Core\Exceptions\Actions\ActionInputMissingError;
use exface\Core\Exceptions\Actions\ActionInputInvalidObjectError;
use exface\ModxCmsConnector\CmsConnectors\Modx;
use exface\Core\Factories\DataSheetFactory;

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
        
        $modx = $this->getWorkbench()->getApp('exface.ModxCmsConnector')->getModx();
        require_once $modx->getConfig('base_path') . 'assets' . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'MODxAPI' . DIRECTORY_SEPARATOR . 'modUsers.php';
        /** @var Modx $modxCmsConnector */
        $modxCmsConnector = $this->getWorkbench()->getCMS();
        $modUser = new \modUsers($modx);
        
        // Wird ein Exface-Nutzer manuell im Frontend geloescht, wird ein DataSheet mit Filter
        // aber ohne rows uebergeben. Dann werden die geloeschten Nutzer eingelesen. Wird ein
        // Exface-Nutzer aus dem Exface-Plugin heraus geloescht, wird ein DataSheet ohne Filter
        // aber mit rows uebergeben. Dieses wird einfach verwendet.
        if ($this->getInputDataSheet()->countRows() == 0 && $this->getInputDataSheet()->getFilters()->countConditions() > 0) {
            $exfUserObj = $this->getWorkbench()->model()->getObject('exface.Core.USER');
            $exfUserSheet = DataSheetFactory::createFromObject($exfUserObj);
            $exfUserSheet->getColumns()->addFromAttribute($exfUserObj->getAttribute('USERNAME'));
            $exfUserSheet->setFilters($this->getInputDataSheet()->getFilters());
            $exfUserSheet->dataRead();
        } else {
            $exfUserSheet = $this->getInputDataSheet();
        }
        
        foreach ($exfUserSheet->getRows() as $row) {
            if (! $row['USERNAME']) {
                throw new ActionInputMissingError($this, 'Mandatory username is missing.');
            }
            
            if ($modxCmsConnector->isModxWebUser($row['USERNAME'])) {
                // Delete Webuser.
                $modUser->delete($modxCmsConnector->getModxWebUserId($row['USERNAME']));
            } elseif ($modxCmsConnector->isModxMgrUser($row['USERNAME'])) {
                // Delete Manageruser.
                $this->deleteMgrUser($modxCmsConnector->getModxMgrUserId($row['USERNAME']));
            }
        }
        
        $this->setResult('');
        $this->setResultMessage('Exface user deleted.');
    }

    /**
     * Deletes the Modx manager user with the given id.
     *
     * @param integer $id
     * @return boolean
     */
    private function deleteMgrUser($id)
    {
        $modx = $this->getWorkbench()->getApp('exface.ModxCmsConnector')->getModx();
        
        // delete the user.
        $modx->db->delete($modx->getFullTableName('manager_users'), "id='{$id}'");
        
        // delete user groups
        $modx->db->delete($modx->getFullTableName('member_groups'), "member='{$id}'");
        
        // delete user settings
        $modx->db->delete($modx->getFullTableName('user_settings'), "user='{$id}'");
        
        // delete the attributes
        $modx->db->delete($modx->getFullTableName('user_attributes'), "internalKey='{$id}'");
        
        return true;
    }
}