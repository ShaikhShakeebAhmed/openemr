<?php
/*
 * Payments can be edited here whch includes deletion of an allocation, modifying the
 * same or adding a new allocation. Log is kept for the deleted ones.
 * The functions of this class support the billing process like the script billing_process.php.
 *
 * @package OpenEMR
 * @author Eldho Chacko <eldho@zhservices.com>
 * @author Paul Simon K <paul@zhservices.com>
 * @author Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) Z&H Consultancy Services Private Limited <sam@zhservices.com>
 * @copyright Copyright (C) 2018 Stephen Waite <stephen.waite@cmsvt.com>
 * @link http://www.open-emr.org
 * @license https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */
require_once("../globals.php");
require_once("$srcdir/log.inc");
require_once("$srcdir/invoice_summary.inc.php");
require_once("../../library/acl.inc");
require_once("$srcdir/auth.inc");
require_once("../../custom/code_types.inc.php");
require_once("$srcdir/patient.inc");
require_once("$srcdir/billrep.inc");
require_once("$srcdir/options.inc.php");
require_once("$srcdir/payment.inc.php");

use OpenEMR\Billing\ParseERA;
use OpenEMR\Billing\SLEOB;
use OpenEMR\Core\Header;

$screen='edit_payment';

// Deletion of payment distribution code

if (isset($_POST["mode"])) {
    if ($_POST["mode"] == "DeletePaymentDistribution") {
        $DeletePaymentDistributionId=trim(formData('DeletePaymentDistributionId'));
        $DeletePaymentDistributionIdArray=explode('_', $DeletePaymentDistributionId);
        $payment_id=$DeletePaymentDistributionIdArray[0];
        $PId=$DeletePaymentDistributionIdArray[1];
        $Encounter=$DeletePaymentDistributionIdArray[2];
        $Code=$DeletePaymentDistributionIdArray[3];
        $Modifier=$DeletePaymentDistributionIdArray[4];
        $Codetype=$DeletePaymentDistributionIdArray[5];
        //delete and log that action
        row_delete("ar_activity", "session_id ='$payment_id' and  pid ='$PId' AND " .
        "encounter='$Encounter' and code_type='$Codetype' and code='$Code' and modifier='$Modifier'");
        $Message='Delete';
        //------------------
        $_POST["mode"] = "searchdatabase";
    }
}

