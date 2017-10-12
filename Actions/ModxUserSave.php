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
        $this->localLangMap = $this->getApp('exface.ModxCmsConnector')->getConfig()->getOption('USERS.LOCALE_LANGUAGE_MAPPING')->toArray();
    }
    
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
        
        // DataSheet zum Bestimmen des alten Nutzernamens erzeugen.
        $exfUserObj = $this->getWorkbench()->model()->getObject('exface.Core.USER');
        $exfUserSheet = DataSheetFactory::createFromObject($exfUserObj);
        $exfUserSheet->getColumns()->addFromAttribute($exfUserObj->getAttribute('USERNAME'));
        
        foreach ($this->getInputDataSheet()->getRows() as $row) {
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
                $userContextScope = $this->getWorkbench()->context()->getScopeUser();
                try {
                    if ($userContextScope->getUserByName($row['USERNAME'])) {
                        // Ja.
                        throw new UserAlreadyExistsError('An Exface user with username "' . $row['USERNAME'] . '" already exists.');
                    }
                } catch (UserNotFoundError $unfe) {}
                // Nein.
                $userRow['oldusername'] = $exfUserSheet->getCellValue('USERNAME', 0);
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
                        $this->deleteMgrUser($modxCmsConnector->getModxMgrUserId($userRow['oldusername']), $userRow['oldusername']);
                    }
                    // Update/Loeschen der/des Nutzer(s) mit dem neuen Namen.
                    if ($modxMgrUserExists) {
                        // Es existiert ein Managernutzer mit dem neuen Namen, welcher
                        // aktualisiert wird. Ein ebenfalls exisitierender Webnutzer mit dem
                        // neuen Namen wird geloescht.
                        if ($modxWebUserExists) {
                            $modUser->delete($modxCmsConnector->getModxWebUserId($userRow['username']));
                        }
                        $this->updateMgrUser($modxCmsConnector->getModxMgrUserId($userRow['username']), $userRow);
                    } elseif ($modxWebUserExists) {
                        // Es existiert ein Webnutzer mit dem neuen Namen, welcher
                        // aktualisiert wird.
                        $modUser->edit($modxCmsConnector->getModxWebUserId($userRow['username']));
                        $modUser->fromArray($userRow);
                        $this->saveWebUser($modUser);
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
                        $this->updateMgrUser($modxCmsConnector->getModxMgrUserId($userRow['oldusername']), $userRow);
                    } elseif ($oldModxWebUserExists) {
                        // Es existiert ein Webnutzer mit dem alten Namen, welcher
                        // aktualisiert wird.
                        $modUser->edit($modxCmsConnector->getModxWebUserId($userRow['oldusername']));
                        $modUser->fromArray($userRow);
                        $this->saveWebUser($modUser);
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
                        $this->updateMgrUser($modxCmsConnector->getModxMgrUserId($userRow['username']), $userRow);
                    } elseif ($modxWebUserExists) {
                        // Es existiert ein Webnutzer mit dem Namen, welcher aktualisiert
                        // wird.
                        $modUser->edit($modxCmsConnector->getModxWebUserId($userRow['username']));
                        $modUser->fromArray($userRow);
                        $this->saveWebUser($modUser);
                    }
                } else {
                    // Der Nutzer wird nicht umbenannt. Es existiert kein Web- oder Manager-
                    // nutzer mit dem Nutzernamen.
                    
                    // Erstellen eines Webnutzers.
                    $modUser->close();
                    $modUser->set('password', $modUser->genPass(8, 'Aa0'));
                    $modUser->set('email', $this->getEmailDefault($row['USERNAME'], $row['FIRST_NAME'], $row['LAST_NAME']));
                    $modUser->fromArray($userRow);
                    $this->saveWebUser($modUser);
                }
            }
        }
        
        $this->setResult('');
        $this->setResultMessage('Exface user saved.');
    }

    /**
     * Saves the passed user. The email is first replaced by a unique email and after saving
     * the user written directly to the database to avoid the unique email policy of Modx.
     * 
     * @param \modUsers $modUser
     * @throws ActionRuntimeError
     * @return ModxUserSave
     */
    private function saveWebUser(\modUsers $modUser)
    {
        $modx = $this->getWorkbench()->getApp('exface.ModxCmsConnector')->getModx();
        
        // Die am User gesetzte E-Mail-Adresse wird zunachst gesichert, anschliessend durch
        // eine generierte ersetzt. Nach dem Speichern wird sie wiederhergestellt, s.u.
        $modUserEmail = $modUser->get('email');
        $modUser->set('email', $this->getEmailUnique());
        
        // Speichern des geaenderten Nutzers.
        $id = $modUser->save(false);
        if ($id === false) {
            throw new ActionRuntimeError($this, 'Error saving modx user "' . $modUser->get('username') . '".');
        }
        
        // Die E-Mail Adresse wird direkt in der Datenbank gesetzt. Beim normalen Speichern
        // erfolgt eine Ueberpruefung ob sie einzigartig ist, diese Einschraenkung gilt aber
        // in anderen Programmen nicht zwangsweise (z.B. zwei Accounts des gleichen Nutzers).
        $modx->db->update(['email' => $modUserEmail], $modx->getFullTableName('web_user_attributes'), 'internalKey = ' . $id);
        
        return $this;
    }

    /**
     * Updates the Modx manager user with the given id using the given userRow array.
     * 
     * $userRow e.g.
     * [
     *      "username" => "test",
     *      "fullname" => "Test Testmann",
     *      "manager_language" => "english"
     * ]
     * 
     * No checks are done. Especially there is no check if the new username already exists if
     * the user is renamed (this should be done before).
     * 
     * @param integer $id
     * @param string[] $userRow
     * @return ModxUserSave
     */
    private function updateMgrUser($id, $userRow)
    {
        // The settable fields in the modx_manager_users table.
        $userFields = [
            'username',
            'password'
        ];
        // The settable fields in the modx_user_attributes table.
        $userAttributeFields = [
            'fullname',
            'role',
            'email',
            'phone',
            'mobilephone',
            'blocked',
            'blockeduntil',
            'blockedafter',
            'logincount',
            'lastlogin',
            'thislogin',
            'failedlogincount',
            'sessionid',
            'dob',
            'gender',
            'country',
            'street',
            'city',
            'state',
            'zip',
            'fax',
            'photo',
            'comment'
        ];
        // The settable fields in the modx_user_settings table.
        $userSettingsFields = [
            'manager_language'
        ];
        
        $modx = $this->getWorkbench()->getApp('exface.ModxCmsConnector')->getModx();
        
        // Bestimmen welche Felder in welche Tabelle geschrieben werden muessen.
        $updateUserFields = [];
        $updateUserAttributeFields = [];
        $updateUserSettings = [];
        foreach ($userRow as $key => $value) {
            if (in_array($key, $userFields)) {
                $updateUserFields[$key] = $value;
            }
            if (in_array($key, $userAttributeFields)) {
                $updateUserAttributeFields[$key] = $value;
            }
            if (in_array($key, $userSettingsFields)) {
                $updateUserSettings[$key] = $value;
            }
        }
        
        // modx_manager_users schreiben.
        if (count($updateUserFields) > 0) {
            $modx->db->update($updateUserFields, $modx->getFullTableName('manager_users'), 'id = "' . $id . '"');
        }
        
        // modx_user_attributes schreiben.
        if (count($updateUserAttributeFields) > 0) {
            $modx->db->update($updateUserAttributeFields, $modx->getFullTableName('user_attributes'), 'internalKey = "' . $id . '"');
        }
        
        // modx_user_settings schreiben.
        $userSettings = $modx->getFullTableName('user_settings');
        foreach ($updateUserSettings as $key => $value) {
            $result = $modx->db->select('setting_value', $userSettings, 'user = ' . $id . ' AND setting_name = "' . $key . '"');
            if ($modx->db->getRecordCount($result) > 0) {
                $result = $modx->db->update(['setting_value' => $value], $userSettings, 'user = ' . $id . ' AND setting_name = "' . $key . '"');
            } else {
                $result = $modx->db->insert(['user' => $id, 'setting_name' => $key, 'setting_value' => $value], $userSettings);
            }
        }
        
        return $this;
    }

    /**
     * Deletes the Modx manager user with the given id.
     * 
     * @param integer $id
     * @return ModxUserSave
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
        for ($i = 0; $i < $localLength; $i++) {
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