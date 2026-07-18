<?php

declare(strict_types=1);

final class CalendarModel
{
    private string $storageFile;

    public function __construct()
    {
        $this->storageFile = ROOT_PATH . '/storage/calendar/events.json';
        $this->ensureStorage();
    }

    public function getEvents(): array
    {
        $contents = @file_get_contents($this->storageFile);
        $events = json_decode($contents ?: '[]', true);
        return is_array($events) ? array_values($events) : [];
    }

    public function save(array $data): array
    {
        $events = $this->getEvents();
        $id = trim((string) ($data['id'] ?? ''));
        $event = $this->normalize($data, $id !== '' ? $id : bin2hex(random_bytes(8)));
        $index = array_search($event['id'], array_column($events, 'id'), true);

        if ($index === false) {
            $events[] = $event;
        } else {
            $events[$index] = $event;
        }

        $this->write($events);
        return $event;
    }

    public function delete(string $id): bool
    {
        $events = $this->getEvents();
        $remaining = array_values(array_filter($events, static fn(array $event): bool => ($event['id'] ?? '') !== $id));
        if (count($events) === count($remaining)) {
            return false;
        }
        $this->write($remaining);
        return true;
    }

    private function normalize(array $data, string $id): array
    {
        $types = ['delivery', 'meeting', 'review', 'deadline'];
        $priorities = ['low', 'medium', 'high'];
        $type = in_array($data['type'] ?? '', $types, true) ? $data['type'] : 'delivery';
        $priority = in_array($data['priority'] ?? '', $priorities, true) ? $data['priority'] : 'medium';
        $date = (string) ($data['date'] ?? '');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            throw new InvalidArgumentException('La fecha no es válida.');
        }
        $title = trim(strip_tags((string) ($data['title'] ?? '')));
        if ($title === '') {
            throw new InvalidArgumentException('El título es obligatorio.');
        }

        return [
            'id' => $id,
            'title' => mb_substr($title, 0, 100),
            'date' => $date,
            'type' => $type,
            'priority' => $priority,
            'description' => mb_substr(trim(strip_tags((string) ($data['description'] ?? ''))), 0, 300),
            'completed' => filter_var($data['completed'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'updatedAt' => date(DATE_ATOM),
        ];
    }

    private function ensureStorage(): void
    {
        $directory = dirname($this->storageFile);
        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }
        if (!is_file($this->storageFile)) {
            $today = new DateTimeImmutable('today');
            $seed = [
                ['id'=>'demo-1','title'=>'Reunión con el tutor','date'=>$today->format('Y-m-d'),'type'=>'meeting','priority'=>'high','description'=>'Revisar observaciones y definir el siguiente avance.','completed'=>false,'updatedAt'=>date(DATE_ATOM)],
                ['id'=>'demo-2','title'=>'Entrega del avance','date'=>$today->modify('+3 days')->format('Y-m-d'),'type'=>'delivery','priority'=>'high','description'=>'Subir la versión corregida del documento.','completed'=>false,'updatedAt'=>date(DATE_ATOM)],
                ['id'=>'demo-3','title'=>'Revisión de referencias','date'=>$today->modify('+7 days')->format('Y-m-d'),'type'=>'review','priority'=>'medium','description'=>'Validar citas, bibliografía y anexos.','completed'=>false,'updatedAt'=>date(DATE_ATOM)],
            ];
            $this->write($seed);
        }
    }

    private function write(array $events): void
    {
        $json = json_encode(array_values($events), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false || file_put_contents($this->storageFile, $json, LOCK_EX) === false) {
            throw new RuntimeException('No fue posible guardar el calendario.');
        }
    }
}
