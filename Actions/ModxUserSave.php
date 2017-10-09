<?php
namespace exface\ModxCmsConnector\Actions;

use exface\Core\CommonLogic\AbstractAction;
use exface\Core\Exceptions\Actions\ActionInputMissingError;
use exface\Core\Exceptions\Actions\ActionInputInvalidObjectError;
use exface\ModxCmsConnector\CmsConnectors\Modx;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Exceptions\Actions\ActionRuntimeError;

/**
 * Creates or Updates a modx web-user or Updates a modx mgr-user.
 * 
 * This Action can be called with an InputDataSheet 'exface.Core.USER' containing columns
 *     'USERNAME', 'FIRST_NAME', 'LAST_NAME', 'LOCALE'
 * 
 * @author SFL
 *
 */
class ModxUserSave extends AbstractAction
{

    // Mappt Sprachen auf Locales
    // TODO: unvollstaendig siehe Sprachdateien in manager/includes/lang
    private $local_lang_map = [
        
        'en_GB' => 'english-british',
        'en_US' => 'english',
        'fr_FR' => 'francais-utf8',
        'de_DE' => 'german',
        'default' => ''
    ];

    // Mappt Laendercodes auf Locales
    // TODO: unvollstaendig siehe Laendercodes in manager/includes/lang/country/german_country.inc.php
    private $local_country_map = [
        'fr_FR' => '73',
        'de_DE' => '81',
        'en_GB' => '222',
        'en_US' => '223',
        'default' => ''
    ];

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
        $exfUserObj = $this->getWorkbench()->model()->getObject('exface.Core.USER');
        $exfUserSheet = DataSheetFactory::createFromObject($exfUserObj);
        $exfUserSheet->getColumns()->addFromAttribute($exfUserObj->getAttribute('USERNAME'));
        
