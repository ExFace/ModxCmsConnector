<?php
namespace exface\ModxCmsConnector\Actions;

use exface\Core\CommonLogic\AbstractAction;
use exface\Core\Exceptions\Actions\ActionInputMissingError;
use exface\Core\Exceptions\Actions\ActionInputInvalidObjectError;
use exface\ModxCmsConnector\CmsConnectors\Modx;
use exface\Core\Factories\DataSheetFactory;
use exface\ModxCmsConnector\CommonLogic\ModxSessionManager;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\Interfaces\Tasks\TaskResultInterface;
use exface\Core\Factories\TaskResultFactory;

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
    protected function perform(TaskInterface $task, DataTransactionInterface $transaction) : TaskResultInterface
    {
        $input = $this->getInputDataSheet($task);
        
        if (! $input->getMetaObject()->isExactly('exface.Core.USER')) {
            throw new ActionInputInvalidObjectError($this, 'InputDataSheet with "exface.Core.USER" required, "' . $input->getMetaObject()->getAliasWithNamespace() . '" given instead.');
        }
        
        $modx = $this->getWorkbench()->getApp('exface.ModxCmsConnector')->getModx();
        require_once $modx->getConfig('base_path') . 'assets' . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'MODxAPI' . DIRECTORY_SEPARATOR . 'modUsers.php';
        require_once $modx->getConfig('base_path') . 'assets' . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'MODxAPI' . DIRECTORY_SEPARATOR . 'modManagers.php';
        /** @var Modx $modxCmsConnector */
        $modxCmsConnector = $this->getWorkbench()->getCMS();
        // modUsers und modManagers benoetigen eine geoeffnete Modx-Session.
        $modxSessionManager = new ModxSessionManager($modx);
        $modxSessionManager->sessionOpen();
        $modUser = new \modUsers($modx);
        $modManager = new \modManagers($modx);
        
        // Wird ein Exface-Nutzer manuell im Frontend geloescht, wird ein DataSheet mit Filter
        // aber ohne rows uebergeben. Dann werden die geloeschten Nutzer eingelesen. Wird ein
        // Exface-Nutzer aus dem Exface-Plugin heraus geloescht, wird ein DataSheet ohne Filter
        // aber mit rows uebergeben. Dieses wird einfach verwendet.
        if ($input->countRows() == 0 && $input->getFilters()->countConditions() > 0) {
            $exfUserObj = $this->getWorkbench()->model()->getObject('exface.Core.USER');
            $exfUserSheet = DataSheetFactory::createFromObject($exfUserObj);
            $exfUserSheet->getColumns()->addFromAttribute($exfUserObj->getAttribute('USERNAME'));
            $exfUserSheet->setFilters($input->getFilters());
            $exfUserSheet->dataRead();
        } else {
            $exfUserSheet = $input;
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
                $modManager->delete($modxCmsConnector->getModxMgrUserId($row['USERNAME']));
            }
        }
        
        // Die Modx-Session wird geschlossen und die zuvor geoeffnete Session
        // wiederhergestellt.
        $modxSessionManager->sessionClose();
        
        return TaskResultFactory::createMessageResult($task, 'Exface user deleted.');
    }
}