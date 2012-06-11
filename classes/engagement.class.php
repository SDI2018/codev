<?php
/*
    This file is part of CoDev-Timetracking.

    CoDev-Timetracking is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    CoDev-Timetracking is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with CoDev-Timetracking.  If not, see <http://www.gnu.org/licenses/>.
*/

require_once('Logger.php');
if (NULL == Logger::getConfigurationFile()) {
      Logger::configure(dirname(__FILE__).'/../log4php.xml');
      $logger = Logger::getLogger("default");
      $logger->info("LOG activated !");
   }


include_once "issue_selection.class.php";
include_once "team.class.php";
include_once "service.class.php";
include_once "engagement_cache.class.php";



/**
 * Un engagement (fiche de presta) est un ensemble de taches que l'on veut
 * piloter a l'aide d'indicateurs (cout, delai, qualite, avancement)
 *
 * un engagement peut contenir des taches précises (mantis)
 * mais également définir des objectifs d'ordre global ou non
 * liés au dev.
 *
 * un engagement est provisionné d'un certain budjet, négocié avec le client.
 * le cout de l'ensemble des taches devrait etre a l'equilibre avec ce budjet.
 */
class Engagement {

  const state_toBeSent      = 1;
  const state_sent          = 2;
  const state_toBeValidated = 3;
  const state_validated     = 4;
  const state_toBeClosed    = 5;
  const state_Closed        = 6;
  const state_toBeBilled    = 7;
  const state_billed        = 8;
  const state_payed         = 9;

  // TODO i18n for constants
  public static $stateNames = array(Engagement::state_toBeSent      => "A émettre",
                                    Engagement::state_sent          => "Emis",
                                    Engagement::state_toBeValidated => "A valider",
                                    Engagement::state_validated     => "Validé",
                                    Engagement::state_toBeClosed    => "A clôturer",
                                    Engagement::state_Closed        => "Clôturé",
                                    Engagement::state_toBeBilled    => "A facturer",
                                    Engagement::state_billed        => "Facturé",
                                    Engagement::state_payed         => "Payé");


   private $logger;

   // codev_engagement_table
   private $id;
   private $name;
   private $description;
   private $startDate;
   private $deadline;
   private $teamid;
   private $serviceid;
   private $state;
   private $budjetDev;
   private $budjetMngt;
   private $budjetGarantie;
   private $averageDailyRate;

   // codev_engagement_bug_table
   private $issueSelection;


   function __construct($id) {

   	$this->logger = Logger::getLogger(__CLASS__);

      if (0 == $id) {
         echo "<span style='color:red'>ERROR: Please contact your CodevTT administrator</span>";
         $e = new Exception("Creating an Engagement with id=0 is not allowed.");
         $this->logger->error("EXCEPTION Engagement constructor: ".$e->getMessage());
         $this->logger->error("EXCEPTION stack-trace:\n".$e->getTraceAsString());
         throw $e;
      }

   	$this->id = $id;
   	$this->initialize();
   }

   private function initialize() {
   	// ---
   	$query  = "SELECT * FROM `codev_engagement_table` WHERE id=$this->id ";
   	$result = mysql_query($query);
   	if (!$result) {
	   	$this->logger->error("Query FAILED: $query");
	   	$this->logger->error(mysql_error());
	   	echo "<span style='color:red'>ERROR: Query FAILED</span>";
	   	exit;
   	}
   	$row = mysql_fetch_object($result);
      $this->name       = $row->name;
      $this->description = $row->description;
   	$this->startDate  = $row->start_date;
   	$this->deadline   = $row->deadline;
   	$this->teamid     = $row->team_id;
   	$this->state      = $row->state;
   	$this->budjetDev  = $row->budjet_dev;
   	$this->budjetMngt = $row->budjet_mngt;
   	$this->budjetGarantie = $row->budjet_garantie;
   	$this->averageDailyRate = $row->average_daily_rate;

   	// ---
   	$this->issueSelection = new IssueSelection($this->name);
   	$query  = "SELECT * FROM `codev_engagement_bug_table` ".
                "WHERE engagement_id=$this->id ";
                #", `mantis_bug_table`".
      	       #"WHERE codev_engagement_bug_table.engagement_id=$this->id ".
                #"AND codev_engagement_bug_table.bug_id = mantis_bug_table.id ".
                #"ORDER BY mantis_bug_table.project_id ASC, mantis_bug_table.target_version DESC, mantis_bug_table.status ASC";

   	$result = mysql_query($query);
   	if (!$result) {
	   	$this->logger->error("Query FAILED: $query");
	   	$this->logger->error(mysql_error());
	   	echo "<span style='color:red'>ERROR: Query FAILED</span>";
	   	exit;
   	}
   	while($row = mysql_fetch_object($result))
   	{
   		$this->issueSelection->addIssue($row->bug_id);
   	}
   }

   public function getId() {
      return $this->id;
   }