        foreach ($this->getInputDataSheet()->getRows() as $row) {
            $userRow = [];
            $userRow['username'] = $row['USERNAME'];
            
            // $oldusername wird nicht uebergeben ist aber wichtig um zu erkennen ob ein
            // Nutzer umbenannt wird. Wird daher aus der Datenbank eingelesen.
            $exfUserSheet->removeRows();
            $exfUserSheet->getFilters()->removeAll();
            $exfUserSheet->getFilters()->addConditionsFromString($exfUserObj, $exfUserObj->getUidAttributeAlias(), $row[$exfUserObj->getUidAttributeAlias()], EXF_COMPARATOR_EQUALS);
            $exfUserSheet->dataRead();
            if ($exfUserSheet->countRows() == 1 && $exfUserSheet->getCellValue('USERNAME', 0) !== $row['USERNAME']) {
                $userRow['oldusername'] = $exfUserSheet->getCellValue('USERNAME', 0);
            }
            
            $userRow['fullname'] = trim($row['FIRST_NAME'] . ' ' . $row['LAST_NAME']);
            if ($row['LOCALE']) {
                $userRow['country'] = array_key_exists($row['LOCALE'], $this->local_country_map) ? $this->local_country_map[$row['LOCALE']] : $this->local_country_map['default'];
                // $userRow['manager_language'] = array_key_exists($row['LOCALE'], $this->local_lang_map) ? $this->local_lang_map[$row['LOCALE']] : $this->local_lang_map['default'];
            }
            
            if (! $userRow['username']) {
                throw new ActionInputMissingError($this, 'Mandatory username is missing.');
            }
            
            $oldModxWebUserExists = $userRow['oldusername'] ? $modxCmsConnector->isModxWebUser($userRow['oldusername']) : false;
            $oldModxMgrUserExists = $userRow['oldusername'] ? $modxCmsConnector->isModxMgrUser($userRow['oldusername']) : false;
            $modxWebUserExists = $modxCmsConnector->isModxWebUser($userRow['username']);
            $modxMgrUserExists = $modxCmsConnector->isModxMgrUser($userRow['username']);
            $oldModxUserExists = $oldModxWebUserExists || $oldModxMgrUserExists;
            $modxUserExists = $modxWebUserExists || $modxMgrUserExists;
            
            if ($oldModxUserExists) {
                if ($modxUserExists) {
                    // Loeschen des Nutzers mit dem alten Namen.
                    if ($oldModxWebUserExists) {
                        $modUser->delete($modxCmsConnector->getModxWebUserId($userRow['oldusername']));
                    }
                    if ($oldModxMgrUserExists) {
                        $this->deleteMgrUser($modxCmsConnector->getModxMgrUserId($userRow['oldusername']), $userRow['oldusername']);
                    }
                    // Update des Nutzers mit dem neuen Namen.
                    if ($modxMgrUserExists) {
                        if ($modxWebUserExists) {
                            $modUser->delete($modxCmsConnector->getModxWebUserId($userRow['username']));
                        }
                        $this->updateMgrUser($modxCmsConnector->getModxMgrUserId($userRow['username']), $userRow);
                    } elseif ($modxWebUserExists) {
                        $modUser->edit($modxCmsConnector->getModxWebUserId($userRow['username']));
                        $modUser->fromArray($userRow);
                        $this->saveWebUser($modUser);
                    }
                } else {
                    // Update des Nutzers mit dem alten Namen.
                    if ($oldModxMgrUserExists) {
                        if ($oldModxWebUserExists) {
                            $modUser->delete($modxCmsConnector->getModxWebUserId($userRow['oldusername']));
                        }
                        $this->updateMgrUser($modxCmsConnector->getModxMgrUserId($userRow['oldusername']), $userRow);
                    } elseif ($oldModxWebUserExists) {
                        $modUser->edit($modxCmsConnector->getModxWebUserId($userRow['oldusername']));
                        $modUser->fromArray($userRow);
                        $this->saveWebUser($modUser);
                    }
                }
            } else {
                if ($modxUserExists) {
                    // Update des Nutzers.
                    if ($modxMgrUserExists) {
                        if ($modxWebUserExists) {
                            $modUser->delete($modxCmsConnector->getModxWebUserId($userRow['username']));
                        }
                        $this->updateMgrUser($modxCmsConnector->getModxMgrUserId($userRow['username']), $userRow);
                    } elseif ($modxWebUserExists) {
                        $modUser->edit($modxCmsConnector->getModxWebUserId($userRow['username']));
                        $modUser->fromArray($userRow);
                        $this->saveWebUser($modUser);
                    }
                } else {
                    // Erstellen eines Webnutzers.
                    $modUser->close();
                    $modUser->set('password', $modUser->genPass(8, 'Aa0'));
                    $modUser->set('email', $this->getEmailDefault($row['USERNAME'], $row['FIRST_NAME'], $row['LAST_NAME']));
                    $modUser->fromArray($userRow);
                    $this->saveWebUser($modUser);
                }
            }
        }
    }

    /**
     * 
     * @param \modUsers $modUser
     * @throws ActionRuntimeError
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
        $modx->db->update('wua.email = "' . $modUserEmail . '"', $modx->getFullTableName('web_user_attributes') . ' wua', 'wua.internalKey = ' . $id);
    }

    /**
     * Updates the Modx manager user with the given id using the given userRow array.
     * 
     * $userRow e.g.
     * [
     *      "username" => "test",
     *      "fullname" => "Test Testmann",
     *      "country" => 81
     * ]
     * 
     * No checks are done. Especially there is no check if the new username already exists if
     * the user is renamed (this should be done before).
     * 
     * @param integer $id
     * @param string[] $userRow
     * @param boolean $fire_events
     * @return boolean|string
     */
    private function updateMgrUser($id, $userRow)
    {
        // The setable fields in the modx_manager_users table.
        $user_fields = [
            'username',
            'password'
        ];
        // The setable fields in the modx_user_attributes table.
        $user_attribute_fields = [
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
        
        $modx = $this->getWorkbench()->getApp('exface.ModxCmsConnector')->getModx();
        
        $update_user_fields = [];
        $update_user_attribute_fields = [];
        foreach ($userRow as $key => $value) {
            if (in_array($key, $user_fields)) {
                $update_user_fields[$key] = $value;
            }
            if (in_array($key, $user_attribute_fields)) {
                $update_user_attribute_fields[$key] = $value;
            }
        }
        
        if (count($update_user_fields) > 0) {
            $modx->db->update($update_user_fields, $modx->getFullTableName('manager_users'), 'id = "' . $id . '"');
        }
        
        if (count($update_user_attribute_fields) > 0) {
            $modx->db->update($update_user_attribute_fields, $modx->getFullTableName('user_attributes'), 'internalKey = "' . $id . '"');
        }
    }

    /**
     * Deletes the Modx manager user with the given id.
     * 
     * @param integer $id
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
    }

    /**
     * Returns a unique standard email-address (e.g. 'temp5@mydomain.com').
     * 
     * @return string
     */
    private function getEmailUnique()
    {
        // Vorhandene E-Mail Adressen auslesen.
        $modx = $this->getWorkbench()->getApp('exface.ModxCmsConnector')->getModx();
        $result = $modx->db->select('wua.email as email', $modx->getFullTableName('web_user_attributes') . ' wua');
        $emails = [];
        while ($row = $modx->db->getRow($result)) {
            $emails[] = $row['email'];
        }
        
        // Eine noch nicht existierende E-Mail Adresse erzeugen.
        $local = 'temp';
        $domain = 'mydomain.com';
        $email = $local . '@' . $domain;
        if (in_array($email, $emails)) {
            $i = 2;
            do {
                $email = $local . $i ++ . '@' . $domain;
            } while (in_array($email, $emails));
        }
        
        return $email;
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