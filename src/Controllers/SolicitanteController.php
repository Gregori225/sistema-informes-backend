<?php

namespace App\Controllers;

use App\Config\Database;
use App\Utils\Response;
use PDO;

class SolicitanteController
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function listar(): void
    {
        $stmt = $this->db->query("SELECT * FROM solicitantes ORDER BY nombre_completo ASC");
        Response::success($stmt->fetchAll());
    }

    public function crear(): void
    {
        $data = json_decode(file_get_contents('php://input'), true);

        if (empty($data['cedula']) || empty($data['nombre_completo']) || empty($data['departamento'])) {
            Response::error("Campos obligatorios: cedula, nombre_completo, departamento", 422);
        }

        $sql = "INSERT INTO solicitantes (cedula, nombre_completo, departamento, telefono, correo)
                VALUES (:cedula, :nombre, :dpto, :telf, :correo) RETURNING id";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':cedula' => trim($data['cedula']),
            ':nombre' => trim($data['nombre_completo']),
            ':dpto'   => trim($data['departamento']),
            ':telf'   => $data['telefono'] ?? null,
            ':correo' => $data['correo'] ?? null
        ]);

        $nuevoId = $stmt->fetchColumn();
        Response::success(['id' => $nuevoId], "Solicitante registrado exitosamente", 201);
    }
}
