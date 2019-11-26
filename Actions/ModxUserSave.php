<?php
namespace exface\ModxCmsConnector\Actions;

use exface\Core\CommonLogic\AbstractAction;
use exface\Core\Exceptions\Actions\ActionInputMissingError;
use exface\Core\Exceptions\Actions\ActionInputInvalidObjectError;
use exface\ModxCmsConnector\CmsConnectors\Modx;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Exceptions\Actions\ActionRuntimeError;
use exface\Core\Exceptions\UserNotFoundError;
use exface\Core\Exceptions\UserAlreadyExistsError;
use exface\ModxCmsConnector\CommonLogic\ModxSessionManager;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\Interfaces\Tasks\ResultInterface;
use exface\Core\Factories\ResultFactory;
use exface\Core\Factories\UserFactory;
use exface\Core\DataTypes\PasswordHashDataType;

/**
 * Creates or Updates a modx web-user or Updates a modx mgr-user.
 * 
 * This Action can be called with an InputDataSheet 'exface.Core.USER' containing columns
 *     'USERNAME', 'FIRST_NAME', 'LAST_NAME', 'LOCALE', 'EMAIL'
 * 
 * @author SFL
 *
 */
class ModxUserSave extends AbstractAction
{

    private $localLangMap;

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AbstractAction::init()
     */
    protected function init()
    {
        parent::init();
        $this->localLangMap = $this->getApp('exface.ModxCmsConnector')->getConfig()->getOption('USERS.LOCALE_LANGUAGE_MAPPING')->toArray();
        $this->setInputObjectAlias('exface.Core.USER');
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AbstractAction::perform()
     */
    protected function perform(TaskInterface $task, DataTransactionInterface $transaction) : ResultInterface
    {
        $input = $this->getInputDataSheet($task);
        
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
        
        // DataSheet zum Bestimmen des alten Nutzernamens erzeugen.
        $exfUserObj = $this->getWorkbench()->model()->getObject('exface.Core.USER');
        $exfUserSheet = DataSheetFactory::createFromObject($exfUserObj);
        $exfUserSheet->getColumns()->addFromAttribute($exfUserObj->getAttribute('USERNAME'));
        
        foreach ($input->getRows() as $row) {
            $userRow = [];
            $userRow['username'] = $row['USERNAME'];
            
            // Ein alter Nutzername wird nicht uebergeben ist aber wichtig um zu erkennen ob
            // ein Nutzer umbenannt wird. Wird daher aus der Datenbank eingelesen.
            $exfUserSheet->removeRows();
            $exfUserSheet->getFilters()->removeAll();
            $exfUserSheet->getFilters()->addConditionsFromString($exfUserObj, $exfUserObj->getUidAttributeAlias(), $row[$exfUserObj->getUidAttributeAlias()], EXF_COMPARATOR_EQUALS);
            $exfUserSheet->dataRead();
            if ($exfUserSheet->countRows() == 1 && $exfUserSheet->getCellValue('USERNAME', 0) !== $row['USERNAME']) {
                // Der Nutzer wird umbenannt. Existiert bereits ein Exface-Nutzer mit dem
                // neuen Benutzernamen?
                $user = UserFactory::createFromModel($this->getWorkbench(), $row['USERNAME']);
                if ($user->hasModel()) {
                    // Ja.
                    throw new UserAlreadyExistsError('An Exface user with username "' . $row['USERNAME'] . '" already exists.');
                } else {
                    // Nein.
                    $userRow['oldusername'] = $exfUserSheet->getCellValue('USERNAME', 0);
                }
            }
            
            // Passwort            
            if (($password = $row['PASSWORD']) && PasswordHashDataType::isHash($password) === false) {
                $userRow['password'] = $password;
            }
            
            // Name, Locale, Email bestimmen.
            $userRow['fullname'] = trim($row['FIRST_NAME'] . ' ' . $row['LAST_NAME']);
            if (($locale = $row['LOCALE']) && array_key_exists($locale, $this->localLangMap)) {
                $userRow['manager_language'] = $this->localLangMap[$locale];
            }
            if ($email = $row['EMAIL']) {
                $userRow['email'] = $email;
            }
            
            if (! $userRow['username']) {
                throw new ActionInputMissingError($this, 'Mandatory username is missing.');
            }
            
            // Existieren bereits Web- oder Managernutzer mit dem Nutzernamen, bzw. dem alten
            // Nutzernamen wenn der Nutzer gerade umbenannt wird?
            $oldModxWebUserExists = $userRow['oldusername'] ? $modxCmsConnector->isModxWebUser($userRow['oldusername']) : false;
            $oldModxMgrUserExists = $userRow['oldusername'] ? $modxCmsConnector->isModxMgrUser($userRow['oldusername']) : false;
            $modxWebUserExists = $modxCmsConnector->isModxWebUser($userRow['username']);
            $modxMgrUserExists = $modxCmsConnector->isModxMgrUser($userRow['username']);
            $oldModxUserExists = $oldModxWebUserExists || $oldModxMgrUserExists;
            $modxUserExists = $modxWebUserExists || $modxMgrUserExists;
            
            if ($oldModxUserExists) {
                if ($modxUserExists) {
                    // Der Nutzer wird gerade umbenannt. Es existiert ein Web- oder Manager-
                    // nutzer mit dem alten Nutzernamen. Es existiert ebenfalls ein Web- oder
                    // Managernutzer mit dem neuen Nutzernamen.
                    
                    // Loeschen der/des Nutzer(s) mit dem alten Namen.
                    if ($oldModxWebUserExists) {
                        $modUser->delete($modxCmsConnector->getModxWebUserId($userRow['oldusername']));
                    }
                    if ($oldModxMgrUserExists) {
                        $modManager->delete($modxCmsConnector->getModxMgrUserId($userRow['oldusername']));
                    }
                    // Update/Loeschen der/des Nutzer(s) mit dem neuen Namen.
                    if ($modxMgrUserExists) {
                        // Es existiert ein Managernutzer mit dem neuen Namen, welcher
                        // aktualisiert wird. Ein ebenfalls exisitierender Webnutzer mit dem
                        // neuen Namen wird geloescht.
                        if ($modxWebUserExists) {
                            $modUser->delete($modxCmsConnector->getModxWebUserId($userRow['username']));
                        }
                        $modManager->edit($modxCmsConnector->getModxMgrUserId($userRow['username']));
                        $modManager->fromArray($userRow);
                        $this->saveUser($modManager);
                    } elseif ($modxWebUserExists) {
                        // Es existiert ein Webnutzer mit dem neuen Namen, welcher
                        // aktualisiert wird.
                        $modUser->edit($modxCmsConnector->getModxWebUserId($userRow['username']));
                        $modUser->fromArray($userRow);
                        $this->saveUser($modUser);
                    }
                } else {
                    // Der Nutzer wird gerade umbenannt. Es existiert ein Web- oder Manager-
                    // nutzer mit dem alten Nutzernamen. Es existiert kein Web- oder Manager-
                    // nutzer mit dem neuen Nutzernamen.
                    
                    // Update des Nutzers mit dem alten Namen.
                    if ($oldModxMgrUserExists) {
                        // Es existiert ein Managernutzer mit dem alten Namen, welcher
                        // aktualisiert wird. Ein ebenfalls exisitierender Webnutzer mit dem
                        // alten Namen wird geloescht.
                        if ($oldModxWebUserExists) {
                            $modUser->delete($modxCmsConnector->getModxWebUserId($userRow['oldusername']));
                        }
                        $modManager->edit($modxCmsConnector->getModxMgrUserId($userRow['oldusername']));
                        $modManager->fromArray($userRow);
                        $this->saveUser($modManager);
                    } elseif ($oldModxWebUserExists) {
                        // Es existiert ein Webnutzer mit dem alten Namen, welcher
                        // aktualisiert wird.
                        $modUser->edit($modxCmsConnector->getModxWebUserId($userRow['oldusername']));
                        $modUser->fromArray($userRow);
                        $this->saveUser($modUser);
                    }
                }
            } else {
                if ($modxUserExists) {
                    // Der Nutzer wird nicht umbenannt. Es existiert bereits ein Web- oder
                    // Managernutzer mit dem Nutzernamen.
                    
                    // Update des Nutzers.
                    if ($modxMgrUserExists) {
                        // Es existiert ein Managernutzer mit dem Namen, welcher aktualisiert
                        // wird. Ein ebenfalls exisitierender Webnutzer mit dem Namen wird
                        // geloescht.
                        if ($modxWebUserExists) {
                            $modUser->delete($modxCmsConnector->getModxWebUserId($userRow['username']));
                        }
                        $modManager->edit($modxCmsConnector->getModxMgrUserId($userRow['username']));
                        $modManager->fromArray($userRow);
                        $this->saveUser($modManager);
                    } elseif ($modxWebUserExists) {
                        // Es existiert ein Webnutzer mit dem Namen, welcher aktualisiert
                        // wird.
                        $modUser->edit($modxCmsConnector->getModxWebUserId($userRow['username']));
                        $modUser->fromArray($userRow);
                        $this->saveUser($modUser);
                    }
                } else {
                    // Der Nutzer wird nicht umbenannt. Es existiert kein Web- oder Manager-
                    // nutzer mit dem Nutzernamen.
                    
                    // Erstellen eines Webnutzers.
                    $modUser->close();
                    $modUser->set('password', $modUser->genPass(8, 'Aa0'));
                    $modUser->set('email', $this->getEmailDefault($row['USERNAME'], $row['FIRST_NAME'], $row['LAST_NAME']));
                    $modUser->fromArray($userRow);
                    $this->saveUser($modUser);
                }
            }
        }
        
        // Die Modx-Session wird geschlossen und die zuvor geoeffnete Session
        // wiederhergestellt.
        $modxSessionManager->sessionClose();
        
        return ResultFactory::createMessageResult($task, 'Exface user saved.');
    }

    /**
     * Saves the passed user. The email is first replaced by a unique email and after saving
     * the user written directly to the database to avoid the unique email policy of Modx.
     * 
     * @param \modUsers|\modManagers $modUser
     * @throws ActionRuntimeError
     * @return ModxUserSave
     */
    private function saveUser($modUser)
    {
        if (! ($modUser instanceof \modUsers || $modUser instanceof \modManagers)) {
            throw new ActionRuntimeError($this, 'Passed modUser is not an instance of modUsers or modManagers.');
        }
        
        $modx = $this->getWorkbench()->getApp('exface.ModxCmsConnector')->getModx();
        
        // Die am User gesetzte E-Mail-Adresse wird zunachst gesichert, anschliessend durch
        // eine generierte ersetzt. Nach dem Speichern wird sie wiederhergestellt, s.u.
        $modUserEmail = $modUser->get('email');
        $modUser->set('email', $this->getEmailUnique());
        
        // Speichern des geaenderten Nutzers.
        $id = $modUser->save();
        if ($id === false) {
            throw new ActionRuntimeError($this, 'Error saving modx user "' . $modUser->get('username') . '".');
        }
        
        // Die E-Mail Adresse wird direkt in der Datenbank gesetzt. Beim normalen Speichern
        // erfolgt eine Ueberpruefung ob sie einzigartig ist, diese Einschraenkung gilt aber
        // in anderen Programmen nicht zwangsweise (z.B. zwei Accounts des gleichen Nutzers).
        $modx->db->update([
            'email' => $modUserEmail
        ], ($modUser instanceof \modManagers ? $modx->getFullTableName('user_attributes') : $modx->getFullTableName('web_user_attributes')), 'internalKey = ' . $id);
        
        return $this;
    }

    /**
     * Returns a unique standard email-address.
     * 
     * @return string
     */
    private function getEmailUnique()
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyz';
        $charactersLength = strlen($characters);
        $localLength = 20;
        $domain = 'mydomain.com';
        $local = '';
        for ($i = 0; $i < $localLength; $i ++) {
            $local .= $characters[mt_rand(0, $charactersLength - 1)];
        }
        return $local . '@' . $domain;
    }

    /**
     * Returns an email-address generated from the schema in the ModxCmsConnector config and
     * the passed parameters.
     * 
     * @param string $username
     * @param string $firstname
     * @param string $lastname
     * @return string
     */
    private function getEmailDefault($username, $firstname, $lastname)
    {
        $email = $this->getWorkbench()->getApp('exface.ModxCmsConnector')->getConfig()->getOption('EMAIL_SCHEMA_DEFAULT');
        $email = str_replace('[#username#]', $username, $email);
        $email = str_replace('[#firstname#]', $firstname, $email);
        $email = str_replace('[#lastname#]', $lastname, $email);
        return $email;
    }
}
?>