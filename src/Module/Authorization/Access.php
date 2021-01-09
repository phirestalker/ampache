<?php
/*
 * vim:set softtabstop=4 shiftwidth=4 expandtab:
 *
 * LICENSE: GNU Affero General Public License, version 3 (AGPL-3.0-or-later)
 * Copyright 2001 - 2020 Ampache.org
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 *
 */

declare(strict_types=0);

namespace Ampache\Module\Authorization;

use Ampache\Module\Authorization\Check\FunctionCheckerInterface;
use Ampache\Module\Authorization\Check\PrivilegeCheckerInterface;
use Ampache\Module\System\AmpError;
use Ampache\Module\System\Dba;

/**
 * Access Class
 *
 * This class handles the access list mojo for Ampache, it is meant to restrict
 * access based on IP and maybe something else in the future.
 *
 */
class Access
{
    // Variables from DB
    /**
     * @var integer $id
     */
    public $id;

    /**
     * @var string $name
     */
    public $name;

    /**
     * @var string $start
     */
    public $start;

    /**
     * @var string $end
     */
    public $end;

    /**
     * @var integer $level
     */
    public $level;

    /**
     * @var integer $user
     */
    public $user;

    /**
     * @var string $type
     */
    public $type;

    /**
     * @var boolean $enabled
     */
    public $enabled;

    /**
     * constructor
     *
     * Takes an ID of the access_id dealie :)
     * @param integer|null $access_id
     */
    public function __construct($access_id)
    {
        /* Assign id for use in get_info() */
        $this->id = (int)$access_id;

        $info = $this->has_info();
        foreach ($info as $key => $value) {
            $this->$key = $value;
        }

        return true;
    }

    /**
     * has_info
     *
     * Gets the vars for $this out of the database.
     * @return array
     */
    private function has_info()
    {
        $sql        = 'SELECT * FROM `access_list` WHERE `id` = ?';
        $db_results = Dba::read($sql, array($this->id));

        return Dba::fetch_assoc($db_results);
    }

    /**
     * _verify_range
     *
     * This outputs an error if the IP range is bad.
     * @param string $startp
     * @param string $endp
     * @return boolean
     */
    public static function _verify_range($startp, $endp)
    {
        $startn = @inet_pton($startp);
        $endn   = @inet_pton($endp);

        if (!$startn && $startp != '0.0.0.0' && $startp != '::') {
            AmpError::add('start', T_('An Invalid IPv4 / IPv6 Address was entered'));

            return false;
        }
        if (!$endn) {
            AmpError::add('end', T_('An Invalid IPv4 / IPv6 Address was entered'));
        }

        if (strlen(bin2hex($startn)) != strlen(bin2hex($endn))) {
            AmpError::add('start', T_('IP Address version mismatch'));
            AmpError::add('end', T_('IP Address version mismatch'));

            return false;
        }

        return true;
    }

    /**
     * update
     *
     * This function takes a named array as a datasource and updates the current
     * access list entry.
     * @param array $data
     * @return boolean
     */
    public function update(array $data)
    {
        if (!self::_verify_range($data['start'], $data['end'])) {
            return false;
        }

        $start   = @inet_pton($data['start']);
        $end     = @inet_pton($data['end']);
        $name    = $data['name'];
        $type    = self::validate_type($data['type']);
        $level   = (int)$data['level'];
        $user    = $data['user'] ?: '-1';
        $enabled = make_bool($data['enabled']) ? 1 : 0;

        $sql = 'UPDATE `access_list` SET `start` = ?, `end` = ?, `level` = ?, ' . '`user` = ?, `name` = ?, `type` = ?, `enabled` = ? WHERE `id` = ?';
        Dba::write($sql, array($start, $end, $level, $user, $name, $type, $enabled, $this->id));

        return true;
    }

    /**
     * create
     *
     * This takes a keyed array of data and trys to insert it as a
     * new ACL entry
     * @param array $data
     * @return boolean
     */
    public static function create(array $data)
    {
        $start   = @inet_pton($data['start']);
        $end     = @inet_pton($data['end']);
        $name    = $data['name'];
        $user    = $data['user'] ?: '-1';
        $level   = (int)$data['level'];
        $type    = self::validate_type($data['type']);
        $enabled = make_bool($data['enabled']) ? 1 : 0;

        $sql = 'INSERT INTO `access_list` (`name`, `level`, `start`, `end`, ' . '`user`, `type`, `enabled`) VALUES (?, ?, ?, ?, ?, ?, ?)';
        Dba::write($sql, array($name, $level, $start, $end, $user, $type, $enabled));

        return true;
    }

    /**
     * check_function
     *
     * This checks if specific functionality is enabled.
     * @param string $type
     * @return boolean
     *
     * @deprecated See FunctionChecker::check
     */
    public static function check_function($type)
    {
        global $dic;

        return $dic->get(FunctionCheckerInterface::class)->check(
            (string) $type
        );
    }

    /**
     * check
     *
     * This is the global 'has_access' function. it can check for any 'type'
     * of object.
     *
     * Everything uses the global 0,5,25,50,75,100 stuff. GLOBALS['user'] is
     * always used.
     * @param string $type
     * @param integer $level
     * @param integer|null $user_id
     * @return boolean
     *
     * @deprecated See PrivilegeChecker::check
     */
    public static function check($type, $level, $user_id = null)
    {
        global $dic;

        return $dic->get(PrivilegeCheckerInterface::class)->check(
            (string) $type,
            (int) $level,
            $user_id
        );
    }

    /**
     * validate_type
     *
     * This validates the specified type; it will always return a valid type,
     * even if you pass in an invalid one.
     * @param string $type
     * @return string
     */
    private static function validate_type($type)
    {
        switch ($type) {
            case 'rpc':
            case 'interface':
            case 'network':
                return $type;
            default:
                return 'stream';
        }
    }
}
