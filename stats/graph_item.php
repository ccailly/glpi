<?php
/*
 ----------------------------------------------------------------------
 GLPI - Gestionnaire Libre de Parc Informatique
 Copyright (C) 2003-2005 by the INDEPNET Development Team.
 
 http://indepnet.net/   http://glpi.indepnet.org
 ----------------------------------------------------------------------

 LICENSE

	This file is part of GLPI.

    GLPI is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    GLPI is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with GLPI; if not, write to the Free Software
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 ------------------------------------------------------------------------
*/

// ----------------------------------------------------------------------
// Original Author of file: Julien Dombre
// Purpose of file:
// ----------------------------------------------------------------------

include ("_relpos.php");
include ($phproot . "/glpi/includes.php");
include ($phproot . "/glpi/includes_tracking.php");
include ($phproot . "/glpi/includes_enterprises.php");
include ($phproot . "/glpi/includes_setup.php");
include ($phproot . "/glpi/includes_devices.php");
require ("functions.php");


checkAuthentication("normal");
commonHeader($lang["title"][11],$_SERVER["PHP_SELF"]);

if(empty($_POST["date1"])&&empty($_POST["date2"])) {
$year=date("Y")-2;
$_POST["date1"]=date("Y-m-d",mktime(1,0,0,date("m"),date("d"),$year));

$_POST["date2"]=date("Y-m-d");
}

if(empty($_POST["date1"])) $_POST["date1"] = "";
if(empty($_POST["date2"])) $_POST["date2"] = "";
if ($_POST["date1"]!=""&&$_POST["date2"]!=""&&strcmp($_POST["date2"],$_POST["date1"])<0){
$tmp=$_POST["date1"];
$_POST["date1"]=$_POST["date2"];
$_POST["date2"]=$tmp;
}

$job=new Job();
switch($_GET["type"]){
case "technicien":
	$val1=$_GET["ID"];
	$val2=$_GET["assign_type"];
	$job->assign=$_GET["ID"];
	$job->assign_type=$_GET["assign_type"];

	if ($_GET["assign_type"]==USER_TYPE){
	$next=getNextItem("glpi_users",$_GET["ID"]);
	$prev=getPreviousItem("glpi_users",$_GET["ID"]);
	$cleantarget=preg_replace("/ID=([0-9]+[&]{0,1})/","",$_SERVER['QUERY_STRING']);
	} else $next=$prev=-1;

	echo "<div align='center'>";
	echo "<table class='icon_consol'>";
	echo "<tr>";
	echo "<td>";
	if ($prev>0) echo "<a href='".$_SERVER['PHP_SELF']."?$cleantarget&ID=$prev'><</a>";
	echo "</td>";
	echo "<td width='400' align='center'><b>".$lang["stats"][16].": ".$job->getAssignName(1)."</b></td>";
	echo "<td>";
	if ($next>0) echo "<a href='".$_SERVER['PHP_SELF']."?$cleantarget&ID=$next'>></a>";
	echo "</td>";
	echo "</tr>";
	echo "</table></div><br>";
	break;
case "user":
	$val1=$_GET["ID"];
	$val2="";
	$job->author=$_GET["ID"];

	$next=getNextItem("glpi_users",$_GET["ID"]);
	$prev=getPreviousItem("glpi_users",$_GET["ID"]);
	$cleantarget=preg_replace("/ID=([0-9]+[&]{0,1})/","",$_SERVER['QUERY_STRING']);

	echo "<div align='center'>";
	echo "<table class='icon_consol'>";
	echo "<tr>";
	echo "<td>";
	if ($prev>0) echo "<a href='".$_SERVER['PHP_SELF']."?$cleantarget&ID=$prev'><</a>";
	echo "</td>";
	echo "<td width='400' align='center'><b>".$lang["stats"][16].": ".$job->getAuthorName(1)."</b></td>";
	echo "<td>";
	if ($next>0) echo "<a href='".$_SERVER['PHP_SELF']."?$cleantarget&ID=$next'>></a>";
	echo "</td>";
	echo "</tr>";
	echo "</table></div><br>";

	break;	
case "category":
	$val1=$_GET["ID"];
	$val2="";

	$next=getNextItem("glpi_dropdown_tracking_category",$_GET["ID"]);
	$prev=getPreviousItem("glpi_dropdown_tracking_category",$_GET["ID"]);
	$cleantarget=preg_replace("/ID=([0-9]+[&]{0,1})/","",$_SERVER['QUERY_STRING']);
	
	echo "<div align='center'>";
	echo "<table class='icon_consol'>";
	echo "<tr>";
	echo "<td>";
	if ($prev>0) echo "<a href='".$_SERVER['PHP_SELF']."?$cleantarget&ID=$prev'><</a>";
	echo "</td>";
	echo "<td width='400' align='center'><b>".$lang["stats"][38].": ".getDropdownName("glpi_dropdown_tracking_category",$_GET["ID"])."</b></td>";
	echo "<td>";
	if ($next>0) echo "<a href='".$_SERVER['PHP_SELF']."?$cleantarget&ID=$next'>></a>";
	echo "</td>";
	echo "</tr>";
	echo "</table></div><br>";

	break;	
case "device":
	$val1=$_GET["ID"];
	$val2=$_GET["device"];

	$device_table = getDeviceTable($_GET["device"]);

	$next=getNextItem($device_table,$_GET["ID"]);
	$prev=getPreviousItem($device_table,$_GET["ID"]);
	$cleantarget=preg_replace("/ID=([0-9]+[&]{0,1})/","",$_SERVER['QUERY_STRING']);
	$db=new DB();
	$query = "select  designation from ".$device_table." WHERE ID='".$_GET['ID']."'";
	$result=$db->query($query);
	
	echo "<div align='center'>";
	echo "<table class='icon_consol'>";
	echo "<tr>";
	echo "<td>";
	if ($prev>0) echo "<a href='".$_SERVER['PHP_SELF']."?$cleantarget&ID=$prev'><</a>";
	echo "</td>";
	echo "<td width='400' align='center'><b>".$lang["stats"][19].": ".$db->result($result,0,"designation")."</b></td>";
	echo "<td>";
	if ($next>0) echo "<a href='".$_SERVER['PHP_SELF']."?$cleantarget&ID=$next'>></a>";
	echo "</td>";
	echo "</tr>";
	echo "</table></div><br>";

	break;
case "comp_champ":
	$val1=$_GET["ID"];
	$val2=$_GET["champ"];

	$table=str_replace("dropdown_type","type_computers",str_replace("location","locations","glpi_dropdown_".$_GET["champ"]));

	$next=getNextItem($table,$_GET["ID"]);
	$prev=getPreviousItem($table,$_GET["ID"]);
	$cleantarget=preg_replace("/ID=([0-9]+[&]{0,1})/","",$_SERVER['QUERY_STRING']);
	
	echo "<div align='center'>";
	echo "<table class='icon_consol'>";
	echo "<tr>";
	echo "<td>";
	if ($prev>0) echo "<a href='".$_SERVER['PHP_SELF']."?$cleantarget&ID=$prev'><</a>";
	echo "</td>";
	echo "<td width='400' align='center'><b>".$lang["stats"][26].": ".getDropdownName($table,$_GET["ID"])."</b></td>";
	echo "<td>";
	if ($next>0) echo "<a href='".$_SERVER['PHP_SELF']."?$cleantarget&ID=$next'>></a>";
	echo "</td>";
	echo "</tr>";
	echo "</table></div><br>";

	break;

}