   public function getTeamid() {
      return $this->teamid;
   }
   public function setTeamid($value) {

      $this->teamid = $value;
      $query = "UPDATE `codev_engagement_table` SET team_id = '$value' WHERE id='$this->id' ";
      $result = mysql_query($query);
	   if (!$result) {
    	      $this->logger->error("Query FAILED: $query");
    	      $this->logger->error(mysql_error());
    	      echo "<span style='color:red'>ERROR: Query FAILED</span>";
    	      exit;
      }
   }



   public function getName() {
      return $this->name;
   }
   public function setName($name) {

      $this->name = $name;
      $query = "UPDATE `codev_engagement_table` SET name = '$name' WHERE id='$this->id' ";
      $result = mysql_query($query);
	   if (!$result) {
    	      $this->logger->error("Query FAILED: $query");
    	      $this->logger->error(mysql_error());
    	      echo "<span style='color:red'>ERROR: Query FAILED</span>";
    	      exit;
      }
   }

   public function getDesc() {
      return $this->description;
   }
   public function setDesc($description) {

      $this->description = $description;
      $query = "UPDATE `codev_engagement_table` SET description = '$description' WHERE id='$this->id' ";
      $result = mysql_query($query);
	   if (!$result) {
    	      $this->logger->error("Query FAILED: $query");
    	      $this->logger->error(mysql_error());
    	      echo "<span style='color:red'>ERROR: Query FAILED</span>";
    	      exit;
      }
   }

   public function getState() {
      return $this->state;
   }
   
   public function setState($value) {

      $this->state = $value;
      $query = "UPDATE `codev_engagement_table` SET state='$value' WHERE id='$this->id' ";
      $result = mysql_query($query);
	   if (!$result) {
    	      $this->logger->error("Query FAILED: $query");
    	      $this->logger->error(mysql_error());
    	      echo "<span style='color:red'>ERROR: Query FAILED</span>";
    	      exit;
      }
   }



   public function getAverageDailyRate() {
      return $this->averageDailyRate;
   }
   public function setAverageDailyRate($value) {


      $this->averageDailyRate = $value;
      $query = "UPDATE `codev_engagement_table` SET average_daily_rate = '$value' WHERE id='$this->id' ";
      $result = mysql_query($query);
	   if (!$result) {
    	      $this->logger->error("Query FAILED: $query");
    	      $this->logger->error(mysql_error());
    	      echo "<span style='color:red'>ERROR: Query FAILED</span>";
    	      exit;
      }
   }

   public function getStartDate() {
      return $this->startDate;
   }

   public function setStartDate($timestamp) {

      $this->startDate = $timestamp;
      $query = "UPDATE `codev_engagement_table` SET start_date = '$this->startDate' WHERE id='$this->id' ";
      $result = mysql_query($query);
	   if (!$result) {
    	      $this->logger->error("Query FAILED: $query");
    	      $this->logger->error(mysql_error());
    	      echo "<span style='color:red'>ERROR: Query FAILED</span>";
    	      exit;
      }
   }

   public function getDeadline() {
      return $this->deadline;
   }
   public function setDeadline($timestamp) {
      
      $this->deadline = $timestamp;
      $query = "UPDATE `codev_engagement_table` SET deadline = '$this->deadline' WHERE id='$this->id' ";
      $result = mysql_query($query);
	   if (!$result) {
    	      $this->logger->error("Query FAILED: $query");
    	      $this->logger->error(mysql_error());
    	      echo "<span style='color:red'>ERROR: Query FAILED</span>";
    	      exit;
      }
   }

   public function getBudjetDev() {
      return $this->budjetDev;
   }
   public function setBudjetDev($value) {

      $this->budjetDev = $value;
      $query = "UPDATE `codev_engagement_table` SET budjet_dev = '$value' WHERE id='$this->id' ";
      $result = mysql_query($query);
	   if (!$result) {
    	      $this->logger->error("Query FAILED: $query");
    	      $this->logger->error(mysql_error());
    	      echo "<span style='color:red'>ERROR: Query FAILED</span>";
    	      exit;
      }
   }

   public function getBudjetMngt() {
      return $this->budjetMngt;
   }
   public function setBudjetMngt($value) {

      $this->budjetMngt = $value;
      $query = "UPDATE `codev_engagement_table` SET budjet_mngt = '$value' WHERE id='$this->id' ";
      $result = mysql_query($query);
	   if (!$result) {
    	      $this->logger->error("Query FAILED: $query");
    	      $this->logger->error(mysql_error());
    	      echo "<span style='color:red'>ERROR: Query FAILED</span>";
    	      exit;
      }
   }

   public function getBudjetGarantie() {
      return $this->budjetGarantie;
   }
   public function setBudjetGarantie($value) {

      $this->budjetGarantie = $value;
      $query = "UPDATE `codev_engagement_table` SET budjet_garantie = '$value' WHERE id='$this->id' ";
      $result = mysql_query($query);
	   if (!$result) {
    	      $this->logger->error("Query FAILED: $query");
    	      $this->logger->error(mysql_error());
    	      echo "<span style='color:red'>ERROR: Query FAILED</span>";
    	      exit;
      }
   }

