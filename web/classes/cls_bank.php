<?php
   /**
    *  Where's My Money? – Bank Class
    * ================================
    *  Created 2017-05-31
    */

    class Bank
    {
        // Publics

        // Privates
        private $db;

        // Constructor
        function __construct($oDB) {
            $this->db = $oDB;
        }

        // Methods
        public function getEntry($ID=0) {

            $tic = microtime(true);

            $aReturn = array(
                "Meta" => array(
                    "Content" => "Banks",
                    "Count"   => 0,
                ),
                "Data" => array(),
            );

            $SQL  = "SELECT ";
            $SQL .= "ID, Name ";
            $SQL .= "FROM bank ";
            if($ID > 0) {
                $SQL .= "WHERE ID = '".$this->db->real_escape_string($ID)."'";
            }
            $oData = $this->db->query($SQL);

            while($aRow = $oData->fetch_assoc()) {
                $aReturn["Data"][] = $aRow;
            }
            $aReturn["Meta"]["Count"] = count($aReturn["Data"]);

            $toc = microtime(true);
            $aReturn["Meta"]["Time"] = ($toc-$tic)*1000;

            return $aReturn;
        }
    }
?>