echo "<div align='center'><form method=\"post\" name=\"form\" action=\"".$_SERVER["REQUEST_URI"]."\">";
echo "<table class='tab_cadre'><tr class='tab_bg_2'><td align='right'>";
echo $lang["search"][8]." :</td><td>";
showCalendarForm("form","date1",$_POST["date1"]);
echo "</td><td rowspan='2' align='center'><input type=\"submit\" class='button' name\"submit\" Value=\"". $lang["buttons"][7] ."\" /></td></tr>";
echo "<tr class='tab_bg_2'><td align='right'>".$lang["search"][9]." :</td><td>";
showCalendarForm("form","date2",$_POST["date2"]);
echo "</td></tr>";
echo "</table></form></div>";



///////// Stats nombre intervention
// Total des interventions
$entrees_total=constructEntryValues("inter_total",$_POST["date1"],$_POST["date2"],$_GET["type"],$val1,$val2);
	if (count($entrees_total)>0)
graphBy($entrees_total,$lang["stats"][5],$lang["stats"][35],1,"month");

// Total des interventions r�solues
$entrees_solved=constructEntryValues("inter_solved",$_POST["date1"],$_POST["date2"],$_GET["type"],$val1,$val2);
	if (count($entrees_solved)>0)
graphBy($entrees_solved,$lang["stats"][11],$lang["stats"][35],1,"month");

//Temps moyen de resolution d'intervention
$entrees_avgtime=constructEntryValues("inter_avgsolvedtime",$_POST["date1"],$_POST["date2"],$_GET["type"],$val1,$val2);
	if (count($entrees_avgtime)>0)
graphBy($entrees_avgtime,$lang["stats"][6],$lang["stats"][32],0,"month");

//Temps moyen d'intervention r�el
$entrees_avgtime=constructEntryValues("inter_avgrealtime",$_POST["date1"],$_POST["date2"],$_GET["type"],$val1,$val2);
	if (count($entrees_avgtime)>0)
graphBy($entrees_avgtime,$lang["stats"][25],$lang["stats"][33],0,"month");

//Temps moyen de prise en compte de l'intervention
$entrees_avgtime=constructEntryValues("inter_avgtakeaccount",$_POST["date1"],$_POST["date2"],$_GET["type"],$val1,$val2);
	if (count($entrees_avgtime)>0)
graphBy($entrees_avgtime,$lang["stats"][30],$lang["stats"][32],0,"month");

commonFooter();

?>