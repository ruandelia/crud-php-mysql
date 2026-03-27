<?php

//Define um conteúdo do tipo JSON
header("Content-Type: application/json");

//Permite que qualquer origem (domínio) possa acessar o recurso (cors)
header("Access-Control-Allow-Origin: *");

//Especifica os métodos http ou https que são permitidos para o recurso.
header("Access-Control-Methods: GET, POST, PUT, DELETE");

//Define quais cabeçalhos http ou https são permitidos na requisição.
header("Access-Control-Headers: Content-Type");

//Obtém o método http da requisição.
$method = $_SERVER['REQUEST_METHOD'];

//Define o caminho do arquivo para obter os dados armazenados.
$dataFile = "..//data/data.json";

/*
*Função para salvar os dados em JSON.
*Receber um array, codifica para JSON formatado e salva no arquivo definido.
*@param array $data Dados a ser salvo no arquivo JSON
*/
function saveData($data)
{
    //Usa a variável 'global' $dataFile que contém o caminho do arquivo.
    global $dataFile;
    /*Converte o aqqray - $data para string JSON formatada.
    *(Com identação - para facilitar a leitura)
    *e grava essa string no arquivo especifico em '$dataFile'
    *sobrescrevendo o conteúdo anterior.
    */
    file_put_contents($dataFile, json_encode($data, JSON_PRETTY_PRINT));
}
/*
*Função para ler os dados do arquivo JSON
*Verifica se o arquivo existe, se não existir, retorna um array vazio
*Se existir, lê o conteúdo do arquivo, decodifica o JSON para array
*associativo e retorna 
*@return array Dados lidos do arquivo JSON
*/
function readData()
{
    //Usa a variável 'global' $dataFile que contém o caminho do arquivo.
    global $dataFile;

    /*
    *Verifica se o arquivo em $dataFile existe.
    *Se o arquivo não existir, retorna array vazio.
    */

    if (!file_exists($dataFile)) return [];


    /*Lê o conteúdo do arquivo JSON e decodifica para um array associativo.
    *'true' no segundo parâmetro da 'json_decode' indica que o resultado
    *será um array, não um objeto.
    */
    $data = json_decode(file_get_contents($dataFile), true);

    // Se o JSON estiver vazio ou inválido, retorna array vazio para evitar count(null)
    if (!is_array($data)) {
        return [];
    }

    return $data;
}

/**
 * Função para validar os dados de entrada
 */
function validarCamposObrigatorios($input, $campos)
{
    foreach ($campos as $campo) {
        if (empty($input[$campo])) {
            http_response_code(400);
            echo json_encode([
                'erro' => true,
                'message' => "Campo: '$campo' é obrigatório!"
            ]);
            exit;
        }
    }
}

// Conecta com banco de dados
function getBDConnection()
{
    $dbhost = 'localhost';
    $dbname = 'agenda_api';
    $dbuser = 'root';
    $dbpass = '';
    $dbcharset = 'utf8mb4';
    $dns = "mysql:host=$dbhost;dbname=$dbname;charset=$dbcharset";

    $option = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ];

    try {
        return new PDO($dns, $dbuser, $dbpass, $option);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode([
            'erro' => true,
            'message' => "ERRO na conexão do banco: " . $e->getMessage()
        ]);
        exit;
    }
}

/**
 * Função para sincronização do arquivo JSON com Banco de Dados.
 */
