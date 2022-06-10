<?php


header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, Access-Control-Request-Method");
header("Access-Control-Allow-Methods: POST");
header("Allow: POST");
header("Content-Type: application/json; charset=UTF-8");
$method = $_SERVER['REQUEST_METHOD'];
if($method !== "POST") {
    die();
}


include_once('db/connection.php');

header('Content-Type: application/json; charset=utf-8');

$connection = new Connection();
$conex = $connection->getConnect();

// Read the input stream
$body = file_get_contents("php://input");
 
// Decode the JSON object
$data = json_decode($body, true);

$sql = "INSERT INTO cliente (cli_nombre_completo, cli_celular, cli_correo, ciu_id) VALUES ( ?, ?, ?, ?)";
try{

    $stmt = $conex->prepare($sql);
    $stmt->bind_param("ssss", $fullname, $phone, $email, $city);

    $fullname = $data['fullName'];
    $phone = $data['phone'];
    $email = $data['email'];
    $city = $data['city'];

    $stmt->execute();  
    
    if($stmt->error) {
       throw new Exception('Ups, ocurrio un error');
    }

    // emails
    
}catch(Exception $e){
    http_response_code(500);
    echo json_encode([ 'status' => 500, 'msg' =>$e->getMessage()]);
    return false;
}

echo json_encode(['status' => 200, 'msg' => 'ok']);