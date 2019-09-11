<?php
require_once("$_SERVER[DOCUMENT_ROOT]/plugin_dependencies/iPlugin.php");
require("$_SERVER[DOCUMENT_ROOT]/config/dbconfig.php");
class twenty5LivePlugin implements iPlugin {
    public function getIndex() {
        return 25;
    }
    public function getName() {
        return "25Live";
    }
    public function isActive($config) {
        return $config->twenty5LiveIntegrationActive;
    }
    public function getResources($config) {
        /*
         * Currently, method is to build array of schedulable resources 
         * from CONFIG "twenty5LiveResources" string.
         * 
         * IMPORTANT: 
         *      This depends on array default automatic assignment (starting 
         *      with zero) of $resourceId to rooms, so the order is crucial.
         */
        $rooms = explode(",", $config->twenty5LiveResources);
        return $rooms;
    }
    private function getResourceName($config, $resourceId) {
        $resources = $this->getResources($config);
        return $resources[$resourceId];
    }
    private function getSchedule($config, $resourceId) {
        /* 
         * https://github.com/caedm/wall-ink-server/wiki/Coding-a-new-plugin#getscheduleconfig-resourceid
         * 
         * According to above documentation, this function must return a string formatted like:
         * 
         *  EB Team Room #224       (Room Name)
         *  Team 23 Meeting         (Meeting Title)
         *  2018-10-23 08:00:00     (Start)
         *  2018-10-23 10:00:00     (End)
         *  Math Study Group
         *  2018-10-23 11:00:00
         *  2018-10-23 12:00:00
         *  ...
         */

        // Step 1 : Add Resource name to Schedule string
        $resourceName = $this->getResourceName($config, $resourceId);
        $schedule = $resourceName."\n";

        // Step 2 : Hit 25Live API for JSON with specific room
        $jsonSource = "http://".$config->twenty5LiveApiBaseUrlBeginning.$config->twenty5LiveWebName.$config->twenty5LiveApiBaseUrlEnd."&search=".$resourceName;
        $jsonFileContents = file_get_contents($jsonSource);
        $reservations = json_decode($jsonFileContents, true);

        // Step 3 : Parse JSON/Array into expected formatted lines
        if (!empty($reservations)) {
            for ($i = 0; $i < count($reservations); $i++)  {
                // Remove 'T' that comes in DateTime format: 2019-09-11T13:30:00
                $schedule .= $reservations[$i]['title'] . "\n"
                    . preg_replace('/T/', ' ', $reservations[$i]['startDateTime']) . "\n"
                    . preg_replace('/T/', ' ', $reservations[$i]['endDateTime']) . "\n";
            }
        }
        return $schedule;
    }
    public function getImage($config, $device) {
        require_once("$_SERVER[DOCUMENT_ROOT]/plugin_dependencies/general_scheduling/schedulingGetImage.php");
        $resourceName = $this->getResourceName($config, $device["resource_id"]);
        return schedulingGetImage(
            $config,
            $device,
            $this->getSchedule($config, $device["resource_id"]),
            $config->twenty5LiveDisplayUrl,
            $config->twenty5LiveQrCodeBaseUrlBeginning . $resourceName . $config->twenty5LiveQrCodeBaseUrlEnd);
    }
    public function getDeviceType($device) {
        require_once("$_SERVER[DOCUMENT_ROOT]/plugin_dependencies/general_scheduling/schedulingGetDeviceType.php");
        return schedulingGetDeviceType($device, $this->getIndex());
    }
} // End class

if ($config->twenty5LiveIntegrationActive == "true") {
    $twenty5Live = new twenty5LivePlugin;
    $plugins[ $twenty5Live->getIndex() ] = $twenty5Live;
}
?>