//===============================================================================
//Modify Payment Code.
//===============================================================================
if (isset($_POST["mode"])) {
    if ($_POST["mode"] == "ModifyPayments" || $_POST["mode"] == "FinishPayments") {
        $payment_id=$_REQUEST['payment_id'];
        //ar_session Code
        //===============================================================================
        if (trim(formData('type_name'))=='insurance') {
            $QueryPart="payer_id = '"       . trim(formData('hidden_type_code')) .
            "', patient_id = '"   . 0 ;
        } elseif (trim(formData('type_name'))=='patient') {
            $QueryPart="payer_id = '"       . 0 .
            "', patient_id = '"   . trim(formData('hidden_type_code')) ;
        }

        $user_id=$_SESSION['authUserID'];
        $closed=0;
        $modified_time = date('Y-m-d H:i:s');
        $check_date=DateToYYYYMMDD(formData('check_date'));
        $deposit_date=DateToYYYYMMDD(formData('deposit_date'));
        $post_to_date=DateToYYYYMMDD(formData('post_to_date'));
        if ($post_to_date=='') {
            $post_to_date=date('Y-m-d');
        }

        if (formData('deposit_date')=='') {
            $deposit_date=$post_to_date;
        }

        sqlStatement("update ar_session set "    .
        $QueryPart .
        "', user_id = '"     . trim($user_id)  .
        "', closed = '"      . trim($closed)  .
        "', reference = '"   . trim(formData('check_number')) .
        "', check_date = '"  . trim($check_date) .
        "', deposit_date = '" . trim($deposit_date)  .
        "', pay_total = '"    . trim(formData('payment_amount')) .
        "', modified_time = '" . trim($modified_time)  .
        "', payment_type = '"   . trim(formData('type_name')) .
        "', description = '"   . trim(formData('description')) .
        "', adjustment_code = '"   . trim(formData('adjustment_code')) .
        "', post_to_date = '" . trim($post_to_date)  .
        "', payment_method = '"   . trim(formData('payment_method')) .
        "'    where session_id='$payment_id'");
    //===============================================================================
        $CountIndexAbove=$_REQUEST['CountIndexAbove'];
        $CountIndexBelow=$_REQUEST['CountIndexBelow'];
        $hidden_patient_code=$_REQUEST['hidden_patient_code'];
        $user_id=$_SESSION['authUserID'];
        $created_time = date('Y-m-d H:i:s');
        //==================================================================
        //UPDATION
        //It is done with out deleting any old entries.
        //==================================================================
        for ($CountRow=1; $CountRow<=$CountIndexAbove; $CountRow++) {
            if (isset($_POST["HiddenEncounter$CountRow"])) {
                if (isset($_POST["Payment$CountRow"]) && $_POST["Payment$CountRow"]*1>0) {
                    if (trim(formData('type_name'))=='insurance') {
                        if (trim(formData("HiddenIns$CountRow"))==1) {
                            $AccountCode="IPP";
                        }

                        if (trim(formData("HiddenIns$CountRow"))==2) {
                            $AccountCode="ISP";
                        }

                        if (trim(formData("HiddenIns$CountRow"))==3) {
                            $AccountCode="ITP";
                        }
                    } elseif (trim(formData('type_name'))=='patient') {
                        $AccountCode="PP";
                    }

                        $resPayment = sqlStatement("SELECT  * from ar_activity " .
                          " where  session_id ='$payment_id' and pid ='" . trim(formData("HiddenPId$CountRow"))  .
                          "' and  encounter  ='" . trim(formData("HiddenEncounter$CountRow"))  .
                                      "' and  code_type  ='" . trim(formData("HiddenCodetype$CountRow"))  .
                          "' and  code  ='" . trim(formData("HiddenCode$CountRow"))  .
                          "' and  modifier  ='" . trim(formData("HiddenModifier$CountRow"))  .
                          "' and pay_amount>0");
                    if (sqlNumRows($resPayment)>0) {
                            sqlStatement("update ar_activity set "    .
                              "   post_user = '" . trim($user_id)  .
                              "', modified_time = '"  . trim($created_time) .
                              "', pay_amount = '" . trim(formData("Payment$CountRow"))  .
                              "', account_code = '" . "$AccountCode"  .
                              "', payer_type = '"   . trim(formData("HiddenIns$CountRow")) .
                              "', reason_code = '"   . trim(formData("ReasonCode$CountRow")) .
                              "' where  session_id ='$payment_id' and pid ='" . trim(formData("HiddenPId$CountRow"))  .
                              "' and  encounter  ='" . trim(formData("HiddenEncounter$CountRow"))  .
                                    "' and  code_type  ='" . trim(formData("HiddenCodetype$CountRow"))  .
                              "' and  code  ='" . trim(formData("HiddenCode$CountRow"))  .
                              "' and  modifier  ='" . trim(formData("HiddenModifier$CountRow"))  .
                              "' and pay_amount>0");
                    } else {
                                 sqlBeginTrans();
                                 $sequence_no = sqlQuery("SELECT IFNULL(MAX(sequence_no),0) + 1 AS increment FROM ar_activity WHERE pid = ? AND encounter = ?", array(trim(formData("HiddenPId$CountRow")), trim(formData("HiddenEncounter$CountRow"))));
                                 sqlStatement("insert into ar_activity set "    .
                                "pid = '"       . trim(formData("HiddenPId$CountRow")) .
                                "', encounter = '"     . trim(formData("HiddenEncounter$CountRow"))  .
                                "', sequence_no = '"     . $sequence_no['increment']  .
                                    "', code_type = '"      . trim(formData("HiddenCodetype$CountRow"))  .
                                "', code = '"      . trim(formData("HiddenCode$CountRow"))  .
                                "', modifier = '"      . trim(formData("HiddenModifier$CountRow"))  .
                                "', payer_type = '"   . trim(formData("HiddenIns$CountRow")) .
                                "', reason_code = '"   . trim(formData("ReasonCode$CountRow")) .
                                "', post_time = '"  . trim($created_time) .
                                "', post_user = '" . trim($user_id)  .
                                "', session_id = '"    . trim(formData('payment_id')) .
                                "', modified_time = '"  . trim($created_time) .
                                "', pay_amount = '" . trim(formData("Payment$CountRow"))  .
                                "', adj_amount = '"    . 0 .
                                "', account_code = '" . "$AccountCode"  .
                                "'");
                                 sqlCommitTrans();
                    }
                } else {
                    sqlStatement("delete from ar_activity " .
                          " where  session_id ='$payment_id' and pid ='" . trim(formData("HiddenPId$CountRow"))  .
                          "' and  encounter  ='" . trim(formData("HiddenEncounter$CountRow"))  .
                                    "' and  code_type  ='" . trim(formData("HiddenCodetype$CountRow"))  .
                          "' and  code  ='" . trim(formData("HiddenCode$CountRow"))  .
                          "' and  modifier  ='" . trim(formData("HiddenModifier$CountRow"))  .
                          "' and pay_amount>0");
                }

          //==============================================================================================================================
                if (isset($_POST["AdjAmount$CountRow"]) && $_POST["AdjAmount$CountRow"]*1!=0) {
                    if (trim(formData('type_name'))=='insurance') {
                        $AdjustString="Ins adjust Ins".trim(formData("HiddenIns$CountRow"));
                        $AccountCode="IA";
                    } elseif (trim(formData('type_name'))=='patient') {
                        $AdjustString="Pt adjust";
                        $AccountCode="PA";
                    }

                      $resPayment = sqlStatement("SELECT  * from ar_activity " .
                          " where  session_id ='$payment_id' and pid ='" . trim(formData("HiddenPId$CountRow"))  .
                          "' and  encounter  ='" . trim(formData("HiddenEncounter$CountRow"))  .
                                    "' and  code_type  ='" . trim(formData("HiddenCodetype$CountRow"))  .
                          "' and  code  ='" . trim(formData("HiddenCode$CountRow"))  .
                          "' and  modifier  ='" . trim(formData("HiddenModifier$CountRow"))  .
                          "' and adj_amount!=0");
                    if (sqlNumRows($resPayment)>0) {
                              sqlStatement("update ar_activity set "    .
                                "   post_user = '" . trim($user_id)  .
                                "', modified_time = '"  . trim($created_time) .
                                "', adj_amount = '"    . trim(formData("AdjAmount$CountRow")) .
                                "', memo = '" . "$AdjustString"  .
                                "', account_code = '" . "$AccountCode"  .
                                "', payer_type = '"   . trim(formData("HiddenIns$CountRow")) .
                                "' where  session_id ='$payment_id' and pid ='" . trim(formData("HiddenPId$CountRow"))  .
                                "' and  encounter  ='" . trim(formData("HiddenEncounter$CountRow"))  .
                                  "' and  code_type  ='" . trim(formData("HiddenCodetype$CountRow"))  .
                                "' and  code  ='" . trim(formData("HiddenCode$CountRow"))  .
                                "' and  modifier  ='" . trim(formData("HiddenModifier$CountRow"))  .
                                "' and adj_amount!=0");
                    } else {
                              sqlBeginTrans();
                              $sequence_no = sqlQuery("SELECT IFNULL(MAX(sequence_no),0) + 1 AS increment FROM ar_activity WHERE pid = ? AND encounter = ?", array(trim(formData("HiddenPId$CountRow")), trim(formData("HiddenEncounter$CountRow"))));
                              sqlStatement("insert into ar_activity set "    .
                                "pid = '"       . trim(formData("HiddenPId$CountRow")) .
                                "', encounter = '"     . trim(formData("HiddenEncounter$CountRow"))  .
                                "', sequence_no = '"     . $sequence_no['increment']  .
                                "', code_type = '"      . trim(formData("HiddenCodetype$CountRow"))  .
                                "', code = '"      . trim(formData("HiddenCode$CountRow"))  .
                                "', modifier = '"      . trim(formData("HiddenModifier$CountRow"))  .
                                "', payer_type = '"   . trim(formData("HiddenIns$CountRow")) .
                                "', post_time = '"  . trim($created_time) .
                                "', post_user = '" . trim($user_id)  .
                                "', session_id = '"    . trim(formData('payment_id')) .
                                "', modified_time = '"  . trim($created_time) .
                                "', pay_amount = '" . 0  .
                                "', adj_amount = '"    . trim(formData("AdjAmount$CountRow")) .
                                "', memo = '" . "$AdjustString"  .
                                "', account_code = '" . "$AccountCode"  .
                                "'");
                                sqlCommitTrans();
                    }
                } else {
                    sqlStatement("delete from ar_activity " .
                          " where  session_id ='$payment_id' and pid ='" . trim(formData("HiddenPId$CountRow"))  .
                          "' and  encounter  ='" . trim(formData("HiddenEncounter$CountRow"))  .
                                    "' and  code_type  ='" . trim(formData("HiddenCodetype$CountRow"))  .
                          "' and  code  ='" . trim(formData("HiddenCode$CountRow"))  .
                          "' and  modifier  ='" . trim(formData("HiddenModifier$CountRow"))  .
                          "' and adj_amount!=0");
                }

          //==============================================================================================================================
                if (isset($_POST["Deductible$CountRow"]) && $_POST["Deductible$CountRow"]*1>0) {
                      $resPayment = sqlStatement("SELECT  * from ar_activity " .
                          " where  session_id ='$payment_id' and pid ='" . trim(formData("HiddenPId$CountRow"))  .
                          "' and  encounter  ='" . trim(formData("HiddenEncounter$CountRow"))  .
                                    "' and  code_type  ='" . trim(formData("HiddenCodetype$CountRow"))  .
                          "' and  code  ='" . trim(formData("HiddenCode$CountRow"))  .
                          "' and  modifier  ='" . trim(formData("HiddenModifier$CountRow"))  .
                          "' and (memo like 'Deductable%' OR memo like 'Deductible%')");
                    if (sqlNumRows($resPayment)>0) {
                              sqlStatement("update ar_activity set "    .
                                "   post_user = '" . trim($user_id)  .
                                "', modified_time = '"  . trim($created_time) .
                                "', memo = '"    . "Deductible $".trim(formData("Deductible$CountRow")) .
                                "', account_code = '" . "Deduct"  .
                                "', payer_type = '"   . trim(formData("HiddenIns$CountRow")) .
                                "' where  session_id ='$payment_id' and pid ='" . trim(formData("HiddenPId$CountRow"))  .
                                "' and  encounter  ='" . trim(formData("HiddenEncounter$CountRow"))  .
                                  "' and  code_type  ='" . trim(formData("HiddenCodetype$CountRow"))  .
                                "' and  code  ='" . trim(formData("HiddenCode$CountRow"))  .
                                "' and  modifier  ='" . trim(formData("HiddenModifier$CountRow"))  .
                                "' and (memo like 'Deductable%' OR memo like 'Deductible%')");
                    } else {
                              sqlBeginTrans();
                              $sequence_no = sqlQuery("SELECT IFNULL(MAX(sequence_no),0) + 1 AS increment FROM ar_activity WHERE pid = ? AND encounter = ?", array(trim(formData("HiddenPId$CountRow")), trim(formData("HiddenEncounter$CountRow"))));
                              sqlStatement("insert into ar_activity set "    .
                                "pid = '"       . trim(formData("HiddenPId$CountRow")) .
                                "', encounter = '"     . trim(formData("HiddenEncounter$CountRow"))  .
                                "', sequence_no = '"     . $sequence_no['increment']  .
                                "', code_type = '"      . trim(formData("HiddenCodetype$CountRow"))  .
                                "', code = '"      . trim(formData("HiddenCode$CountRow"))  .
                                "', modifier = '"      . trim(formData("HiddenModifier$CountRow"))  .
                                "', payer_type = '"   . trim(formData("HiddenIns$CountRow")) .
                                "', post_time = '"  . trim($created_time) .
                                "', post_user = '" . trim($user_id)  .
                                "', session_id = '"    . trim(formData('payment_id')) .
                                "', modified_time = '"  . trim($created_time) .
                                "', pay_amount = '" . 0  .
                                "', adj_amount = '"    . 0 .
                                "', memo = '"    . "Deductible $".trim(formData("Deductible$CountRow")) .
                                "', account_code = '" . "Deduct"  .
                                "'");
                              sqlCommitTrans();
                    }
                } else {
                    sqlStatement("delete from ar_activity " .
                          " where  session_id ='$payment_id' and pid ='" . trim(formData("HiddenPId$CountRow"))  .
                          "' and  encounter  ='" . trim(formData("HiddenEncounter$CountRow"))  .
                                    "' and  code_type  ='" . trim(formData("HiddenCodetype$CountRow"))  .
                          "' and  code  ='" . trim(formData("HiddenCode$CountRow"))  .
                          "' and  modifier  ='" . trim(formData("HiddenModifier$CountRow"))  .
                          "' and (memo like 'Deductable%' OR memo like 'Deductible%')");
                }

          //==============================================================================================================================
                if (isset($_POST["Takeback$CountRow"]) && $_POST["Takeback$CountRow"]*1>0) {
                      $resPayment = sqlStatement("SELECT  * from ar_activity " .
                          " where  session_id ='$payment_id' and pid ='" . trim(formData("HiddenPId$CountRow"))  .
                          "' and  encounter  ='" . trim(formData("HiddenEncounter$CountRow"))  .
                                    "' and  code_type  ='" . trim(formData("HiddenCodetype$CountRow"))  .
                          "' and  code  ='" . trim(formData("HiddenCode$CountRow"))  .
                          "' and  modifier  ='" . trim(formData("HiddenModifier$CountRow"))  .
                          "' and pay_amount < 0");
                    if (sqlNumRows($resPayment)>0) {
                        sqlStatement("update ar_activity set "    .
                                "   post_user = '" . trim($user_id)  .
                                "', modified_time = '"  . trim($created_time) .
                                "', pay_amount = '" . trim(formData("Takeback$CountRow"))*-1  .
                                "', account_code = '" . "Takeback"  .
                                "', payer_type = '"   . trim(formData("HiddenIns$CountRow")) .
                                "' where  session_id ='$payment_id' and pid ='" . trim(formData("HiddenPId$CountRow"))  .
                                "' and  encounter  ='" . trim(formData("HiddenEncounter$CountRow"))  .
                                  "' and  code_type  ='" . trim(formData("HiddenCodetype$CountRow"))  .
                                "' and  code  ='" . trim(formData("HiddenCode$CountRow"))  .
                                "' and  modifier  ='" . trim(formData("HiddenModifier$CountRow"))  .
                                "' and pay_amount < 0");
                    } else {
                              sqlBeginTrans();
                              $sequence_no = sqlQuery("SELECT IFNULL(MAX(sequence_no),0) + 1 AS increment FROM ar_activity WHERE pid = ? AND encounter = ?", array(trim(formData("HiddenPId$CountRow")), trim(formData("HiddenEncounter$CountRow"))));
                              sqlStatement("insert into ar_activity set "    .
                                "pid = '"       . trim(formData("HiddenPId$CountRow")) .
                                "', encounter = '"     . trim(formData("HiddenEncounter$CountRow"))  .
                                "', sequence_no = '"     . $sequence_no['increment']  .
                                "', code_type = '"      . trim(formData("HiddenCodetype$CountRow"))  .
                                "', code = '"      . trim(formData("HiddenCode$CountRow"))  .
                                "', modifier = '"      . trim(formData("HiddenModifier$CountRow"))  .
                                "', payer_type = '"   . trim(formData("HiddenIns$CountRow")) .
                                "', post_time = '"  . trim($created_time) .
                                "', post_user = '" . trim($user_id)  .
                                "', session_id = '"    . trim(formData('payment_id')) .
                                "', modified_time = '"  . trim($created_time) .
                                "', pay_amount = '" . trim(formData("Takeback$CountRow"))*-1  .
                                "', adj_amount = '"    . 0 .
                                "', account_code = '" . "Takeback"  .
                                "'");
                                sqlCommitTrans();
                    }
                } else {
                    sqlStatement("delete from ar_activity " .
                          " where  session_id ='$payment_id' and pid ='" . trim(formData("HiddenPId$CountRow"))  .
                          "' and  encounter  ='" . trim(formData("HiddenEncounter$CountRow"))  .
                                    "' and  code_type  ='" . trim(formData("HiddenCodetype$CountRow"))  .
                          "' and  code  ='" . trim(formData("HiddenCode$CountRow"))  .
                          "' and  modifier  ='" . trim(formData("HiddenModifier$CountRow"))  .
                          "' and pay_amount < 0");
                }

          //==============================================================================================================================
                if (isset($_POST["FollowUp$CountRow"]) && $_POST["FollowUp$CountRow"]=='y') {
                      $resPayment = sqlStatement("SELECT  * from ar_activity " .
                          " where  session_id ='$payment_id' and pid ='" . trim(formData("HiddenPId$CountRow"))  .
                          "' and  encounter  ='" . trim(formData("HiddenEncounter$CountRow"))  .
                                    "' and  code_type  ='" . trim(formData("HiddenCodetype$CountRow"))  .
                          "' and  code  ='" . trim(formData("HiddenCode$CountRow"))  .
                          "' and  modifier  ='" . trim(formData("HiddenModifier$CountRow"))  .
                          "' and follow_up ='y'");
                    if (sqlNumRows($resPayment)>0) {
                              sqlStatement("update ar_activity set "    .
                                "   post_user = '" . trim($user_id)  .
                                "', modified_time = '"  . trim($created_time) .
                                "', follow_up = '"    . "y" .
                                "', follow_up_note = '"    . trim(formData("FollowUpReason$CountRow")) .
                                "', payer_type = '"   . trim(formData("HiddenIns$CountRow")) .
                                "' where  session_id ='$payment_id' and pid ='" . trim(formData("HiddenPId$CountRow"))  .
                                "' and  encounter  ='" . trim(formData("HiddenEncounter$CountRow"))  .
                                  "' and  code_type  ='" . trim(formData("HiddenCodetype$CountRow"))  .
                                "' and  code  ='" . trim(formData("HiddenCode$CountRow"))  .
                                "' and  modifier  ='" . trim(formData("HiddenModifier$CountRow"))  .
                                "' and follow_up ='y'");
                    } else {
                              sqlBeginTrans();
                              $sequence_no = sqlQuery("SELECT IFNULL(MAX(sequence_no),0) + 1 AS increment FROM ar_activity WHERE pid = ? AND encounter = ?", array(trim(formData("HiddenPId$CountRow")), trim(formData("HiddenEncounter$CountRow"))));
                              sqlStatement("insert into ar_activity set "    .
                                "pid = '"       . trim(formData("HiddenPId$CountRow")) .
                                "', encounter = '"     . trim(formData("HiddenEncounter$CountRow"))  .
                                "', sequence_no = '"     . $sequence_no['increment']  .
                                "', code_type = '"      . trim(formData("HiddenCodetype$CountRow"))  .
                                "', code = '"      . trim(formData("HiddenCode$CountRow"))  .
                                "', modifier = '"      . trim(formData("HiddenModifier$CountRow"))  .
                                "', payer_type = '"   . trim(formData("HiddenIns$CountRow")) .
                                "', post_time = '"  . trim($created_time) .
                                "', post_user = '" . trim($user_id)  .
                                "', session_id = '"    . trim(formData('payment_id')) .
                                "', modified_time = '"  . trim($created_time) .
                                "', pay_amount = '" . 0  .
                                "', adj_amount = '"    . 0 .
                                "', follow_up = '"    . "y" .
                                "', follow_up_note = '"    . trim(formData("FollowUpReason$CountRow")) .
                                "'");
                              sqlCommitTrans();
                    }
                } else {
                    sqlStatement("delete from ar_activity " .
                          " where  session_id ='$payment_id' and pid ='" . trim(formData("HiddenPId$CountRow"))  .
                          "' and  encounter  ='" . trim(formData("HiddenEncounter$CountRow"))  .
                                    "' and  code_type  ='" . trim(formData("HiddenCodetype$CountRow"))  .
                          "' and  code  ='" . trim(formData("HiddenCode$CountRow"))  .
                          "' and  modifier  ='" . trim(formData("HiddenModifier$CountRow"))  .
                          "' and follow_up ='y'");
                }

          //==============================================================================================================================
            } else {
                break;
            }
        }

        //=========
        //INSERTION of new entries,continuation of modification.
        //=========
        for ($CountRow=$CountIndexAbove+1; $CountRow<=$CountIndexAbove+$CountIndexBelow; $CountRow++) {
            if (isset($_POST["HiddenEncounter$CountRow"])) {
                DistributionInsert($CountRow, $created_time, $user_id);
            } else {
                break;
            }
        }

        if ($_REQUEST['global_amount']=='yes') {
            sqlStatement("update ar_session set global_amount=".trim(formData("HidUnappliedAmount"))*1 ." where session_id ='$payment_id'");
        }

        if ($_POST["mode"]=="FinishPayments") {
              $Message='Finish';
        }

        $_POST["mode"] = "searchdatabase";
        $Message='Modify';
    }
}

