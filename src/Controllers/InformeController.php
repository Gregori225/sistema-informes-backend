<?php

namespace App\Controllers;

use App\Config\Database;
use App\Utils\Response;
use PDO;
use Exception;

class InformeController
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function listar(): void
    {
        $sql = "SELECT 
                    i.id,
                    i.codigo_informe,
                    i.estado,
                    i.fecha_solicitud,
                    i.descripcion_solicitud,
                    i.diagnostico_problema,
                    i.observaciones_recomendaciones,
                    s.nombre_completo AS solicitante,
                    s.departamento,
                    u.nombre AS tecnico_responsable,
                    e.serial AS equipo_serial,
                    e.tipo AS equipo_tipo,
                    e.marca AS equipo_marca,
                    e.modelo AS equipo_modelo
                FROM informes_tecnicos i
                INNER JOIN solicitantes s ON i.solicitante_id = s.id
                INNER JOIN usuarios u ON i.usuario_id = u.id
                INNER JOIN equipos e ON i.equipo_id = e.id
                ORDER BY i.id DESC";

        $stmt = $this->db->query($sql);
        Response::success($stmt->fetchAll());
    }

    public function obtenerPorId(int $id): void
    {
        $sql = "SELECT i.*, 
                       s.nombre_completo AS solicitante_nombre, 
                       u.nombre AS usuario_nombre,
                       e.serial AS equipo_serial, e.modelo AS equipo_modelo
                FROM informes_tecnicos i
                JOIN solicitantes s ON i.solicitante_id = s.id
                JOIN usuarios u ON i.usuario_id = u.id
                JOIN equipos e ON i.equipo_id = e.id
                WHERE i.id = :id";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        $informe = $stmt->fetch();

        if (!$informe) {
            Response::error("Informe no encontrado", 404);
        }

        // Obtener refacciones usadas
        $stmtRef = $this->db->prepare("SELECT r.codigo, r.nombre, ir.cantidad 
                                       FROM informe_refacciones ir 
                                       JOIN refacciones r ON ir.refaccion_id = r.id 
                                       WHERE ir.informe_id = :id");
        $stmtRef->execute([':id' => $id]);
        $informe['refacciones'] = $stmtRef->fetchAll();

        // Obtener herramientas vinculadas
        $stmtHerr = $this->db->prepare("SELECT h.codigo, h.nombre, ih.devuelto 
                                        FROM informe_herramientas ih 
                                        JOIN herramientas h ON ih.herramienta_id = h.id 
                                        WHERE ih.informe_id = :id");
        $stmtHerr->execute([':id' => $id]);
        $informe['herramientas'] = $stmtHerr->fetchAll();

        Response::success($informe);
    }

    public function crear(): void
    {
        $data = json_decode(file_get_contents('php://input'), true);

        // Validación de campos mandatorios
        $requeridos = ['codigo_informe', 'solicitante_id', 'usuario_id', 'equipo_id', 'descripcion_solicitud', 'diagnostico_problema', 'observaciones_recomendaciones'];
        foreach ($requeridos as $campo) {
            if (empty($data[$campo])) {
                Response::error("El campo '{$campo}' es estrictamente requerido", 422);
            }
        }

        try {
            // INICIO DE TRANSACCIÓN ATÓMICA
            $this->db->beginTransaction();

            // 1. Insertar el Informe Técnico
            $sql = "INSERT INTO informes_tecnicos (
                        codigo_informe, solicitante_id, usuario_id, equipo_id,
                        descripcion_solicitud, diagnostico_problema, observaciones_recomendaciones, estado
                    ) VALUES (
                        :codigo, :solicitante, :usuario, :equipo,
                        :desc, :diag, :obs, :estado
                    ) RETURNING id";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':codigo'      => trim($data['codigo_informe']),
                ':solicitante' => $data['solicitante_id'],
                ':usuario'     => $data['usuario_id'],
                ':equipo'      => $data['equipo_id'],
                ':desc'        => $data['descripcion_solicitud'],
                ':diag'        => $data['diagnostico_problema'],
                ':obs'         => $data['observaciones_recomendaciones'],
                ':estado'      => $data['estado'] ?? 'en_proceso'
            ]);
            $informeId = $stmt->fetchColumn();

            // 2. Procesar Refacciones (si se enviaron) y descontar stock
            if (!empty($data['refacciones']) && is_array($data['refacciones'])) {
                $stmtRef = $this->db->prepare("INSERT INTO informe_refacciones (informe_id, refaccion_id, cantidad) VALUES (:inf, :ref, :cant)");
                $stmtStock = $this->db->prepare("UPDATE refacciones SET stock_actual = stock_actual - :cant WHERE id = :ref AND stock_actual >= :cant");

                foreach ($data['refacciones'] as $item) {
                    // Descontar inventario validando existencia
                    $stmtStock->execute([':cant' => $item['cantidad'], ':ref' => $item['refaccion_id']]);
                    if ($stmtStock->rowCount() === 0) {
                        throw new Exception("Stock insuficiente para la refacción ID: " . $item['refaccion_id']);
                    }
                    // Asociar al informe
                    $stmtRef->execute([':inf' => $informeId, ':ref' => $item['refaccion_id'], ':cant' => $item['cantidad']]);
                }
            }

            // 3. Procesar Herramientas (si se prestaron/usaron)
            if (!empty($data['herramientas']) && is_array($data['herramientas'])) {
                $stmtHerr = $this->db->prepare("INSERT INTO informe_herramientas (informe_id, herramienta_id, devuelto) VALUES (:inf, :herr, FALSE)");
                $stmtUpdHerr = $this->db->prepare("UPDATE herramientas SET estado = 'en_uso' WHERE id = :herr");

                foreach ($data['herramientas'] as $herramientaId) {
                    $stmtHerr->execute([':inf' => $informeId, ':herr' => $herramientaId]);
                    $stmtUpdHerr->execute([':herr' => $herramientaId]);
                }
            }

            // Confirmar transacción
            $this->db->commit();
            Response::success(['id' => $informeId], "Informe técnico creado correctamente con control de inventario", 201);
        } catch (Exception $e) {
            $this->db->rollBack();
            Response::error("Error al procesar el informe: " . $e->getMessage(), 500);
        }
    }
}
