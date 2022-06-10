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

header('Content-Type: application/json; charset=utf-8');

$connection = new Connection();
$conex = $connection->getConnect();

// Read the input stream
$body = file_get_contents("php://input");

// Decode the JSON object
$data = json_decode($body, true);

$sql = "INSERT INTO cliente (cli_nombre_completo, cli_celular, cli_correo, ciu_id) VALUES ( ?, ?, ?, ?)";

try {


    if (validaciones($data, $connection)) {
        http_response_code(200);
        echo json_encode(['status' => 200, 'msg' => 'Ya te enviamos una cotizacion']);
        return false;
    }

    $fullname = '';
    $phone = '';
    $email = '';
    $city = '';

    $stmt = $conex->prepare($sql);
    $stmt->bind_param("ssss", $fullname, $phone, $email, $city);

    $fullname = $data['fullName'];
    $phone = $data['phone'];
    $email = $data['email'];
    $city = $data['city'];

    $stmt->execute();

    if ($stmt->error) {
        throw new Exception('Ups, ocurrio un error', 500);
    }

    // emails


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

    $sql = "SELECT cli_id, dia_cotizacion FROM cliente WHERE cli_correo = ? LIMIT 1";
    $conex = $connection->getConnect();
    $stmt = $conex->prepare($sql);
    $email = $data['email'];
    $stmt->bind_param("s", $email);
    $stmt->execute();

    $resultado = $stmt->get_result();
    $result = $resultado->fetch_assoc();

    if ($stmt->affected_rows > 0) {
        if ($result['cli_id'] && ($result['dia_cotizacion'] === date('Y-m-d'))) {
            return true;
        }
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

    if ($data['agree'] === true) {
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

}

echo json_encode(['status' => 200, 'msg' => 'ok']);