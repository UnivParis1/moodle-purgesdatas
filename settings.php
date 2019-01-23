<?php

/**
 * Settings and links
 *
 * @package    local
 * @subpackage purges_datas
 * @author 		El-Miqui CHEMLALI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

$ADMIN->add('server',
        new admin_externalpage('local_purgesdatas',
                 get_string('pluginname', 'local_purgesdatas'),
                "$CFG->wwwroot/local/purgesdatas/index.php")
        );
// no report settings
$settings = null;
