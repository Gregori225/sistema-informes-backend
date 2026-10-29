<?php

namespace App\Controllers;

use App\Config\Database;
use App\Utils\Response;
use PDO;

class InventarioController
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function listarEquipos(): void
    {
        $sql = "SELECT e.*, s.nombre_completo AS asignado_a 
                FROM equipos e 
                LEFT JOIN solicitantes s ON e.solicitante_id = s.id 
                ORDER BY e.id DESC";
        $stmt = $this->db->query($sql);
        Response::success($stmt->fetchAll());
    }

    public function listarHerramientas(): void
    {
        $stmt = $this->db->query("SELECT * FROM herramientas ORDER BY nombre ASC");
        Response::success($stmt->fetchAll());
    }

    public function listarRefacciones(): void
    {
        $stmt = $this->db->query("SELECT * FROM refacciones ORDER BY nombre ASC");
        Response::success($stmt->fetchAll());
    }
}
