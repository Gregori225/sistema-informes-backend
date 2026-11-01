<?php

namespace App\Controllers;

use App\Config\Database;
use App\Utils\Response;
use PDO;

class AuthController
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function login(): void
    {
        $data = json_decode(file_get_contents('php://input'), true);

        if (empty($data['correo']) || empty($data['password'])) {
            Response::error("Correo y contraseña requeridos", 422);
        }

        $stmt = $this->db->prepare("SELECT id, nombre, correo, password_hash, rol, activo FROM usuarios WHERE correo = :correo LIMIT 1");
        $stmt->execute([':correo' => trim($data['correo'])]);
        $usuario = $stmt->fetch();

        if (!$usuario || !$usuario['activo']) {
            Response::error("Credenciales incorrectas o usuario inactivo", 401);
        }

        // Validación de la contraseña con bcrypt
        if (!password_verify($data['password'], $usuario['password_hash'])) {
            Response::error("Credenciales incorrectas", 401);
        }

        // Generar respuesta sin exponer el hash
        unset($usuario['password_hash']);

        // Token de sesión base64 simple para el cliente
        $token = base64_encode(json_encode([
            'id' => $usuario['id'],
            'rol' => $usuario['rol'],
            'exp' => time() + (60 * 60 * 8) // 8 horas
        ]));

        Response::success([
            'usuario' => $usuario,
            'token' => $token
        ], "Inicio de sesión exitoso");
    }
}