function sincronizarDataJsonParaMySQL()
{
    $db = getBDConnection();

    $jsonFile = __DIR__ . "/../data/data.json";

    if (!file_exists($jsonFile)) {
        http_response_code(404);
        echo json_encode([
            'erro' => true,
            'message' => "Arquivo data.json não encontrado!"
        ]);
        exit;
    }

    $jsonContent = file_get_contents($jsonFile);
    $data = json_decode($jsonContent, true);

    if (!$data) {
        http_response_code(400);
        echo json_encode([
            'erro' => true,
            'message' => "ERRO ao decodificar o JSON!"
        ]);
        exit;
    }

    $stmt = $db->prepare("
        INSERT INTO tbUSUARIO (id, nome, email)
        VALUES (?, ?, ?) 
        ON DUPLICATE KEY UPDATE nome = VALUES(nome), email = VALUES(email)
    "); // Adicionado o 'S' em VALUES

    foreach ($data as $user) {
        $stmt->execute([$user['id'], $user['nome'], $user['email']]);
    }

    echo json_encode([
        'erro' => false,
        'message' => "Sincronização Concluída com sucesso!"
    ]);
}

if (isset($_GET['action']) && $_GET['action'] === 'sync') {
    sincronizarDataJsonParaMySQL();
    exit;
}

switch ($method) {
    case 'GET':
        //Para requisição 'GET', lê os dados do arquivo e retorna o JSON
        echo json_encode(readData());
        break;
    case 'POST':
        //Para requisição 'POST', lê o corpo da requisição JSON e decodifica o array associativo
        $input = json_decode(file_get_contents('php://input'), true);
        //Uso de uma função para validar os dados de entrada
        validarCamposObrigatorios($input, ['nome', 'email']);
        //Lê os dados atuais do arquivo JSON
        $data = readData();
        $novoId = count($data) > 0 ? end($data)['id'] + 1 : 1;
        $novoRegistro = [
            "id" => $novoId,
            "nome" => $input['nome'],
            "email" => $input['email']
        ];
        $data[] = $novoRegistro;
        //Salva o array atualizado no arquivo JSON
        saveData($data);

        // Dentro do case 'POST'
        $db = getBDConnection();
        // Adicione o campo 'id' no comando e no execute
        $stmt = $db->prepare("INSERT INTO tbUSUARIO (id, nome, email) VALUES (:id, :nome, :email)");
        $stmt->execute([
            'id'    => $novoId, // Agora o banco recebe o mesmo ID do JSON
            'nome'  => $input['nome'],
            'email' => $input['email']
        ]);
        /**
         * Retorna uma resposta JSON informado que o dado foi criado junto com os dados já inseridos em JSON.
         */
        echo json_encode([
            'erro' => false,
            'status' => "criado",
            'data' => $novoRegistro
        ]);
        break;
    case 'PUT':
        $input = json_decode(file_get_contents('php://input'), true);
        validarCamposObrigatorios($input, ['nome', 'email']);
        $data = readData();
        $atualizado = null;

        $db = getBDConnection();
        $stmt = $db->prepare("UPDATE tbUSUARIO SET nome = :nome, email = :email WHERE id = :id");
        $stmt->execute(['nome' => $input['nome'], 'email' => $input['email'], 'id' => $input['id']]);

        foreach ($data as &$item) {
            if ($item['id'] === $input['id']) {
                $item = [
                    "id" => $input['id'],
                    "nome" => $input['nome'],
                    "email" => $input['email']
                ];
                $atualizado = true;
                break;
            }
        }
        if ($atualizado) {
            saveData($data);
            echo json_encode([
                'erro' => false,
                'status' => "atualizado",
                'data' => $atualizado
            ]);
        } else {
            echo json_encode([
                'erro' => false,
                'status' => "atualizado",
                'data' => $item // Retorna o item que foi modificado dentro do loop
            ]);
        }
        break;
    case 'DELETE':
        $input = json_decode(file_get_contents('php://input'), true);
        $data = readData();
        // Filtra os dados
        $data = array_filter($data, fn($item) => $item['id'] != $input['id']);

        saveData(array_values($data));

        $db = getBDConnection();
        $stmt = $db->prepare("DELETE FROM tbUSUARIO WHERE id = :id");
        $stmt->execute(['id' => $input['id']]);

        echo json_encode([
            'erro' => false,
            'status' => "deletado",
        ]);
        break; // <--- FALTAVA ESTE BREAK

    default:
        http_response_code(405);
        echo json_encode(['erro' => true, 'message' => "Método não permitido!"]);
        break;
}
