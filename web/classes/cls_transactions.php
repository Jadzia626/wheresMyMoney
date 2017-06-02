<?php
   /**
    *  Where's My Money? – Transactions Class
    * ========================================
    *  Created 2017-06-01
    */

    class Transact
    {
        // Publics

        // Privates
        private $db;
        private $fundsID  = null;
        private $fromDate = null;
        private $toDate   = null;


        // Constructor
        function __construct($oDB) {
            $this->db = $oDB;
        }

        // Wrappers
        private function dbWrap($dbVar,$varType="text") {

            switch($varType) {
                case "text":
                    $dbVar = $dbVar === null ? "NULL" : "'".$this->db->real_escape_string($dbVar)."'";
                    break;
                case "int":
                    $dbVar = $dbVar === null ? "NULL" : intval($dbVar);
                    break;
                case "float":
                    $dbVar = $dbVar === null ? "NULL" : floatval($dbVar);
                    break;
                case "date":
                    $dbVar = $dbVar === null ? "NULL" : date("'Y-m-d'",$dbVar);
                    break;
                case "datetime":
                    $dbVar = $dbVar === null ? "NULL" : date("'Y-m-d H:i:s'",$dbVar);
                    break;
            }
            return $dbVar;
        }

        // Methods
        public function setFilter($filterType, $filterValue) {
            switch($filterType) {
                case "FundsID":
                    $this->fundsID  = $filterValue;
                    break;
                case "FromDate":
                    $this->fromDate = $filterValue;
                    break;
                case "ToDate":
                    $this->toDate   = $filterValue;
                    break;
                default:
                    echo "Unknown filter type ...<br />";
                    break;
            }
        }

        public function unsetFilter($filterType) {
            switch($filterType) {
                case "FundsID":
                    $this->fundsID  = null;
                    break;
                case "FromDate":
                    $this->fromDate = null;
                    break;
                case "ToDate":
                    $this->toDate   = null;
                    break;
            }
        }

        public function getData($ID=0) {

            $tic = microtime(true);

            $aReturn = array(
                "Meta" => array(
                    "Content" => "Transactions",
                    "Count"   => 0,
                ),
                "Data" => array(),
            );

            $SQL  = "SELECT ";
            $SQL .= "t.ID AS ID, ";
            $SQL .= "f.Name AS FundsName, ";
            $SQL .= "t.RecordDate AS RecordDate, ";
            $SQL .= "t.TransactionDate AS TransactionDate, ";
            $SQL .= "t.Details AS Details, ";
            $SQL .= "t.Original AS Original, ";
            $SQL .= "tc.ISO AS Currency, ";
            $SQL .= "tc.Factor AS CurrencyFac, ";
            $SQL .= "t.Amount AS Amount, ";
            $SQL .= "fc.Factor AS AmountFac, ";
            $SQL .= "t.Complete AS Complete ";
            $SQL .= "FROM transactions AS t ";
            $SQL .= "LEFT JOIN funds AS f ON f.ID = t.FundsID ";
            $SQL .= "LEFT JOIN currency AS tc ON tc.ID = t.CurrencyID ";
            $SQL .= "LEFT JOIN currency AS fc ON fc.ID = f.CurrencyID ";
            if($ID > 0) {
                $SQL .= "WHERE t.ID = '".$this->db->real_escape_string($ID)."' ";
            } else {
                $SQL .= "WHERE t.ID > 0 ";
            }
            if(!is_null($this->fundsID)) {
                $SQL .= "AND t.FundsID = '".$this->db->real_escape_string($this->fundsID)."' ";
            }
            if(!is_null($this->fromDate)) {
                $SQL .= "AND t.RecordDate >= '".date("Y-m-d",$this->fromDate)."' ";
            }
            if(!is_null($this->toDate)) {
                $SQL .= "AND t.RecordDate <= '".date("Y-m-d",$this->toDate)."' ";
            }
            $oData = $this->db->query($SQL);

            while($aRow = $oData->fetch_assoc()) {
                $aReturn["Data"][] = array(
                    "ID"              => $aRow["ID"],
                    "FundsName"       => $aRow["FundsName"],
                    "RecordDate"      => strtotime($aRow["RecordDate"]),
                    "TransactionDate" => $aRow["TransactionDate"] === null ? null : strtotime($aRow["TransactionDate"]),
                    "Details"         => $aRow["Details"],
                    "Original"        => $aRow["Original"],
                    "Currency"        => $aRow["Currency"],
                    "CurrencyFac"     => $aRow["CurrencyFac"],
                    "Amount"          => $aRow["Amount"],
                    "AmountFac"       => $aRow["AmountFac"],
                );
            }
            $aReturn["Meta"]["Count"] = count($aReturn["Data"]);

            $toc = microtime(true);
            $aReturn["Meta"]["Time"] = ($toc-$tic)*1000;

            return $aReturn;
        }

       /**
        *  Returns all entries in temporary table for a given funds ID
        */

        public function getTemp() {

            if(is_null($this->fundsID)) return false;

            $tic = microtime(true);

            $aReturn = array(
                "Meta" => array(
                    "Content" => "Transactions",
                    "Count"   => 0,
                ),
                "Data" => array(),
            );

            $SQL  = "SELECT * ";
            $SQL .= "FROM transactions_temp ";
            $SQL .= "WHERE FundsID = ".$this->dbWrap($this->fundsID,"int")." ";
            $SQL .= "ORDER BY RecordDate ASC, ID ASC ";
            $oData = $this->db->query($SQL);

            if(!$oData) {
                echo "MySQL Query Failed ...<br />";
                echo "Error: ".$this->db->error."<br />";
                echo "The Query was:<br />";
                echo str_replace("\n","<br />",$SQL);
            }

            while($aRow = $oData->fetch_assoc()) {
                $aReturn["Data"][] = $aRow;
            }
            $aReturn["Meta"]["Count"] = count($aReturn["Data"]);

            $toc = microtime(true);
            $aReturn["Meta"]["Time"] = ($toc-$tic)*1000;

            return $aReturn;
        }

       /**
        *  Saves imported transactions to permanent table.
        */

        public function saveData($aData) {

            if(is_null($this->fundsID)) return false;

            // Get currencies
            $oCurrencies = $this->db->query("SELECT ID, ISO FROM currency");
            $aCurrencyIDs = array();
            while($aCurrency = $oCurrencies->fetch_assoc()) {
                $aCurrencyIDs[$aCurrency["ISO"]] = $aCurrency["ID"];
            }

            $SQL = "";
            reset($aData);
            foreach($aData as $iKey=>$aRow) {

                if(array_key_exists($aRow["Currency"],$aCurrencyIDs)) {
                    $iCurrencyID = $aCurrencyIDs[$aRow["Currency"]];
                } else {
                    $iCurrencyID = null;
                }

                if(array_key_exists("ID",$aRow)) {
                    $SQL .= "UPDATE transactions SET ";
                    $SQL .= "FundsID = "        .$this->dbWrap($this->fundsID,"int").", ";
                    $SQL .= "RecordDate = "     .$this->dbWrap($aRow["RecordDate"],"date").", ";
                    $SQL .= "TransactionDate = ".$this->dbWrap($aRow["TransactionDate"],"date").", ";
                    $SQL .= "Details = "        .$this->dbWrap($aRow["Details"],"text").", ";
                    $SQL .= "Original = "       .$this->dbWrap($aRow["Original"],"int").", ";
                    $SQL .= "CurrencyID = "     .$this->dbWrap($iCurrencyID,"int").", ";
                    $SQL .= "Amount = "         .$this->dbWrap($aRow["Amount"],"int")." ";
                    $SQL .= "WHERE ID = "       .$this->dbWrap($aRow["ID"],"int")." ";
                    $SQL .= "AND FundsID = '"   .$this->dbWrap($this->fundsID,"int").";\n";
                } else {
                    $SQL .= "INSERT INTO transactions (";
                    $SQL .= "FundsID, ";
                    $SQL .= "RecordDate, ";
                    $SQL .= "TransactionDate, ";
                    $SQL .= "Details, ";
                    $SQL .= "Original, ";
                    $SQL .= "CurrencyID, ";
                    $SQL .= "Amount, ";
                    $SQL .= "Created ";
                    $SQL .= ") VALUES (";
                    $SQL .= $this->dbWrap($this->fundsID,"int").", ";
                    $SQL .= $this->dbWrap($aRow["RecordDate"],"date").", ";
                    $SQL .= $this->dbWrap($aRow["TransactionDate"],"date").", ";
                    $SQL .= $this->dbWrap($aRow["Details"],"text").", ";
                    $SQL .= $this->dbWrap($aRow["Original"],"int").", ";
                    $SQL .= $this->dbWrap($iCurrencyID,"int").", ";
                    $SQL .= $this->dbWrap($aRow["Amount"],"int").", ";
                    $SQL .= $this->dbWrap(time(),"datetime").");\n";
                }
            }
            if($SQL == "") return true;

            $oRes = $this->db->multi_query($SQL);
            while($this->db->more_results()) $this->db->next_result();

            if(!$oRes) {
                echo "MySQL Query Failed ...<br />";
                echo "Error: ".$this->db->error."<br />";
                echo "The Query was:<br />";
                echo str_replace("\n","<br />",$SQL);
                return false;
            } else {
                return true;
            }
        }

       /**
        *  Saves imported transactions to temporary table.
        */

        public function saveTemp($aData, $removeOld=false) {

            if(is_null($this->fundsID)) return false;

            if($removeOld) {
                $SQL = "DELETE FROM transactions_temp WHERE FundsID = '".$this->db->real_escape_string($this->fundsID)."';\n";
            } else {
                $SQL = "";
            }

            reset($aData);
            foreach($aData as $iKey=>$aRow) {
                if(array_key_exists("ID",$aRow)) {
                    $SQL .= "UPDATE transactions_temp SET ";
                    $SQL .= "FundsID = "        .$this->dbWrap($this->fundsID,"int").", ";
                    $SQL .= "RecordDate = "     .$this->dbWrap($aRow["RecordDate"],"date").", ";
                    $SQL .= "TransactionDate = ".$this->dbWrap($aRow["TransactionDate"],"date").", ";
                    $SQL .= "Details = "        .$this->dbWrap($aRow["Details"],"text").", ";
                    $SQL .= "Original = "       .$this->dbWrap($aRow["Original"],"int").", ";
                    $SQL .= "Currency = "       .$this->dbWrap($aRow["Currency"],"text").", ";
                    $SQL .= "Amount = "         .$this->dbWrap($aRow["Amount"],"int").", ";
                    $SQL .= "Hash = "           .$this->dbWrap($aRow["Hash"],"text")." ";
                    $SQL .= "WHERE ID = "       .$this->dbWrap($aRow["ID"],"int")." ";
                    $SQL .= "AND FundsID = '"   .$this->dbWrap($this->fundsID,"int").";\n";
                } else {
                    $SQL .= "INSERT INTO transactions_temp (";
                    $SQL .= "FundsID, ";
                    $SQL .= "RecordDate, ";
                    $SQL .= "TransactionDate, ";
                    $SQL .= "Details, ";
                    $SQL .= "Original, ";
                    $SQL .= "Currency, ";
                    $SQL .= "Amount, ";
                    $SQL .= "Hash ";
                    $SQL .= ") VALUES (";
                    $SQL .= $this->dbWrap($this->fundsID,"int").", ";
                    $SQL .= $this->dbWrap($aRow["RecordDate"],"date").", ";
                    $SQL .= $this->dbWrap($aRow["TransactionDate"],"date").", ";
                    $SQL .= $this->dbWrap($aRow["Details"],"text").", ";
                    $SQL .= $this->dbWrap($aRow["Original"],"int").", ";
                    $SQL .= $this->dbWrap($aRow["Currency"],"text").", ";
                    $SQL .= $this->dbWrap($aRow["Amount"],"int").", ";
                    $SQL .= $this->dbWrap($aRow["Hash"],"text").");\n";
                }
            }
            $oRes = $this->db->multi_query($SQL);
            while($this->db->more_results()) $this->db->next_result();

            if(!$oRes) {
                echo "MySQL Query Failed ...<br />";
                echo "Error: ".$this->db->error."<br />";
                echo "The Query was:<br />";
                echo str_replace("\n","<br />",$SQL);
                return false;
            } else {
                return true;
            }
        }

        public function deleteData($aIDs) {

            if(is_null($this->fundsID)) return false;

            $SQL = "";

            foreach($aIDs as $iID) {

                if(!$iID > 0) continue;

                $SQL .= "DELETE FROM transactions ";
                $SQL .= "WHERE FundsID = ".$this->dbWrap($this->fundsID,"int")." ";
                $SQL .= "AND ID = ".$this->dbWrap($iID,"int").";\n";
            }
            $oRes = $this->db->multi_query($SQL);
            while($this->db->more_results()) $this->db->next_result();

            if(!$oRes) {
                echo "MySQL Query Failed ...<br />";
                echo "Error: ".$this->db->error."<br />";
                echo "The Query was:<br />";
                echo str_replace("\n","<br />",$SQL);
                return false;
            } else {
                return true;
            }
        }
    }
?>
