<?php
   /**
    *  Where's My Money? – Import File
    * =================================
    *  Created 2017-06-01
    */

    $bMain  = true;
    require_once("includes/init.php");
    $theOpt = new Settings($oDB);

    $dataType = htmGet("Type",1,false,"Trans");
    $dataID   = htmGet("ID",0,false,0);
    $currStep = htmGet("Step",0,false,1);

    $aParts = array(
        "Trans" => array("Title"=>"Import Transactions", "Menu"=>"", "URL"=>""),
    );

   /**
    *  Page Content
    */

    // Header
    require_once("includes/header.php");
    makePageHeader($aParts,$dataType);

    if($dataType == "Trans") {

        $importOpt = "ImportCall_FundsID_".$dataID;

        $aImportTypes = array(
            "NO_1" => array("Call" => "NO_Sparebank1_CSV",  "Desc" => "[NOR] Sparebank1 Account CSV Export"),
            "NO_2" => array("Call" => "NO_Spreadsheet_4Col","Desc" => "[NOR] Copy/Paste from LibreOffice. 4 Colums: Date Detail Date Amount."),
        );

        if($currStep == 1) {

            $lastMethod = $theOpt->getValue($importOpt);

            echo "<form method='post' action='import.php?Type=".$dataType."&ID=".$dataID."&Step=2'>\n";
            echo "<table class='form-table' style='width: 100%;'>";
            echo "<tr>";
                echo "<th>Import Type</th>";
                echo "<td>";
                    echo "<select name='ImportCall'>";
                        foreach($aImportTypes as $iKey=>$aDetails) {
                            echo "<option value='".$iKey."'".($lastMethod==$iKey?" selected":"").">".$aDetails["Desc"]."</option>";
                        }
                    echo "</select>";
                echo "</td>";
            echo "</tr>";
            echo "<tr>";
                echo "<th>Raw Data</th>";
                echo "<td style='width: 100%;'><textarea name='rawData' class='mono text-raw'></textarea></td>";
            echo "</tr>";
            echo "<tr>";
                echo "<td colspan=2 class='input-button'><input type='submit' /></td>";
            echo "</tr>";
            echo "</table>\n";
            echo "</form>";
        }

        if($currStep == 2) {

            include_once("import/import_bank.php");

            $impCall = htmPost("ImportCall","");
            $rawData = htmPost("rawData","");

            $theOpt->setValue($importOpt,$impCall);
            $aImport = importBank($aImportTypes[$impCall]["Call"],$dataID,$rawData);

            $iCount  = $aImport["Meta"]["Count"];
            $dateMin = $aImport["Meta"]["DateMin"];
            $dateMax = $aImport["Meta"]["DateMax"];

            $theTrans = new Transact($oDB);
            $theTrans->setFilter("FundsID",$dataID);
            $theTrans->setFilter("FromDate",$dateMin-7*86400);
            $aExists  = $theTrans->getData();

            $oddEven  = 0;

            echo "<form method='post' action='import.php?Type=".$dataType."&ID=".$dataID."&Step=3'>\n";
            echo "<table class='list-table'>\n";
            echo "<tr class='list-head'>";
                echo "<td>&#10004;</td>";
                echo "<td>&#10006;</td>";
                echo "<td>Date</td>";
                echo "<td>Details</td>";
                echo "<td>Tr. Date</td>";
                echo "<td colspan=2 class='right'>Currency</td>";
                echo "<td class='right'>Amount</td>";
            echo "</tr>";
            foreach($aExists["Data"] as $iKey=>$aRow) {
                echo "<tr class='list-row ".($oddEven%2==0?"even":"odd")."'>";
                    echo "<td>&nbsp;</td>";
                    echo "<td><input type='checkbox' name='delLines[]' value='".$aRow["ID"]."' /></td>";
                    echo "<td>".rdblDate($aRow["RecordDate"],$cDateS)."</td>";
                    echo "<td>".$aRow["Details"]."</td>";
                    echo "<td>".rdblDate($aRow["TransactionDate"],$cDateS)."</td>";
                    echo "<td class='mono'>".$aRow["Currency"]."</td>";
                    echo "<td class='mono right'>".rdblAmount($aRow["Original"],100)."</td>";
                    echo "<td class='mono right'>".rdblAmount($aRow["Amount"],100)."</td>";
                echo "</tr>";
                $oddEven++;
            }
            foreach($aImport["Data"] as $iKey=>$aRow) {
                echo "<tr class='list-row g-".($oddEven%2==0?"even":"odd")."'>";
                    echo "<td><input type='checkbox' name='accLines[]' value='".$iKey."' checked /></td>";
                    echo "<td>&nbsp;</td>";
                    echo "<td>".rdblDate($aRow["RecordDate"],$cDateS)."</td>";
                    echo "<td>".$aRow["Details"]."</td>";
                    echo "<td>".rdblDate($aRow["TransactionDate"],$cDateS)."</td>";
                    echo "<td class='mono'>".$aRow["Currency"]."</td>";
                    echo "<td class='mono right'>".rdblAmount($aRow["Original"],100)."</td>";
                    echo "<td class='mono right'>".rdblAmount($aRow["Amount"],100)."</td>";
                echo "</tr>";
                $oddEven++;
            }
            echo "<tr class='list-stats'><td colspan=8>Import: ".number_format($aImport["Meta"]["Time"],2)." ms</td></tr>";
            echo "<tr>";
                echo "<td colspan=8 class='input-button'><input type='submit' value='Import' /></td>";
            echo "</tr>";
            echo "</table>\n";
            echo "<input type='hidden' name='importData' value='".base64_encode(json_encode($aImport))."'/>\n";
            echo "</form>\n";
        }

        if($currStep == 3) {

            $theTrans = new Transact($oDB);

            $aImport  = json_decode(base64_decode(htmPost("importData","")),true);
            $accLines = htmPost("accLines",array());
            $delLines = htmPost("delLines",array());

            $aData = array();
            foreach($accLines as $iImport) {
                $aData[] = $aImport["Data"][$iImport];
            }

            $theTrans->setFilter("FundsID",$dataID);

            $bSave = $theTrans->saveData($aData);
            $bDel  = $theTrans->deleteData($delLines);

            if($bSave && $bDel) header("Location: funds.php?Part=Trans&FundsID=".$dataID);
        }
    }

    require_once("includes/footer.php");

?>
