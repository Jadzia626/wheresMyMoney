<?php
   /**
    *  Where's My Money? – Import File
    * =================================
    *  Created 2017-06-01
    */

    $bMain = true;
    require_once("includes/init.php");

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

    if($currStep == 1) {
        echo "<form method='post' action='import.php?Type=".$dataType."&ID=".$dataID."&Step=2'>\n";
        echo "<table class='input-form'>";
        echo "<tr>";
            echo "<td>Raw Data</td>";
            echo "<td><textarea name='rawData'></textarea></td>";
        echo "</tr>";
        echo "<tr>";
            echo "<td colspan=2><input type='submit' /></td>";
        echo "</tr>";
        echo "</table>\n";
        echo "</form>";
    }

    if($currStep == 2) {
        include_once("import/import_bank.php");
        $rawData = htmPost("rawData","");
        $aImport = importBank("NO_Sparebank1_csv",$dataID,$rawData);

        // var_dump($aImport);

        $oddEven = 0;

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
        foreach($aImport["Data"] as $iKey=>$aRow) {
            echo "<tr class='list-row ".($oddEven%2==0?"even":"odd")."'>";
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
            // echo "<tr class='list-row ".($oddEven%2==0?"even":"odd")."'>";
            //     echo "<td><input type='checkbox' name='accept_".$iKey."' checked /></td>";
            //     echo "<td><input type='checkbox' name='reject_".$iKey."' /></td>";
            //     echo "<td>".rdblDate($aRow["RecordDate"],$cDateS)."</td>";
            //     echo "<td>".$aRow["Details"]."</td>";
            //     echo "<td>".rdblDate($aRow["TransactionDate"],$cDateS)."</td>";
            //     echo "<td class='mono'>".$aRow["Currency"]."</td>";
            //     echo "<td class='mono right'>".rdblAmount($aRow["Original"],100)."</td>";
            //     echo "<td class='mono right'>".rdblAmount($aRow["Amount"],100)."</td>";
            // echo "</tr>";
            // $oddEven++;
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

        $aData = array();
        foreach($accLines as $iImport) {
            $aData[] = $aImport["Data"][$iImport];
        }

        // var_dump($accLines);
        // var_dump($aImport);
        print_r($aData);
        $theTrans->setFilter("FundsID",$dataID);
        $theTrans->saveData($aData);

    }

    require_once("includes/footer.php");

?>
