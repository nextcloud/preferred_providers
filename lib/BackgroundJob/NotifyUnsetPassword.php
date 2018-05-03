<?php
declare (strict_types = 1);
/**
 * @copyright Copyright (c) 2018 John Molakvoæ (skjnldsv) <skjnldsv@protonmail.com>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Preferred_Providers\BackgroundJob;

use OC\BackgroundJob\TimedJob;
use OC\SystemConfig;
use OCA\Preferred_Providers\Mailer\SetPasswordMailHelper;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\ILogger;

class NotifyUnsetPassword extends TimedJob {

    /** @var string */
    private $appName;

    /** @var ILogger */
    private $logger;

    /** @var IDBConnection */
    private $connection;

    /** @var SystemConfig */
    private $systemConfig;

    /** @var IConfig */
    private $config;

    /** @var SetPasswordMailHelper */
    private $mailHelper;

    public function __construct() {
        // Run once per 5 minutes
        $this->setInterval(5 * 60);
    }

    public function run($argument) {
        $this->appName = 'preferred_providers';
        $this->logger = \OC::$server->getLogger();
        $this->connection = \OC::$server->getDatabaseConnection();
        $this->systemConfig = \OC::$server->getSystemConfig();
        $this->config = \OC::$server->getConfig();

        $this->mailHelper = new SetPasswordMailHelper(
            $this->appName,
            \OC::$server->getThemingDefaults(),
            \OC::$server->getURLGenerator(),
            \OC::$server->getL10N($this->appName),
            \OC::$server->getMailer(),
            \OC::$server->getConfig(),
            \OC::$server->getCrypto()
        );
       
        // process if token is 5min old
        $users = $this->getUsersForUserLowerThanValue($this->appName, 'remind_password', time() - 5 * 60);
        foreach($users as $userId) {
            $emailTemplate = $this->mailHelper->generateTemplate($userId);
            try {
                $this->mailHelper->sendMail($userId, $emailTemplate);
                // only send one mail
                $this->config->deleteUserValue($userId, $this->appName, 'remind_password');
                $this->logger->debug('Password definition mail sent to '.$userId, ['app' => 'Preferred_Providers']);
            } catch(Exception $e) {
                $this->logger->debug('Error while sending the password definition mail to  '.$userId, ['app' => 'Preferred_Providers']);
            }
        }
    }

    /**
     * Determines the users that have the given value set for a specific app-key-pair
     *
     * @param string $appName the app to get the user for
     * @param string $key the key to get the user for
     * @param string $value the value to get the user for
     * @return array of user IDs
     */
    private function getUsersForUserLowerThanValue($appName, $key, $value) {

        $sql = 'SELECT `userid` FROM `*PREFIX*preferences` ' .
            'WHERE `appid` = ? AND `configkey` = ? ';

        if ($this->systemConfig->getValue('dbtype', 'sqlite') === 'oci') {
            //oracle hack: need to explicitly cast CLOB to CHAR for comparison
            $sql .= 'AND to_char(`configvalue`) < ?';
        } else {
            $sql .= 'AND `configvalue` < ?';
        }

        $result = $this->connection->executeQuery($sql, array($appName, $key, $value));

        $userIDs = array();
        while ($row = $result->fetch()) {
            $userIDs[] = $row['userid'];
        }

        return $userIDs;
    }
}
