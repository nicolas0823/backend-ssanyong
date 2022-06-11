<?php


header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, Access-Control-Request-Method");
header("Access-Control-Allow-Methods: POST");
header("Allow: POST");
header("Content-Type: application/json; charset=UTF-8");
$method = $_SERVER['REQUEST_METHOD'];
if ($method !== "POST") {
    die();
}


include_once('db/connection.php');
require 'vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;
require 'vendor/phpmailer/phpmailer/src/PHPMailer.php';
require 'vendor/phpmailer/phpmailer/src/SMTP.php';
require 'vendor/phpmailer/phpmailer/src/Exception.php';


header('Content-Type: application/json; charset=utf-8');

$connection = new Connection();
$conex = $connection->getConnect();

// Read the input stream
$body = file_get_contents("php://input");

// Decode the JSON object
$data = json_decode($body, true);


try {
    if (validaciones($data, $connection)) {
        http_response_code(200);
        echo json_encode([
            'status' => 200, 
            'msg' => 'Ya te enviamos anteriormente una cotización', 
            'icon' => 'warning'
        ]);
        return false;
    }
        $fullname = '';
        $phone = '';
        $email = '';
        $city = '';
    if(validarRegistro($data,$connection)){
        //http_response_code(200);
        //echo json_encode(['status' => 200, 'msg' => 'El usuario ya esta registrado']);
        $sql = "UPDATE cliente set dia_cotizacion=CURRENT_DATE()  WHERE cli_correo = ? ";

        $conex = $connection->getConnect();
        $stmt = $conex->prepare($sql);
        $email='';
        $stmt->bind_param("s", $email);
        $email = $data['email'];

        $stmt->execute(); 
    }else{
        $sql = "INSERT INTO cliente (cli_nombre_completo, cli_celular, cli_correo, ciu_id) VALUES ( ?, ?, ?, ?)";
        

        $stmt = $conex->prepare($sql);
        $stmt->bind_param("ssss", $fullname, $phone, $email, $city);

        $fullname = $data['fullName'];
        $phone = $data['phone'];
        $email = $data['email'];
        $city = $data['city'];

        $stmt->execute();
    }

    

    if ($stmt->error) {
        throw new Exception('Ups, ocurrio un error', 500);
    }
    echo json_encode([
        'status' => 200,
        'msg' => 'Te hemos enviado la cotizacion',
        'icon' => 'success'
    ]);
    
    sendEMail($data);

    
    sendEMailAdmin($data);
    

} catch (Exception $e) {
    http_response_code($e->getCode() || 500);
    echo json_encode(['status' => $e->getCode(), 'msg' => $e->getMessage()]);
    return false;
}

