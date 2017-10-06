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
        
        require_once MODX_BASE_PATH . 'assets' . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'MODxAPI' . DIRECTORY_SEPARATOR . 'modUsers.php';
        $modx = $this->getWorkbench()->getApp('exface.ModxCmsConnector')->getModx();
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
            
            if ($userRow['oldusername'] && $modxCmsConnector->isModxWebUser($userRow['oldusername'])) {
                if ($modxCmsConnector->isModxWebUser($userRow['username'])) {
                    // Loeschen des Webnutzers mit dem alten Namen, Update des Webnutzers mit
                    // dem neuen Namen.
                    $modUser->delete($modxCmsConnector->getModxWebUserId($userRow['oldusername']));
                    $modUser->edit($modxCmsConnector->getModxWebUserId($userRow['username']));
                } else {
                    // Update des Webnutzers mit dem alten Namen.
                    $modUser->edit($modxCmsConnector->getModxWebUserId($userRow['oldusername']));
                }
            } else {
                if ($modxCmsConnector->isModxWebUser($userRow['username'])) {
                    // Update des Webnutzers.
                    $modUser->edit($modxCmsConnector->getModxWebUserId($userRow['username']));
                } elseif ($modxCmsConnector->isModxMgrUser($userRow['username'])) {
                    // Update des Managernutzers.
                    // TODO
                } else {
                    // Erstellen eines Webnutzers.
                    $modUser->close();
                    $modUser->set('password', $modUser->genPass(8, 'Aa0'));
                    $modUser->set('email', $this->getEmailDefault($row['USERNAME'], $row['FIRST_NAME'], $row['LAST_NAME']));
                }
            }
            $modUser->fromArray($userRow);
            // Die am User gesetzte E-Mail-Adresse wird zunachst gesichert, anschliessend durch
            // eine generierte ersetzt. Nach dem Speichern wird sie wiederhergestellt, s.u.
            $modUserEmail = $modUser->get('email');
            $modUser->set('email', $this->getEmailUnique());
            
            // Speichern des geaenderten Nutzers.
            $id = $modUser->save(false, true);
            if ($id === false) {
                throw new ActionRuntimeError($this, 'Error saving modx user "' . $modUser->get('username') . '".');
            }
            
            // Die E-Mail Adresse wird direkt in der Datenbank gesetzt. Beim normalen Speichern
            // erfolgt eine Ueberpruefung ob sie einzigartig ist, diese Einschraenkung gilt aber
            // in anderen Programmen nicht zwangsweise (z.B. zwei Accounts des gleichen Nutzers).
            $modx->db->update('wua.email = "' . $modUserEmail . '"', $modx->getFullTableName('web_user_attributes') . ' wua', 'wua.internalKey = ' . $id);
        }
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