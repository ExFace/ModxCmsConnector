<?php
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Exceptions\RuntimeException;

/**
 * Creates an exface user using the passed $username, $firstname and $lastname.
 * 
 * @param string $username
 * @param string $firstname
 * @param string $lastname
 * @return DataSheetInterface
 */
function createExfaceUser($username, $firstname, $lastname)
{
    global $exface;
    
    $user = $exface->model()->getObject('exface.Core.USER');
    $exf_user = DataSheetFactory::createFromObject($user);
    $exf_user->getColumns()->addFromAttribute($user->getAttribute('FIRST_NAME'));
    $exf_user->getColumns()->addFromAttribute($user->getAttribute('LAST_NAME'));
    $exf_user->getColumns()->addFromAttribute($user->getAttribute('USERNAME'));
    $exf_user->addRow([
        'FIRST_NAME' => $firstname,
        'LAST_NAME' => $lastname,
        'USERNAME' => $username
    ]);
    $exf_user->dataCreate();
    
    return $exf_user;
}

/**
 * Reads an exface user using the passed $username.
 * 
 * @param string $username
 * @throws RuntimeException
 * @return DataSheetInterface|null
 */
function readExfaceUser($username)
{
    global $exface;
    
    $user = $exface->model()->getObject('exface.Core.USER');
    $exf_user = DataSheetFactory::createFromObject($user);
    foreach ($user->getAttributes() as $attr) {
        $exf_user->getColumns()->addFromAttribute($attr);
    }
    $exf_user->getFilters()->addConditionsFromString($user, 'USERNAME', $username, EXF_COMPARATOR_EQUALS);
    $exf_user->dataRead();
    // Der Filter nach dem Username wird entfernt. Das ist wichtig fuer das Loeschen. Das
    // Objekt axenox.TestMan.TEST_LOG enthaelt naemlich eine Relation auf User, dadurch
    // werden beim Loeschen auch Testlogs geloescht, welche der Nutzer erstellt hat
    // (Cascading Delete). Die Tabelle test_log befindet sich aber in einer anderen
    // Datenbank als exf_user, es kommt daher zu einem SQL-Error wenn versucht wird die Uid
    // aus dem Username zu ermitteln.
    $exf_user->getFilters()->removeAll();
    
    if ($exf_user->countRows() == 0) {
        return null;
    } elseif ($exf_user->countRows() == 1) {
        return $exf_user;
    } else {
        throw new RuntimeException('More than one Exface users with username "' . $username . '" defined.');
    }
}

/**
 * Updates an exface user with the passed $username, $firstname and $lastname. If a DataSheet is
 * passed it is used, otherwise the user is read from the database using the passed
 * $username_old.
 * 
 * @param string $username_old
 * @param string $username
 * @param string $firstname
 * @param string $lastname
 * @param DataSheetInterface $exf_user
 * @return DataSheetInterface
 */
function updateExfaceUser($username_old, $username, $firstname, $lastname, $exf_user = null)
{
    if (! $exf_user) {
        $exf_user = readExfaceUser($username_old);
        if (! $exf_user) {
            throw new RuntimeException('No Exface user with username "' . $username_old . '" defined.');
        }
    }
    $exf_user->setCellValue('FIRST_NAME', 0, $firstname);
    $exf_user->setCellValue('LAST_NAME', 0, $lastname);
    $exf_user->setCellValue('USERNAME', 0, $username);
    // Wichtig, da der Username auch das Label ist.
    $exf_user->setCellValue('LABEL', 0, $username);
    $exf_user->dataUpdate();
    
    return $exf_user;
}

/**
 * Deletes an exface user. If a DataSheet is passed it is used, otherwise the user is read from
 * the database using the passed $username.
 * 
 * @param string $username
 * @param DataSheetInterface $exf_user
 * @return DataSheetInterface|null
 */
function deleteExfaceUser($username, $exf_user = null)
{
    if (! $exf_user) {
        $exf_user = readExfaceUser($username);
    }
    if ($exf_user) {
        $exf_user->dataDelete();
    }
    
    return $exf_user;
}
