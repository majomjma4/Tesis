<?php

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/app');
require APP_PATH . '/Core/Autoloader.php';
Autoloader::register();

$db = Database::connection();
$owners = $db->query("SELECT id FROM users WHERE status='active' AND deleted_at IS NULL AND purged_at IS NULL ORDER BY id LIMIT 2")->fetchAll(PDO::FETCH_COLUMN);
if (count($owners) < 2) throw new RuntimeException('Se requieren dos usuarios activos para la prueba del calendario.');
[$firstOwner, $secondOwner] = array_map('intval', $owners);
$model = new CalendarModel();
$assert = static function (bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); echo "OK  $message\n"; };

$db->beginTransaction();
try {
    $title = 'QA calendario ' . bin2hex(random_bytes(5));
    $withoutTime = $model->saveForOwner(['title'=>$title,'date'=>'2026-12-11','type'=>'personal','priority'=>'medium','description'=>'Prueba transaccional','completed'=>false], $firstOwner);
    $assert((int)$withoutTime['id'] > 0 && $withoutTime['time'] === null && $withoutTime['type'] === 'personal', 'creación personal sin hora');
    $withTime = $model->saveForOwner(['title'=>$title.' hora','date'=>'2026-12-12','time'=>'14:30','type'=>'personal','priority'=>'high','description'=>'Prueba con hora','completed'=>false], $firstOwner);
    $assert($withTime['time'] === '14:30' && $withTime['type'] === 'personal', 'creación personal con hora');
    $own = $model->getEventsForOwner($firstOwner);
    $other = $model->getEventsForOwner($secondOwner);
    $assert(in_array((int)$withoutTime['id'], array_map(static fn(array $event): int => (int)$event['id'], $own), true), 'lectura del propietario');
    $assert(!in_array((int)$withoutTime['id'], array_map(static fn(array $event): int => (int)$event['id'], $other), true), 'aislamiento entre usuarios');
    $updated = $model->saveForOwner(['id'=>$withTime['id'],'title'=>$withTime['title'],'date'=>'2026-12-13','time'=>'09:15','type'=>'personal','priority'=>'low','description'=>'Actualizado','completed'=>true], $firstOwner);
    $assert($updated['date'] === '2026-12-13' && $updated['time'] === '09:15' && $updated['type'] === 'personal' && $updated['completed'] === true, 'edición Personal con hora');
    $updatedWithoutTime = $model->saveForOwner(['id'=>$withTime['id'],'title'=>$updated['title'],'date'=>$updated['date'],'time'=>'','type'=>'personal','priority'=>$updated['priority'],'description'=>$updated['description'],'completed'=>$updated['completed']], $firstOwner);
    $assert($updatedWithoutTime['time'] === null && $updatedWithoutTime['type'] === 'personal', 'edición Personal y hora vacía');
    try { $model->saveForOwner(['id'=>$withTime['id'],'title'=>'Ajeno','date'=>'2026-12-13','type'=>'meeting','priority'=>'low','completed'=>false], $secondOwner); throw new RuntimeException('No se rechazó la edición ajena.'); }
    catch (CalendarEventException $exception) { $assert($exception->httpStatus() === 403, 'edición ajena rechazada'); }
    try { $model->saveForOwner(['title'=>'Fecha inválida','date'=>'2026-02-30','type'=>'delivery','priority'=>'low'], $firstOwner); throw new RuntimeException('No se rechazó fecha imposible.'); }
    catch (CalendarEventException $exception) { $assert($exception->httpStatus() === 422, 'fecha imposible rechazada'); }
    $model->deleteForOwner($withoutTime['id'], $firstOwner);
    $assert(true, 'eliminación del propietario');
    $restored = $model->saveForOwner(['id'=>'','title'=>$withoutTime['title'],'date'=>$withoutTime['date'],'type'=>$withoutTime['type'],'priority'=>$withoutTime['priority'],'description'=>$withoutTime['description'],'completed'=>$withoutTime['completed']], $firstOwner);
    $assert((int)$restored['id'] !== (int)$withoutTime['id'], 'deshacer crea un nuevo registro persistente');
    try { $model->deleteForOwner($withTime['id'], $secondOwner); throw new RuntimeException('No se rechazó la eliminación ajena.'); }
    catch (CalendarEventException $exception) { $assert($exception->httpStatus() === 403, 'eliminación ajena rechazada'); }
    $db->rollBack();
    echo "Pruebas del calendario personal finalizadas sin residuos.\n";
} catch (Throwable $exception) {
    if ($db->inTransaction()) $db->rollBack();
    throw $exception;
}
