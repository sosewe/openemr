<?php
/**
 * PatientMenuRole class.
 *
 * @package   OpenEMR
 * @link      http://www.open-emr.org
 * @author    Brady Miller <brady.g.miller@gmail.com>
 * @author    Eyal Wolanowski <eyal.wolanowski@gmail.com>
 * @copyright Copyright (c) 2018 Brady Miller <brady.g.miller@gmail.com>
 * @copyright Copyright (c) 2018 Eyal Wolanowski <eyal.wolanowski@gmail.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

namespace OpenEMR\Menu;

use OpenEMR\Services\UserService;

class PatientMenuRole extends MenuRole
{

    /**
     * Constructor
     */
    public function __construct()
    {
        // This is where the magic happens to support special menu items.
        //   An empty menu_update_map array is created in MenuRole class
        //   constructor. Adding to this array will link special menu items
        //   to functions in this class.
        parent::__construct();
        $this->menu_update_map["Modules"]="updateModulesDemographicsMenu";
    }

    /**
     * Collect the Menu for logged in user.
     *
     * @return array representation of the Menu
     */
    public function getMenu()
    {
        // Collect the selected menu of user
        $patientMenuRole = $this->getMenuRole();

        // Load the selected menu
        if (preg_match("/.json$/", $patientMenuRole)) {
            // load custom menu (includes .json in id)
            $menu_parsed = json_decode(file_get_contents($GLOBALS['OE_SITE_DIR'] . "/documents/custom_menus/patient_menus/" . $patientMenuRole));
        } else {
            // load a standardized menu (does not include .json in id)
            $menu_parsed = json_decode(file_get_contents($GLOBALS['fileroot'] . "/interface/main/tabs/menu/menus/patient_menus/" . $patientMenuRole . ".json"));
        }
        // if error, then die and report error
        if (!$menu_parsed) {
            die("\nJSON ERROR: " . json_last_error());
        }
        //to make the url absolute to web root and to account for external urls i.e. those beginning with http or https
        foreach ($menu_parsed as $menu_obj) {
            $menu_obj -> url = $this->getAbsoluteWebRoot($menu_obj -> url);
            if (!empty($menu_obj->children)) {
                foreach ($menu_obj->children as $menu_obj) {
                    $menu_obj -> url = $this->getAbsoluteWebRoot($menu_obj -> url);
                }
            }
        }
        $menu_parsed = json_decode(json_encode($menu_parsed));
        $this->menuUpdateEntries($menu_parsed);
        $menu_restrictions=array();
        $this->menuApplyRestrictions($menu_parsed, $menu_restrictions);
        return $menu_restrictions;
    }

    /**
     * Build the html select element to list the PatientMenuRole options.
     *
     * @var string $selected Current PatientMenuRole for current users.
     * @return string Html select element to list the PatientMenuRole options.
     */
    public function displayMenuRoleSelector($selected = "")
    {
        $output = "<select name='patient_menu_role' id='patient_menu_role' class='form-control'>";
        $output .= "<option value='standard' " . (($selected == "standard") ? "selected" : "") . ">" . xlt("Standard") . "</option>";

        $customMenuDir = $GLOBALS['OE_SITE_DIR'] . "/documents/custom_menus/patient_menus/";
        if (file_exists($customMenuDir)) {
            $dHandle = opendir($customMenuDir);
            while (false !== ($menuCustom = readdir($dHandle))) {
                // Only process files that contain *.json
                if (preg_match("/.json$/", $menuCustom)) {
                    $selectedTag = ($selected == $menuCustom) ? "selected" : "";
                    $output .= "<option value='" . attr($menuCustom) . "' " . $selectedTag . ">";
                    // Drop the .json and translate the name
                    $output .= xlt(substr($menuCustom, 0, -5));
                    $output .= "</option>";
                }
            }

            closedir($dHandle);
        }

        $output .= "</select>";
        return $output;
    }

    /**
     * Collect the patientMenuRole for logged in user.
     *
     * @return string Identifier for the patientMenuRole
     */
    private function getMenuRole()
    {
        $userService = new UserService();
        $user = $userService->getCurrentlyLoggedInUser();
        $patientMenuRole = $user->getPatientMenuRole();
        if (empty($patientMenuRole)) {
            $patientMenuRole = "standard";
        }

        return $patientMenuRole;
    }

    /**
     * load demographics created by modules system
     * @param $menu_list
     */
    protected function updateModulesDemographicsMenu(&$menu_list)
    {
        $module_query = sqlStatement("SELECT msh.*,ms.obj_name,ms.menu_name,ms.path,m.mod_ui_name,m.type FROM modules_hooks_settings AS msh
					                LEFT OUTER JOIN modules_settings AS ms ON obj_name=enabled_hooks AND ms.mod_id=msh.mod_id
					                LEFT OUTER JOIN modules AS m ON m.mod_id=ms.mod_id
					                WHERE fld_type=3 AND mod_active=1 AND sql_run=1 AND attached_to='demographics' ORDER BY mod_id");

        if (sqlNumRows($module_query)) {
            while ($hookrow = sqlFetchArray($module_query)) {
                if ($hookrow['type'] == 0) {
                    $modulePath = $GLOBALS['customModDir'];
                    $added = "";
                } else {
                    $added = "index";
                    $modulePath = $GLOBALS['zendModDir'];
                }

                if (zh_acl_check($_SESSION['authUserID'], $hookrow['obj_name']) ?  "" : "1") {
                    continue;
                }

                $relative_link = "../../modules/" . $modulePath . "/" . $hookrow['path'];
                $mod_nick_name = $hookrow['menu_name'] ? $hookrow['menu_name'] : 'NoName';

                $subEntry = new \stdClass();
                $subEntry->requirement = 0;
                $subEntry->target = 'main';
                $subEntry->menu_id = $hookrow['mod_id'];
                $subEntry->label = xlt($mod_nick_name);
                $subEntry->url = $relative_link;
                $subEntry->on_click = 'top.restoreSession()';
                $subEntry->pid = 'false';

                array_push($menu_list->children, $subEntry);
            }
        }
    }
    /**
     * displays a bootstrap3 horizontal nav bar
     */
    
    public function displayHorizNavBarMenu()
    {
        $pid = $_SESSION['pid'];
        $menu_restrictions = $this->getMenu();
        $li_id = 1;
        $str_top = <<<EOT
        <nav class="navbar navbar-default navbar-color navbar-static-top patient-menu">
            <div class="container-fluid">
                <div class="navbar-header">
                    <button class="navbar-toggle" data-target="#myNavbar" data-toggle="collapse" type="button"><span class="icon-bar"></span> <span class="icon-bar"></span> <span class="icon-bar"></span></button>
                </div>
                <div class="collapse navbar-collapse" id="myNavbar" >
                    <ul class="nav navbar-nav">
EOT;
        echo $str_top. "\r\n";
        foreach ($menu_restrictions as $key => $value) {
            if (!empty($value->children)) {
                // create dropdown if there are children (bootstrap3 horizontal nav bar with dropdown)
                $class = isset($value->class) ? $value->class : '';
                $list = '<li class="dropdown"><a href="#"  id="' . attr($value->menu_id) . '" class="dropdown-toggle oe-bold-black ' . attr($class) . '" data-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false">' . text($value->label) . ' <span class="caret"></span></a>';
                $list .='<ul class="dropdown-menu">';
                foreach ($value->children as $children_key => $children_value) {
                    $link = ($children_value->pid != "true") ? $children_value->url : $children_value->url . attr($pid);
                    $class = isset($children_value->class) ? $children_value->class : '';
                    $list .= '<li class="oe-bold-black ' . attr($class) . '" id="' . attr($children_value->menu_id) . '">';
                    $list .= '<a class="oe-bold-black"  href="' . attr($link) . '" onclick="' . $children_value->on_click .'"> ' . text($children_value->label) . ' </a>';
                    $list .= '</li>';
                }
                $list .= '</ul>';
            } else {
                $link = ($value->pid != "true") ? $value->url : $value->url . attr($pid);
                $class = isset($value->class) ? $value->class : '';
                $list = '<li class="oe-bold-black ' . attr($class) . '" id="' . attr($value->menu_id) . '">';
                $list .= '<a href="' . attr($link) . '" onclick="' . $value->on_click .'"> ' . text($value->label) . ' </a>';
                $list .= '</li>';
            }
            echo $list. "\r\n";
            $li_id++;
        }
        $str_bot = <<<EOB
                    </ul>
                </div>
            </div>
        </nav>
EOB;
        echo $str_bot. "\r\n";
        return;
    }

    /**
     * make the url absolute to web root
     * @param $rel_url
     *
     * @return string
     */
    private function getAbsoluteWebRoot($rel_url)
    {
        if ($rel_url && !strpos($rel_url, "://")) {
            return $GLOBALS['webroot'] . "/" . $rel_url;
        }
        return $rel_url;
    }
}