function validaciones($data, $connection)
{
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        throw new Exception('El email no tiene el formato correcto', 400);
    }

    $phone_number_validation_regex = "/^\\+?\\d{1,4}?[-.\\s]?\\(?\\d{1,3}?\\)?[-.\\s]?\\d{1,4}[-.\\s]?\\d{1,4}[-.\\s]?\\d{1,9}$/";
    if (!preg_match($phone_number_validation_regex, $data['phone'])) {
        throw new Exception('El número teléfonico no tiene el formato correcto', 400);
    }

    if (!is_int(intval($data['model']))) {
        throw new Exception('El modelo no tiene el formato correcto', 400);
    }

    if (strlen($data['fullName']) < 6) {
        throw new Exception('El nombre debe tener mínimo 6 carácteres', 400);
    }

    if ($data['agree'] !== true) {
        throw new Exception('Los terminos y condiciones deben ser aceptados', 400);
    }

    if (!is_int(intval($data['city']))) {
        throw new Exception('La ciudad no tiene el formato correcto', 400);
    }


    $sql = "SELECT ciu_id FROM ciudad WHERE ciu_id = ? LIMIT 1";

    $conex = $connection->getConnect();
    $stmt = $conex->prepare($sql);
    $city = intval($data['city']);
    $stmt->bind_param("i", $city);
    $stmt->execute();

    $resultado = $stmt->get_result();
    $result = $resultado->fetch_assoc();

    if ($stmt->affected_rows <= 0 || !$result['ciu_id']) {
        throw new Exception('La ciudad no existe', 400);
    }

    if ($stmt->error) {
        throw new Exception('Ups, ocurrio un error', 500);
    }


    

    $sql = "SELECT cli_id, dia_cotizacion FROM cliente WHERE cli_correo = ? AND dia_cotizacion = CURRENT_DATE() LIMIT 1 ";
    $conex = $connection->getConnect();
    $stmt = $conex->prepare($sql);
    $email = '';
    $stmt->bind_param("s", $email);
    $email = $data['email'];
    $stmt->execute();
    //print_r($stmt);

    $resultado = $stmt->get_result();
    $result = $resultado->fetch_assoc();
    date_default_timezone_set('America/Bogota');
    if ($stmt->affected_rows > 0) {
        
        if ($result['cli_id'] && ($result['dia_cotizacion'] === date('Y-m-d'))) {
            return true;
        }
    }


}
function validarRegistro($data,$connection){
    $sql = "SELECT cli_id FROM cliente WHERE cli_correo = ? LIMIT 1 ";
    $conex = $connection->getConnect();
    $stmt = $conex->prepare($sql);
    $email = '';
    $stmt->bind_param("s", $email);
    $email = $data['email'];
    $stmt->execute();

    $resultado = $stmt->get_result();
    $result = $resultado->fetch_assoc();
    //print_r($sql);
    if ($stmt->affected_rows > 0) {
        return true;
    }
}

function sendEMail($data){
    $mensaje='';
    include_once 'body.php';
    
    $mail=new PHPMailer(true);
    $mensaje=str_replace("Nicolas",$data['fullName'],$mensaje);
    //se reemplaza nombre de cliente por defecto
    
    //Configurar el servidor
    $mail->SMTPDebug=0;
    $mail->isSMTP();
    $mail->Host="smtp.gmail.com";
    $mail->SMTPAuth=true;
    $mail->Username="nfajardo68@misena.edu.co";
    $mail->Password="hfudhhejoxuecnkk";
    $mail->SMTPSecure="tls";
    $mail->Port="587";

    //Informacion del destinatario y remitente
    $mail->setFrom("nfajardo68@misena.edu.co","Ssanyong Motor Colombia");// correo donde se envia
    $mail->addAddress($data['email'],$data['fullName']);

    //contenido
    $mail->isHTML(true);
    $mail->Subject="Cotizacion Ssanyong Motor Colombia";
    $mail->Body=$mensaje;
    $mail->send();

}
function sendEMailAdmin($data){
    $mensaje_admin='';
    include_once 'mensaje.php';
    
    $mail=new PHPMailer(true);
    $mensaje=str_replace("prueba",$data['fullName'],$mensaje_admin);
    //se reemplaza nombre de cliente por defecto
    
    //Configurar el servidor
    $mail->SMTPDebug=0;
    $mail->isSMTP();
    $mail->Host="smtp.gmail.com";
    $mail->SMTPAuth=true;
    $mail->Username="nfajardo68@misena.edu.co";
    $mail->Password="hfudhhejoxuecnkk";
    $mail->SMTPSecure="tls";
    $mail->Port="587";

    //Informacion del destinatario y remitente
    $mail->setFrom("nfajardo68@misena.edu.co","Ssanyong Motor Colombia");// correo donde se envia
    $mail->addAddress('jaguilar@processoft.com.co','');
    $mail->addAddress('jcastro@processoft.com.co','');
    $mail->addAddress('mahernandez@processoft.com.co','');

    //contenido
    $mail->isHTML(true);
    $mail->Subject="Cotizacion SSANYONG Cliente ".$data['fullName'];
    $mail->Body=$mensaje;
    $mail->send();
}

