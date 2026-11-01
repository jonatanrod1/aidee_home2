<?php
declare(strict_types=1);
// php scripts/seed_superadmin.php "Nombre" email clave [whatsapp]
require __DIR__ . '/../bootstrap.php';
$c = require __DIR__ . '/../config.php';
use App\Models\Usuario;
use App\Core\Database;
[$s,$nombre,$email,$clave] = [$argv[0]??'', $argv[1]??'Superadmin', $argv[2]??'', $argv[3]??''];
$wa = $argv[4] ?? null;
if (!$email || !$clave) exit("Uso: php scripts/seed_superadmin.php \"Nombre\" email clave [whatsapp]\n");
$pdo = Database::pdo();
$ex = Usuario::findByEmail($email);
$id = $ex ? (int)$ex['id'] : Usuario::create($nombre, $email, $clave, $wa);
echo "usuario_id=$id\n";
// Si no hay propiedades, crea una demo para asignar rol superadmin global
$pid = (int)($pdo->query("SELECT id FROM propiedades ORDER BY id LIMIT 1")->fetchColumn() ?: 0);
if (!$pid) {
    $pid = (int)$pdo->query("INSERT INTO propiedades (ubicacion, duenio_nombre, duenio_doc, duenio_whatsapp, n_unidades) VALUES ('Casa demo','Dueño demo','0','3000000000',0) RETURNING id")->fetchColumn();
    echo "propiedad_demo=$pid\n";
}
Usuario::asignarRol($id, $pid, 'superadmin');
echo "rol superadmin en propiedad $pid OK\n";
