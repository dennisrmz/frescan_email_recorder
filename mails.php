<?php
/*** En este archivo empezare el trabajo para envio de mails */
/*** El cliente ha solicitado que cuando falten 5 dias para cumplir el mes de su ultimo pedido  */
/*** Por que se desarrolla un JOB que consulte la base de datos para obtener los correos de los clientes y recordarles */
/*** Que su perro estara a punto de morir de hambre cool */

//El job se correra cada dia entonces buscare las ordenes completas con post_modified de hace 25 dias


use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require 'inc/Exception.php';
require 'inc/PHPMailer.php';
require 'inc/SMTP.php';
include "inc/Logs.php";
date_default_timezone_set('Etc/UTC');


$log = new Logs("log", getcwd()."//");
$log->insert('// ********************************************************** //', false, false, false);
$log->insert('Iniciando el proceso de envio de correos', false, false, false);

//PRIMERO OBTENDRE LOS CORREOS A LOS QUE LES ENVIARE EL AVISO

//Datos de Acceso A BD
$host_wp = 'localhost';
$user_wp = 'root';
$pass_wp = '';
$db_wp   = 'frescan';





//Prefijo de nombre de tablas de base de datos
$pre_wp = "frc_";
//Primero hare un select para traer los dias que tenga habilitados

$sql_days_active = "SELECT id, dias, active, created_at
FROM ". $pre_wp ."fres_recorder_days ffrd where ffrd.active = 1;";


//Query consulta de correos de pedidos completos hace 25 dias

$correos = [];

//Conexion a base de datos
$mysqli = new mysqli($host_wp, $user_wp, $pass_wp, $db_wp);

$mysqli->set_charset("utf8");

if ($mysqli->connect_error) {
    die('Connect Error (' . mysqli_connect_errno() . ') '. mysqli_connect_error());
     $log->insert('Error en la conexion a la base de datos', false, false, false);
    exit;
}

$result_days = $mysqli->query($sql_days_active);

if ($result_days) {
    if ($result_days->num_rows > 0) {
        //output data of each row
        while ($row_days = $result_days->fetch_assoc()) {
            $dias_atras = 30 - $row_days['dias'];
            $result = "";
            $sql = "";

            $sql = "SELECT rp2.meta_key , rp2.meta_value, rp.ID 
                        FROM " . $pre_wp . "posts rp 
                        LEFT JOIN " . $pre_wp . "postmeta rp2 
                        ON rp.ID = rp2.post_id 
                        WHERE rp.post_status = 'wc-completed'
                        AND rp.post_type = 'shop_order'
                        AND (date(rp.post_modified) = SUBDATE(current_date(), interval $dias_atras DAY) 
                            OR date(rp.post_date) = SUBDATE(current_date(), interval $dias_atras DAY)) 
                        AND rp2.meta_key = '_billing_email';";

            $result = $mysqli->query($sql);

            if ($result) {
                if ($result->num_rows > 0) {
                    //output data of each row
                    while ($row = $result->fetch_assoc()) {
                        array_push($correos,$row['meta_value']);
                    }
                }
            } else {
                 $log->insert("Error en el result de la consulta de correos a la base de datos para el dia $dias_atras", false, false, false);
                //("Error : " . mysqli_error($mysqli) . " " . mysqli_errno($mysqli));
            }
        }
    }else{
        $log->insert('No hay dias disponibles', false, false, false);
    }
} else {
     $log->insert('Error en el result de la consulta de dias a la base de datos', false, false, false);
    //("Error : " . mysqli_error($mysqli) . " " . mysqli_errno($mysqli));
}

//Configuracion de Opciones de Envio de Email
$mail = new PHPMailer(true);
if(count($correos) > 0){
    try {
        //Server settings
        $mail->SMTPDebug = 0;                       //Enable verbose debug output
        $mail->isSMTP();                                            //Send using SMTP
        $mail->Host       = 'smtp.gmail.com';                       //Set the SMTP server to send through
        $mail->SMTPAuth   = true;                                   //Enable SMTP authentication
        $mail->Username   = 'orellanadennis12@gmail.com';                     //SMTP username
        $mail->Password   = 'ffneyjsnwoxbtfxs';                               //SMTP password
        $mail->SMTPSecure = 'tls';                                  //Enable TLS encryption; `PHPMailer::ENCRYPTION_SMTPS` encouraged
        $mail->Port       = 587;                                    //TCP port to connect to, use 465 for `PHPMailer::ENCRYPTION_SMTPS` above
    
        //Recipients
        $mail->From = "orellanadennis12@gmail.com";
        $mail->FromName = "Dennis El Salvador";     //Add a recipient
        
        foreach($correos as $key=> $value){
            $mail->addAddress("$value");     
        }
                  //Name is optional
        //$mail->addReplyTo('info@example.com', 'Information');
        //$mail->addCC('cc@example.com');
        //$mail->addBCC('bcc@example.com');
    
        //Attachments
        //$mail->addAttachment('/var/tmp/file.tar.gz');         //Add attachments
        //$mail->addAttachment('/tmp/image.jpg', 'new.jpg');    //Optional name
    
        //Content
        $mail->isHTML(true);                                  //Set email format to HTML
        $mail->Subject = 'Recordatorio de Plan';
        //Inclusion de archivo con variable que tiene el codigo html del correo
        include 'inc/format_html.php';
        $mail->Body = $string_html;
        //$mail->AltBody = 'This is the body in plain text for non-HTML mail clients';
    
        $mail->send();
        
        $log->insert('Correos enviados con exito', false, false, false);
        $log->insert('Se enviaron un total de: ' .count($correos) , false, false, false);
    } catch (Exception $e) {
        $log->insert('Error en en el envio del correo', false, false, false);
        
    }
    
}else{
     $log->insert('La cantidad de correos obtenida fue cero', false, false, false);
}