//==============================================================================
//Search Code
//===============================================================================
$payment_id=$payment_id*1 > 0 ? $payment_id : $_REQUEST['payment_id'];
$ResultSearchSub = sqlStatement("SELECT  distinct encounter,code_type,code,modifier, pid from ar_activity where  session_id ='$payment_id' order by pid,encounter,code,modifier");
//==============================================================================

//==============================================================================
//===============================================================================
?>
<!DOCTYPE html>
<html>
<head>

    <?php Header::setupHeader(['datetime-picker', 'common']); ?>

<script language='JavaScript'>
 var mypcc = '1';
</script>
<?php include_once("{$GLOBALS['srcdir']}/payment_jav.inc.php"); ?>
<?php include_once("{$GLOBALS['srcdir']}/ajax/payment_ajax_jav.inc.php"); ?>
<script language="javascript" type="text/javascript">
    function ModifyPayments()
    {//Used while modifying the allocation
       if(!FormValidations())//FormValidations contains the form checks
        {
         return false;
        }
       if(CompletlyBlankAbove())//The distribution rows already in the database are checked.
        {
         alert("<?php echo htmlspecialchars(xl('None of the Top Distribution Row Can be Completly Blank.'), ENT_QUOTES);
            echo htmlspecialchars('\n');echo htmlspecialchars(xl('Use Delete Option to Remove.'), ENT_QUOTES) ?>")
         return false;
        }
       if(!CheckPayingEntityAndDistributionPostFor())//Ensures that Insurance payment is distributed under Ins1,Ins2,Ins3 and Patient paymentat under Pat.
        {
         return false;
        }
       if(CompletlyBlankBelow())//The newly added distribution rows are checked.
        {
         alert("<?php echo htmlspecialchars(xl('Fill any of the Below Row.'), ENT_QUOTES) ?>")
         return false;
        }
       PostValue=CheckUnappliedAmount();//Decides TdUnappliedAmount >0, or <0 or =0
       if(PostValue==1)
        {
         alert("<?php echo htmlspecialchars(xl('Cannot Modify Payments.Undistributed is Negative.'), ENT_QUOTES) ?>")
         return false;
        }
       if(confirm("<?php echo htmlspecialchars(xl('Would you like to Modify Payments?'), ENT_QUOTES) ?>"))
        {
           document.getElementById('mode').value='ModifyPayments';
           top.restoreSession();
           document.forms[0].submit();
        }
       else
        return false;
    }
    function FinishPayments()
    {
       if(!FormValidations())//FormValidations contains the form checks
        {
         return false;
        }
       if(CompletlyBlankAbove())//The distribution rows already in the database are checked.
        {
         alert("<?php echo htmlspecialchars(xl('None of the Top Distribution Row Can be Completly Blank.'), ENT_QUOTES);
            echo htmlspecialchars('\n');echo htmlspecialchars(xl('Use Delete Option to Remove.'), ENT_QUOTES) ?>")
         return false;
        }
       if(!CheckPayingEntityAndDistributionPostFor())//Ensures that Insurance payment is distributed under Ins1,Ins2,Ins3 and Patient paymentat under Pat.
        {
         return false;
        }
       if(CompletlyBlankBelow())//The newly added distribution rows are checked.
        {
         alert("<?php echo htmlspecialchars(xl('Fill any of the Below Row.'), ENT_QUOTES) ?>")
         return false;
        }
       PostValue=CheckUnappliedAmount();//Decides TdUnappliedAmount >0, or <0 or =0
       if(PostValue==1)
        {
         alert("<?php echo htmlspecialchars(xl('Cannot Modify Payments.Undistributed is Negative.'), ENT_QUOTES) ?>")
         return false;
        }
       if(PostValue==2)
        {
           if(confirm("<?php echo htmlspecialchars(xl('Would you like to Modify and Finish Payments?'), ENT_QUOTES) ?>"))
            {
               UnappliedAmount=document.getElementById('TdUnappliedAmount').innerHTML*1;
               if(confirm("<?php echo htmlspecialchars(xl('Undistributed is'), ENT_QUOTES) ?>" + ' ' + UnappliedAmount + '.' + "<?php echo htmlspecialchars('\n');echo htmlspecialchars(xl('Would you like the balance amount to apply to Global Account?'), ENT_QUOTES) ?>"))
                {
                   document.getElementById('mode').value='FinishPayments';
                   document.getElementById('global_amount').value='yes';
                   top.restoreSession();
                   document.forms[0].submit();
                }
               else
                {
                   document.getElementById('mode').value='FinishPayments';
                   top.restoreSession();
                   document.forms[0].submit();
                }
            }
           else
            return false;
        }
       else
        {
           if(confirm("<?php echo htmlspecialchars(xl('Would you like to Modify and Finish Payments?'), ENT_QUOTES) ?>"))
            {
               document.getElementById('mode').value='FinishPayments';
               top.restoreSession();
               document.forms[0].submit();
            }
           else
            return false;
        }

    }
    function CompletlyBlankAbove()
    {//The distribution rows already in the database are checked.
    //It is not allowed to be made completly empty.If needed delete option need to be used.
     CountIndexAbove=document.getElementById('CountIndexAbove').value*1;
     for(RowCount=1;RowCount<=CountIndexAbove;RowCount++)
      {
      if(document.getElementById('Allowed'+RowCount).value=='' && document.getElementById('Payment'+RowCount).value=='' && document.getElementById('AdjAmount'+RowCount).value=='' && document.getElementById('Deductible'+RowCount).value=='' && document.getElementById('Takeback'+RowCount).value=='' && document.getElementById('FollowUp'+RowCount).checked==false)
       {
        return true;
       }
      }
     return false;
    }
    function CompletlyBlankBelow()
    {//The newly added distribution rows are checked.
    //It is not allowed to be made completly empty.
     CountIndexAbove=document.getElementById('CountIndexAbove').value*1;
     CountIndexBelow=document.getElementById('CountIndexBelow').value*1;
     if(CountIndexBelow==0)
      return false;
     for(RowCount=CountIndexAbove+1;RowCount<=CountIndexAbove+CountIndexBelow;RowCount++)
      {
      if(document.getElementById('Allowed'+RowCount).value=='' && document.getElementById('Payment'+RowCount).value=='' && document.getElementById('AdjAmount'+RowCount).value=='' && document.getElementById('Deductible'+RowCount).value=='' && document.getElementById('Takeback'+RowCount).value=='' && document.getElementById('FollowUp'+RowCount).checked==false)
       {

       }
       else
        return false;
      }
     return true;
    }
    function OnloadAction()
    {//Displays message while loading after some action.
     after_value=document.getElementById('ActionStatus').value;
     if(after_value=='Delete')
      {
       alert("<?php echo htmlspecialchars(xl('Successfully Deleted'), ENT_QUOTES) ?>")
       return true;
      }
     if(after_value=='Modify' || after_value=='Finish')
      {
       alert("<?php echo htmlspecialchars(xl('Successfully Modified'), ENT_QUOTES) ?>")
       return true;
      }
     after_value=document.getElementById('after_value').value;
     payment_id=document.getElementById('payment_id').value;
     if(after_value=='distribute')
      {
      }
     else if(after_value=='new_payment')
      {
       if(document.getElementById('TablePatientPortion'))
        {
           document.getElementById('TablePatientPortion').style.display='none';
        }
       if(confirm("<?php echo htmlspecialchars(xl('Successfully Saved.Would you like to Distribute?'), ENT_QUOTES) ?>"))
        {
           if(document.getElementById('TablePatientPortion'))
            {
               document.getElementById('TablePatientPortion').style.display='';
            }
        }
      }

    }
    function DeletePaymentDistribution(DeleteId)
    {//Confirms deletion of payment distribution.
       if(confirm("<?php echo htmlspecialchars(xl('Would you like to Delete Payment Distribution?'), ENT_QUOTES) ?>"))
        {
           document.getElementById('mode').value='DeletePaymentDistribution';
           document.getElementById('DeletePaymentDistributionId').value=DeleteId;
           top.restoreSession();
           document.forms[0].submit();
        }
       else
        return false;
    }
    //========================================================================================

    $(document).ready(function() {
       $('.datepicker').datetimepicker({
            <?php $datetimepicker_timepicker = false; ?>
            <?php $datetimepicker_showseconds = false; ?>
            <?php $datetimepicker_formatInput = true; ?>
            <?php require($GLOBALS['srcdir'] . '/js/xl/jquery-datetimepicker-2-5-4.js.php'); ?>
            <?php // can add any additional javascript settings to datetimepicker here; need to prepend first setting with a comma ?>
       });
    });

    </script>
<script language="javascript" type="text/javascript">
document.onclick=HideTheAjaxDivs;
</script>
<style>
.class1 {
    width: 125px;
}
.class2 {
    width: 250px;
}
.class3 {
    width: 100px;
}
.bottom {
    border-bottom: 1px solid black;
}
.top {
    border-top: 1px solid black;
}
.left {
    border-left: 1px solid black;
}
.right {
    border-right: 1px solid black;
}
#ajax_div_insurance {
    position: absolute;
    z-index: 10;
    /*
       left: 20px;
       top: 300px;
       */
    background-color: #FBFDD0;
    border: 1px solid #ccc;
    padding: 10px;
}
#ajax_div_patient {
    position: absolute;
    z-index: 10;
    /*
       left: 20px;
       top: 300px;
       */
    background-color: #FBFDD0;
    border: 1px solid #ccc;
    padding: 10px;
}
.form-group {
    margin-bottom: 5px;
}
legend {
    border-bottom: 2px solid #E5E5E5;
    background: #E5E5E5;
    padding-left: 10px;
}
.form-horizontal .control-label {
    padding-top: 2px;
}
fieldset {
    border-color: #68171A !important;
    background-color: #f2f2f2;
    /*#e7e7e7*/
    margin-bottom: 10px;
    padding-bottom: 15px;
}
@media only screen and (max-width: 768px) {
    [class*="col-"] {
        width: 100%;
        text-align: left!Important;
    }
}
</style>
<link rel="stylesheet" href="<?php echo $css_header; ?>" type="text/css">
</head>
<body class="body_top" onload="OnloadAction()">
    <div class="container">
    <?php
    if ($_REQUEST['ParentPage']=='new_payment') {
    ?>
    <div class="row">
        <div class="page-header">
            <h2><?php echo xlt('Payments'); ?></h2>
        </div>
    </div>
    
    <div class="row" >
        <nav class="navbar navbar-default navbar-color navbar-static-top" >
            <div class="container-fluid">
                <div class="navbar-header">
                    <button class="navbar-toggle" data-target="#myNavbar" data-toggle="collapse" type="button"><span class="icon-bar"></span> <span class="icon-bar"></span> <span class="icon-bar"></span></button>
                </div>
                <div class="collapse navbar-collapse" id="myNavbar" >
                    <ul class="nav navbar-nav" >
                        <li class="active oe-bold-black">
                            <a href='new_payment.php' style="font-weight:700; color:#000000"><?php echo xlt('New Payment'); ?></a>
                        </li>
                        <li class="oe-bold-black" >
                            <a href='search_payments.php' style="font-weight:700; color:#000000"><?php echo xlt('Search Payment'); ?></a>
                        </li>
                        <li class="oe-bold-black">
                            <a href='era_payments.php' style="font-weight:700; color:#000000"><?php echo xlt('ERA Posting'); ?></a>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>
    </div>
    <?php
    }
    ?>
    <div class="row">
        <form name='new_payment' method='post'  action="edit_payment.php" onsubmit='
        <?php
        if ($payment_id*1==0) {
            echo "top.restoreSession();return SavePayment();";
        } else {
            echo "return false;";
        }
        ?> 
        ' style="display:inline" >
            <?php
            if ($payment_id*1>0) { ?>
            <fieldset>
            <?php
                require_once("payment_master.inc.php");  //Check/cash details are entered here.
            ?>
            <?php
            }//End of if($payment_id*1>0)
            ?>
            <?php
            if ($payment_id*1>0) {//Distribution rows already in the database are displayed.
            ?>
                <?php //
                    $resCount = sqlStatement("SELECT distinct encounter,code_type,code,modifier from ar_activity where  session_id ='$payment_id' ");
                    $TotalRows=sqlNumRows($resCount);
                    $CountPatient=0;
                    $CountIndex=0;
                    $CountIndexAbove=0;
                    $paymenttot=0;
                    $adjamttot=0;
                    $deductibletot=0;
                    $takebacktot=0;
                    $allowedtot=0;
                if ($RowSearchSub = sqlFetchArray($ResultSearchSub)) {
                    do {
                        $CountPatient++;
                        $PId=$RowSearchSub['pid'];
                        $EncounterMaster=$RowSearchSub['encounter'];
                                                // Only use the code_type in the queries below if it is specified in the ar_activity table.
                                                // If it is not specified in the ar_activity table, also note it is not requested from the
                                                // billing table in below query, thus making it blank in all queries below in this script.
                                                $CodetypeMaster=$RowSearchSub['code_type'];
                                                $sql_select_part_codetype = "";
                                                $sql_where_part_codetype = "";
                        if (!empty($CodetypeMaster)) {
                            $sql_select_part_codetype = "billing.code_type,";
                            $sql_where_part_codetype = "and billing.code_type ='$CodetypeMaster'";
                        }
                        $CodeMaster=$RowSearchSub['code'];
                        $ModifierMaster=$RowSearchSub['modifier'];
                        $res = sqlStatement("SELECT fname,lname,mname FROM patient_data where pid ='$PId'");
                        $row = sqlFetchArray($res);
                        $fname=$row['fname'];
                        $lname=$row['lname'];
                        $mname=$row['mname'];
                        $NameDB=$lname.' '.$fname.' '.$mname;
                        $ResultSearch = sqlStatement("SELECT billing.id,last_level_closed,billing.encounter,form_encounter.`date`,$sql_select_part_codetype billing.code,billing.modifier,fee
                             FROM billing ,form_encounter
                             where billing.encounter=form_encounter.encounter and billing.pid=form_encounter.pid and
                             code_type!='ICD9' and  code_type!='COPAY' and billing.activity!=0 and
                             form_encounter.pid ='$PId' and billing.pid ='$PId' and billing.encounter ='$EncounterMaster'
                                                      $sql_where_part_codetype
                              and billing.code ='$CodeMaster'
                               and billing.modifier ='$ModifierMaster'
                             ORDER BY form_encounter.`date`,form_encounter.encounter,billing.code,billing.modifier");
                        if (sqlNumRows($ResultSearch)>0) {
                            if ($CountPatient==1) {
                                $Table='yes';
                    ?>
                    <input id="HiddenRemainderTd<?php echo $CountIndex; ?>" name="HiddenRemainderTd<?php echo $CountIndex; ?>" type="hidden" value="<?php echo htmlspecialchars(round($RemainderJS, 2)); ?>">
                <div class = "table-responsive">  
                <table class="table-condensed" id="TableDistributePortion" >
                <thead bgcolor="#DDDDDD" class="text">
                    <td class="left top" >&nbsp;</td>
                    <td class="left top" > <?php echo htmlspecialchars(xl('Patient Name'), ENT_QUOTES) ?></td>
                    <td class="left top" ><?php echo htmlspecialchars(xl('Post For'), ENT_QUOTES) ?></td>
                    <td class="left top" ><?php echo htmlspecialchars(xl('Service Date'), ENT_QUOTES) ?></td>
                    <td class="left top" ><?php echo htmlspecialchars(xl('Encounter'), ENT_QUOTES) ?></td>
                    <td class="left top" ><?php echo htmlspecialchars(xl('Service Code'), ENT_QUOTES) ?></td>
                    <td class="left top" ><?php echo htmlspecialchars(xl('Charge'), ENT_QUOTES) ?></td>
                    <td class="left top" ><?php echo htmlspecialchars(xl('Copay'), ENT_QUOTES) ?></td>
                    <td class="left top" ><?php echo htmlspecialchars(xl('Remdr'), ENT_QUOTES) ?></td>
                    <td class="left top" ><?php echo htmlspecialchars(xl('Allowed(c)'), ENT_QUOTES) ?></td><!-- (c) means it is calculated.Not stored one. -->
                    <td class="left top" ><?php echo htmlspecialchars(xl('Payment'), ENT_QUOTES) ?></td>
                    <td class="left top" ><?php echo htmlspecialchars(xl('Adj Amount'), ENT_QUOTES) ?></td>
                    <td class="left top" ><?php echo htmlspecialchars(xl('Deductible'), ENT_QUOTES) ?></td>
                    <td class="left top" ><?php echo htmlspecialchars(xl('Takeback'), ENT_QUOTES) ?></td>
                    <td class="left top" ><?php echo htmlspecialchars(xl('MSP Code'), ENT_QUOTES) ?></td>
                    <td class="left top"><?php echo htmlspecialchars(xl('Resn'), ENT_QUOTES) ?></td>
                    <td class="left top right" ><?php echo htmlspecialchars(xl('Follow Up Reason'), ENT_QUOTES) ?></td>
                </thead>
                <?php
                            }
                            while ($RowSearch = sqlFetchArray($ResultSearch)) {
                                $CountIndex++;
                                $CountIndexAbove++;
                                $ServiceDateArray=explode(' ', $RowSearch['date']);
                                $ServiceDate=oeFormatShortDate($ServiceDateArray[0]);
                                                            $Codetype=$RowSearch['code_type'];
                                $Code=$RowSearch['code'];
                                $Modifier =$RowSearch['modifier'];
                                if ($Modifier!='') {
                                    $ModifierString=", $Modifier";
                                } else {
                                    $ModifierString="";
                                    $Fee=$RowSearch['fee'];
                                    $Encounter=$RowSearch['encounter'];

                                    $resPayer = sqlStatement("SELECT  payer_type from ar_activity where  session_id ='$payment_id' and
                                pid ='$PId' and  encounter  ='$Encounter' and code_type='$Codetype' and code='$Code' and modifier='$Modifier' ");
                                    $rowPayer = sqlFetchArray($resPayer);
                                    $Ins=$rowPayer['payer_type'];

                                    //Always associating the copay to a particular charge.
                                    $BillingId=$RowSearch['id'];
                                    $resId = sqlStatement("SELECT id  FROM billing where code_type!='ICD9' and  code_type!='COPAY'  and
                                pid ='$PId' and  encounter  ='$Encounter' and billing.activity!=0 order by id");
                                    $rowId = sqlFetchArray($resId);
                                    $Id=$rowId['id'];
                                }

                                if ($BillingId!=$Id) {//multiple cpt in single encounter
                                    $Copay=0.00;
                                } else {
                                    $resCopay = sqlStatement("SELECT sum(fee) as copay FROM billing where
                                code_type='COPAY' and  pid ='$PId' and  encounter  ='$Encounter' and billing.activity!=0");
                                    $rowCopay = sqlFetchArray($resCopay);
                                    $Copay=$rowCopay['copay']*-1;

                                    $resMoneyGot = sqlStatement("SELECT sum(pay_amount) as PatientPay FROM ar_activity where
                                pid ='$PId'  and  encounter  ='$Encounter' and  payer_type=0 and account_code='PCP'");//new fees screen copay gives account_code='PCP'
                                    $rowMoneyGot = sqlFetchArray($resMoneyGot);
                                    $PatientPay=$rowMoneyGot['PatientPay'];

                                    $Copay=$Copay+$PatientPay;
                                }

                                //For calculating Remainder
                                if ($Ins==0) {//Fetch all values
                                    $resMoneyGot = sqlStatement("SELECT sum(pay_amount) as MoneyGot FROM ar_activity where
                                pid ='$PId' and code_type='$Codetype' and code='$Code' and modifier='$Modifier'  and  encounter  ='$Encounter' and  !(payer_type=0 and
                                account_code='PCP')");
                                    //new fees screen copay gives account_code='PCP'
                                    $rowMoneyGot = sqlFetchArray($resMoneyGot);
                                    $MoneyGot=$rowMoneyGot['MoneyGot'];

                                    $resMoneyAdjusted = sqlStatement("SELECT sum(adj_amount) as MoneyAdjusted FROM ar_activity where
                                pid ='$PId' and code_type='$Codetype' and code='$Code' and modifier='$Modifier'  and  encounter  ='$Encounter'");
                                    $rowMoneyAdjusted = sqlFetchArray($resMoneyAdjusted);
                                    $MoneyAdjusted=$rowMoneyAdjusted['MoneyAdjusted'];
                                } else //Fetch till that much got
                                {
                                    //Fetch the HIGHEST sequence_no till this session.
                                    //Used maily in  the case if primary/others pays once more.
                                    $resSequence = sqlStatement("SELECT  sequence_no from ar_activity where  session_id ='$payment_id' and
                                pid ='$PId' and  encounter  ='$Encounter' order by sequence_no desc ");
                                    $rowSequence = sqlFetchArray($resSequence);
                                    $Sequence=$rowSequence['sequence_no'];

                                    $resMoneyGot = sqlStatement("SELECT sum(pay_amount) as MoneyGot FROM ar_activity where
                                pid ='$PId' and code_type='$Codetype' and code='$Code' and modifier='$Modifier'  and  encounter  ='$Encounter' and
                                payer_type > 0 and payer_type <='$Ins' and sequence_no<='$Sequence'");
                                    $rowMoneyGot = sqlFetchArray($resMoneyGot);
                                    $MoneyGot=$rowMoneyGot['MoneyGot'];

                                    $resMoneyAdjusted = sqlStatement("SELECT sum(adj_amount) as MoneyAdjusted FROM ar_activity where
                                pid ='$PId' and code_type='$Codetype' and code='$Code' and modifier='$Modifier'   and  encounter  ='$Encounter' and
                                payer_type > 0 and payer_type <='$Ins' and sequence_no<='$Sequence'");
                                    $rowMoneyAdjusted = sqlFetchArray($resMoneyAdjusted);
                                    $MoneyAdjusted=$rowMoneyAdjusted['MoneyAdjusted'];
                                }
                                $Remainder=$Fee-$Copay-$MoneyGot-$MoneyAdjusted;

                                //For calculating RemainderJS.Used while restoring back the values.
                                if ($Ins==0) {//Got just before Patient
                                    $resMoneyGot = sqlStatement("SELECT sum(pay_amount) as MoneyGot FROM ar_activity where
                                pid ='$PId' and code_type='$Codetype' and code='$Code' and modifier='$Modifier'  and  encounter  ='$Encounter' and  payer_type !=0");
                                    $rowMoneyGot = sqlFetchArray($resMoneyGot);
                                    $MoneyGot=$rowMoneyGot['MoneyGot'];

                                    $resMoneyAdjusted = sqlStatement("SELECT sum(adj_amount) as MoneyAdjusted FROM ar_activity where
                                pid ='$PId' and code_type='$Codetype' and code='$Code' and modifier='$Modifier'  and  encounter  ='$Encounter' and payer_type !=0");
                                    $rowMoneyAdjusted = sqlFetchArray($resMoneyAdjusted);
                                    $MoneyAdjusted=$rowMoneyAdjusted['MoneyAdjusted'];
                                } else {//Got just before the previous
                                    //Fetch the LOWEST sequence_no till this session.
                                    //Used maily in  the case if primary/others pays once more.
                                    $resSequence = sqlStatement("SELECT  sequence_no from ar_activity where  session_id ='$payment_id' and
                                pid ='$PId' and  encounter  ='$Encounter' order by sequence_no  ");
                                    $rowSequence = sqlFetchArray($resSequence);
                                    $Sequence=$rowSequence['sequence_no'];

                                    $resMoneyGot = sqlStatement("SELECT sum(pay_amount) as MoneyGot FROM ar_activity where
                                pid ='$PId' and code_type='$Codetype' and code='$Code' and modifier='$Modifier'   and  encounter  ='$Encounter'
                                and payer_type > 0  and payer_type <='$Ins' and sequence_no<'$Sequence'");
                                    $rowMoneyGot = sqlFetchArray($resMoneyGot);
                                    $MoneyGot=$rowMoneyGot['MoneyGot'];

                                    $resMoneyAdjusted = sqlStatement("SELECT sum(adj_amount) as MoneyAdjusted FROM ar_activity where
                                pid ='$PId' and code_type='$Codetype' and code='$Code' and modifier='$Modifier'   and  encounter  ='$Encounter'
                                and payer_type <='$Ins' and sequence_no<'$Sequence' ");
                                    $rowMoneyAdjusted = sqlFetchArray($resMoneyAdjusted);
                                    $MoneyAdjusted=$rowMoneyAdjusted['MoneyAdjusted'];
                                }
                                //Stored in hidden so that can be used while restoring back the values.
                                $RemainderJS=$Fee-$Copay-$MoneyGot-$MoneyAdjusted;

                                $resPayment = sqlStatement("SELECT  pay_amount from ar_activity where  session_id ='$payment_id' and
                            pid ='$PId' and  encounter  ='$Encounter' and code_type='$Codetype' and code='$Code' and modifier='$Modifier'  and pay_amount>0");
                                $rowPayment = sqlFetchArray($resPayment);
                                $PaymentDB=$rowPayment['pay_amount']*1;
                                $PaymentDB=$PaymentDB == 0 ? '' : $PaymentDB;

                                $resPayment = sqlStatement("SELECT  pay_amount from ar_activity where  session_id ='$payment_id' and
                            pid ='$PId' and  encounter  ='$Encounter' and code_type='$Codetype' and code='$Code' and modifier='$Modifier'  and pay_amount<0");
                                $rowPayment = sqlFetchArray($resPayment);
                                $TakebackDB=$rowPayment['pay_amount']*-1;
                                $TakebackDB=$TakebackDB == 0 ? '' : $TakebackDB;

                                $resPayment = sqlStatement("SELECT  adj_amount from ar_activity where  session_id ='$payment_id' and
                            pid ='$PId' and  encounter  ='$Encounter' and code_type='$Codetype' and code='$Code' and modifier='$Modifier'  and adj_amount!=0");
                                $rowPayment = sqlFetchArray($resPayment);
                                $AdjAmountDB=$rowPayment['adj_amount']*1;
                                $AdjAmountDB=$AdjAmountDB == 0 ? '' : $AdjAmountDB;

                                $resPayment = sqlStatement("SELECT  memo from ar_activity where  session_id ='$payment_id' and
                            pid ='$PId' and  encounter  ='$Encounter' and code_type='$Codetype' and  code='$Code' and modifier='$Modifier'  and (memo like 'Deductable%' OR memo like 'Deductible%')");
                                $rowPayment = sqlFetchArray($resPayment);
                                $DeductibleDB=$rowPayment['memo'];
                                $DeductibleDB=str_replace('Deductable $', '', $DeductibleDB);
                                $DeductibleDB=str_replace('Deductible $', '', $DeductibleDB);

                                $resPayment = sqlStatement("SELECT  follow_up,follow_up_note from ar_activity where  session_id ='$payment_id' and
                            pid ='$PId' and  encounter  ='$Encounter' and code_type='$Codetype' and  code='$Code' and modifier='$Modifier'  and follow_up = 'y'");
                                $rowPayment = sqlFetchArray($resPayment);
                                $FollowUpDB=$rowPayment['follow_up'];
                                $FollowUpReasonDB=$rowPayment['follow_up_note'];

                                $resPayment = sqlStatement("SELECT reason_code from ar_activity where  session_id ='$payment_id' and
                            pid ='$PId' and  encounter  ='$Encounter' and code_type='$Codetype' and  code='$Code' and modifier='$Modifier'");
                                $rowPayment = sqlFetchArray($resPayment);
                                $ReasonCodeDB=$rowPayment['reason_code'];

                                if ($Ins==1) {
                                    $AllowedDB=number_format($Fee-$AdjAmountDB, 2);
                                } else {
                                    $AllowedDB = 0;
                                }
                                $AllowedDB=$AllowedDB == 0 ? '' : $AllowedDB;

                                if ($CountIndex==$TotalRows) {
                                    $StringClass=' bottom left top ';
                                } else {
                                    $StringClass=' left top ';
                                }

                                if ($Ins==1) {
                                    $bgcolor='#ddddff';
                                } elseif ($Ins==2) {
                                    $bgcolor='#ffdddd';
                                } elseif ($Ins==3) {
                                    $bgcolor='#F2F1BC';
                                } elseif ($Ins==0) {
                                    $bgcolor='#AAFFFF';
                                }
                                $paymenttot=$paymenttot+$PaymentDB;
                                $adjamttot=$adjamttot+$AdjAmountDB;
                                $deductibletot=$deductibletot+$DeductibleDB;
                                $takebacktot=$takebacktot+$TakebackDB;
                                $allowedtot=$allowedtot+$AllowedDB;
                            ?>
                            <tr bgcolor='<?php echo $bgcolor; ?>' class="text" id="trCharges<?php echo $CountIndex; ?>">
                                <td align="left" class="<?php echo $StringClass; ?>">
                                    <a href="#" onclick="javascript:return DeletePaymentDistribution('<?php echo htmlspecialchars($payment_id.'_'.$PId.'_'.$Encounter.'_'.$Code.'_'.$Modifier.'_'.$Codetype); ?>');"><img border="0" src="../pic/Delete.gif"></a>
                                </td>
                                <td align="left" class="<?php echo $StringClass; ?>"><?php echo htmlspecialchars($NameDB); ?><input name="HiddenPId<?php echo $CountIndex; ?>" type="hidden" value="<?php echo htmlspecialchars($PId); ?>"></td>
                                <td align="left" class="<?php echo $StringClass; ?>"><input id="HiddenIns<?php echo $CountIndex; ?>" name="HiddenIns<?php echo $CountIndex; ?>" type="hidden" value="<?php echo htmlspecialchars($Ins); ?>"><?php echo generate_select_list("payment_ins$CountIndex", "payment_ins", "$Ins", "Insurance/Patient", '', '', 'ActionOnInsPat("'.$CountIndex.'")'); ?></td>
                                <td class="<?php echo $StringClass; ?>"><?php echo htmlspecialchars($ServiceDate); ?></td>
                                <td align="right" class="<?php echo $StringClass; ?>"><input name="HiddenEncounter<?php echo $CountIndex; ?>" type="hidden" value="<?php echo htmlspecialchars($Encounter); ?>"><?php echo htmlspecialchars($Encounter); ?></td>
                                <td class="<?php echo $StringClass; ?>"><input name="HiddenCodetype<?php echo $CountIndex; ?>" type="hidden" value="<?php echo htmlspecialchars($Codetype); ?>"><input name="HiddenCode<?php echo $CountIndex; ?>" type="hidden" value="<?php echo htmlspecialchars($Code); ?>"><?php echo htmlspecialchars($Codetype."-".$Code.$ModifierString); ?><input name="HiddenModifier<?php echo $CountIndex; ?>" type="hidden" value="<?php echo htmlspecialchars($Modifier); ?>"></td>
                                <td align="right" class="<?php echo $StringClass; ?>"><input id="HiddenChargeAmount<?php echo $CountIndex; ?>" name="HiddenChargeAmount<?php echo $CountIndex; ?>" type="hidden" value="<?php echo htmlspecialchars($Fee); ?>"><?php echo htmlspecialchars($Fee); ?></td>
                                <td align="right" class="<?php echo $StringClass; ?>"><input id="HiddenCopayAmount<?php echo $CountIndex; ?>" name="HiddenCopayAmount<?php echo $CountIndex; ?>" type="hidden" value="<?php echo htmlspecialchars($Copay); ?>"><?php echo htmlspecialchars(number_format($Copay, 2)); ?></td>
                                <td align="right" class="<?php echo $StringClass; ?>" id="RemainderTd<?php echo $CountIndex; ?>"><?php echo htmlspecialchars(round($Remainder, 2)); ?></td>
                                <td class="<?php echo $StringClass; ?>"><input autocomplete="off" id="Allowed<?php echo $CountIndex; ?>" name="Allowed<?php echo $CountIndex; ?>" onchange="ValidateNumeric(this);ScreenAdjustment(this,<?php echo $CountIndex; ?>);UpdateTotalValues(1,<?php echo $TotalRows; ?>,'Allowed','allowtotal');UpdateTotalValues(1,<?php echo $TotalRows; ?>,'Payment','paymenttotal');UpdateTotalValues(1,<?php echo $TotalRows; ?>,'AdjAmount','AdjAmounttotal');RestoreValues(<?php echo $CountIndex; ?>)" onkeydown="PreventIt(event)" style="width:60px;text-align:right; font-size:12px" type="text" value="<?php echo htmlspecialchars($AllowedDB); ?>"></td>
                                <td class="<?php echo $StringClass; ?>"><input autocomplete="off" id="Payment<?php echo $CountIndex; ?>" name="Payment<?php echo $CountIndex; ?>" onchange="ValidateNumeric(this);ScreenAdjustment(this,<?php echo $CountIndex; ?>);UpdateTotalValues(1,<?php echo $TotalRows; ?>,'Payment','paymenttotal');RestoreValues(<?php echo $CountIndex; ?>)" onkeydown="PreventIt(event)" style="width:60px;text-align:right; font-size:12px" type="text" value="<?php echo htmlspecialchars($PaymentDB); ?>"></td>
                                <td class="<?php echo $StringClass; ?>"><input autocomplete="off" id="AdjAmount<?php echo $CountIndex; ?>" name="AdjAmount<?php echo $CountIndex; ?>" onchange="ValidateNumeric(this);ScreenAdjustment(this,<?php echo $CountIndex; ?>);UpdateTotalValues(1,<?php echo $TotalRows; ?>,'AdjAmount','AdjAmounttotal');RestoreValues(<?php echo $CountIndex; ?>)" onkeydown="PreventIt(event)" style="width:70px;text-align:right; font-size:12px" type="text" value="<?php echo htmlspecialchars($AdjAmountDB); ?>"></td>
                                <td class="<?php echo $StringClass; ?>"><input autocomplete="off" id="Deductible<?php echo $CountIndex; ?>" name="Deductible<?php echo $CountIndex; ?>" onchange="ValidateNumeric(this);UpdateTotalValues(1,<?php echo $TotalRows; ?>,'Deductible','deductibletotal');" onkeydown="PreventIt(event)" style="width:60px;text-align:right; font-size:12px" type="text" value="<?php echo htmlspecialchars($DeductibleDB); ?>"></td>
                                <td class="<?php echo $StringClass; ?>"><input autocomplete="off" id="Takeback<?php echo $CountIndex; ?>" name="Takeback<?php echo $CountIndex; ?>" onchange="ValidateNumeric(this);ScreenAdjustment(this,<?php echo $CountIndex; ?>);UpdateTotalValues(1,<?php echo $TotalRows; ?>,'Takeback','takebacktotal');RestoreValues(<?php echo $CountIndex; ?>)" onkeydown="PreventIt(event)" style="width:60px;text-align:right; font-size:12px" type="text" value="<?php echo htmlspecialchars($TakebackDB); ?>"></td>
                                <td align="left" class="<?php echo $StringClass; ?>"><input id="HiddenReasonCode<?php echo $CountIndex; ?>" name="HiddenReasonCode<?php echo $CountIndex; ?>" type="hidden" value="<?php echo htmlspecialchars($ReasonCodeDB); ?>"><?php echo generate_select_list("ReasonCode$CountIndex", "msp_remit_codes", "$ReasonCodeDB", "MSP Code"); ?></td>
                                <td align="center" class="<?php echo $StringClass; ?>"><input id="FollowUp<?php echo $CountIndex; ?>" name="FollowUp<?php echo $CountIndex; ?>" onclick="ActionFollowUp(<?php echo $CountIndex; ?>)" type="checkbox" value="y"></td>
                                <td class="<?php echo $StringClass; ?> right"><input id="FollowUpReason<?php echo $CountIndex; ?>" name="FollowUpReason<?php echo $CountIndex; ?>" onkeydown="PreventIt(event)" style="width:110px;font-size:12px" type="text" value="<?php echo htmlspecialchars($FollowUpReasonDB); ?>"></td>
                            </tr><?php
                            }//End of while ($RowSearch = sqlFetchArray($ResultSearch))
                        ?>
                        <?php
                        }//End of if(sqlNumRows($ResultSearch)>0)
                    } while ($RowSearchSub = sqlFetchArray($ResultSearchSub));
                    if ($Table=='yes') {
                        ?>
<tr class="text">
<td align="left" colspan="9">&nbsp;</td>
<td align="right" bgcolor="#6699FF" class="left bottom" id="allowtotal"><?php echo htmlspecialchars(number_format($allowedtot, 2)); ?></td>
<td align="right" bgcolor="#6699FF" class="left bottom" id="paymenttotal"><?php echo htmlspecialchars(number_format($paymenttot, 2)); ?></td>
<td align="right" bgcolor="#6699FF" class="left bottom" id="AdjAmounttotal"><?php echo htmlspecialchars(number_format($adjamttot, 2)); ?></td>
<td align="right" bgcolor="#6699FF" class="left bottom" id="deductibletotal"><?php echo htmlspecialchars(number_format($deductibletot, 2)); ?></td>
<td align="right" bgcolor="#6699FF" class="left bottom right" id="takebacktotal"><?php echo htmlspecialchars(number_format($takebacktot, 2)); ?></td>
<td align="center">&nbsp;</td>
<td align="center">&nbsp;</td>
</tr>
                    </table>
                        <?php
                    }
                    ?>
                    <?php
                    echo '<br/>';
                }//End of if($RowSearchSub = sqlFetchArray($ResultSearchSub))
                    ?>
                </div>
                    <?php
                        require_once("payment_pat_sel.inc.php"); //Patient ajax section and listing of charges.
                    ?>
            <?php
            }//End of if($payment_id*1>0)
            ?>
            <?php //can change position of buttons by creating a class 'position-override' and adding rule text-align:center or right as the case may be in individual stylesheets ?>
            <div class="form-group clearfix">
                <div class="col-sm-12 text-left position-override">
                    <div class="btn-group" role="group">
                        <a class="btn btn-default btn-save" href="#" onclick="javascript:return ModifyPayments();"><span><?php echo xlt('Modify Payments');?></span></a>
                        <a class="btn btn-default btn-save" href="#" onclick="javascript:return FinishPayments();"><span><?php echo xlt('Finish Payments');?></span></a>
                    </div>
                </div>
            </div>
            <div class="row">
                <input type="hidden" name="hidden_patient_code" id="hidden_patient_code" value="<?php echo htmlspecialchars($hidden_patient_code);?>"/>
                <input type='hidden' name='mode' id='mode' value='' />
                <input type='hidden' name='ajax_mode' id='ajax_mode' value='' />
                <input type="hidden" name="after_value" id="after_value" value="<?php echo htmlspecialchars($_POST["mode"]);?>"/>
                <input type="hidden" name="payment_id" id="payment_id" value="<?php echo htmlspecialchars($payment_id);?>"/>
                <input type="hidden" name="hidden_type_code" id="hidden_type_code" value="<?php echo htmlspecialchars($TypeCode);?>"/>
                <input type='hidden' name='global_amount' id='global_amount' value='' />
                <input type='hidden' name='DeletePaymentDistributionId' id='DeletePaymentDistributionId' value='' />
                <input type="hidden" name="ActionStatus" id="ActionStatus" value="<?php echo htmlspecialchars($Message);?>"/>
                <input type='hidden' name='CountIndexAbove' id='CountIndexAbove' value='<?php echo htmlspecialchars($CountIndexAbove*1);?>' />
                <input type='hidden' name='CountIndexBelow' id='CountIndexBelow' value='<?php echo htmlspecialchars($CountIndexBelow*1);?>' />
                <input type="hidden" name="ParentPage" id="ParentPage" value="<?php echo htmlspecialchars($_REQUEST['ParentPage']);?>"/>
            </div>
        </form>
    </div>
    </div><!-- End of container div-->
</body>
</html>