   public function getIssueSelection() {
      return $this->issueSelection;
   }


   /**
    * create a new engagement in the DB
    *
    * @return int $id
    */
   public static function create($name, $teamid) {
    $query = "INSERT INTO `codev_engagement_table`  (`name`, `team_id`) ".
             "VALUES ('$name', '$teamid');";
    $result = mysql_query($query);
    if (!$result) {
    	$this->logger->error("Query FAILED: $query");
    	$this->logger->error(mysql_error());
    	echo "<span style='color:red'>ERROR: Query FAILED</span>";
    	exit;
    }
    $id = mysql_insert_id();
    return $id;
   }

   /**
    * add Issue to engagement (in DB & current instance)
    *
    * @param int $bugid
    */
   public function addIssue($bugid) {

      // security check
      if (!is_numeric($bugid)) {
         echo "<span style='color:red'>ERROR: Please contact your CodevTT administrator</span>";
         $e = new Exception("SECURITY ALERT: Attempt to set non_numeric value ($bugid)");
         $this->logger->fatal("EXCEPTION addIssue: ".$e->getMessage());
         $this->logger->fatal("EXCEPTION stack-trace:\n".$e->getTraceAsString());
         throw $e;
      }

      try {
         IssueCache::getInstance()->getIssue($bugid);
      } catch (Exception $e) {
         $this->logger->error("addIssue($bugid): issue $bugid does not exist !");
         echo "<span style='color:red'>ERROR: issue  '$bugid' does not exist !</span>";
         return NULL;
      }

      $this->logger->debug("Add issue $bugid to engagement $this->id");
      $this->issueSelection->addIssue($bugid);

      $query = "INSERT INTO `codev_engagement_bug_table` (`engagement_id`, `bug_id`) VALUES ('$this->id', '$bugid');";
      $result = mysql_query($query);
      if (!$result) {
	      $this->logger->error("Query FAILED: $query");
	      $this->logger->error(mysql_error());
	      echo "<span style='color:red'>ERROR: Query FAILED</span>";
	      exit;
      }
      $id = mysql_insert_id();
      return $id;

   }

   /**
    * remove issue from engagement issueList.
    * the issue itself is not deleted.
    *
    * @param int $bugid
    */
   public function removeIssue($bugid) {

      // security check
      if (!is_numeric($bugid)) {
         echo "<span style='color:red'>ERROR: Please contact your CodevTT administrator</span>";
         $e = new Exception("SECURITY ALERT: Attempt to set non_numeric value ($bugid)");
         $this->logger->fatal("EXCEPTION removeIssue: ".$e->getMessage());
         $this->logger->fatal("EXCEPTION stack-trace:\n".$e->getTraceAsString());
         throw $e;
      }


      $this->issueSelection->removeIssue($bugid);

      $query = "DELETE FROM `codev_engagement_bug_table` WHERE engagement_id='$this->id' AND bug_id='$bugid';";
      $result = mysql_query($query);
      if (!$result) {
	      $this->logger->error("Query FAILED: $query");
	      $this->logger->error(mysql_error());
	      echo "<span style='color:red'>ERROR: Query FAILED</span>";
	      exit;
      }
   }

   /**
    * 
    */
   public function getService() {

      if (NULL == $this->serviceid) {

         $query  = "SELECT * FROM `codev_service_eng_table` WHERE engagement_id=$this->id ";
         $result = mysql_query($query);
         if (!$result) {
            $this->logger->error("Query FAILED: $query");
            $this->logger->error(mysql_error());
            echo "<span style='color:red'>ERROR: Query FAILED</span>";
            exit;
         }

         // can an Engagement belong to more than one service ?
         while($row = mysql_fetch_object($result)) {

            $this->serviceid = $row->service_id;
            $this->logger->debug("Engagement $this->id is in service $this->serviceid");
         }
      }
      return $this->serviceid;
   }


   /**
    *
    * @param int $value serviceid. if NULL or '0' then remove association
    * @param int $type
    *
    */
   public function setService($value, $type = Service::engType_dev) {

      if ((NULL == $value) || (0 == $value)){

         if (NULL == $this->getService()) { return; }
         $query = "DELETE FROM `codev_service_eng_table` WHERE `engagement_id` = '$this->id' ";

      } else {
         if (NULL == $this->getService()) {
            $query = "INSERT INTO `codev_service_eng_table` (`service_id`, `engagement_id`, `type`) ".
                     "VALUES ('$value', '$this->id', '$type');";
         } else {
            $query = "UPDATE `codev_service_eng_table` SET service_id = '$value' WHERE engagement_id='$this->id' ";
         }
      }

      $result = mysql_query($query);
      if (!$result) {
            $this->logger->error("Query FAILED: $query");
            $this->logger->error(mysql_error());
            echo "<span style='color:red'>ERROR: Query FAILED</span>";
            exit;
      }
      $this->serviceid = $value;

   }

}
?>